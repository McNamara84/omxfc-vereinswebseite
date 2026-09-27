<?php

namespace App\Services\RpgCombat;

use App\Models\Activity;
use App\Models\RpgCharacter;
use App\Models\RpgCombat;
use App\Models\RpgCombatDecision;
use App\Models\RpgCombatDelivery;
use App\Models\RpgCombatMilestone;
use App\Models\User;
use App\Services\RpgAccess;
use App\Support\RpgCombatRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class CombatService
{
    public function __construct(private RpgAccess $access, private CombatSnapshotFactory $snapshots, private CombatEngine $engine, private AutomaticDecision $automatic) {}

    public function challenge(User $user, array $input): RpgCombat
    {
        $data = Validator::make($input, ['submission_key' => 'required|uuid', 'character_id' => 'required|integer|min:1',
            'opponent_id' => 'required|integer|different:character_id|min:1', 'revision' => 'required|integer|min:0', 'opponent_revision' => 'required|integer|min:0',
            'distance' => 'required|integer|min:1|max:'.config('rpg-combat.maximum_start_distance')])->validate();

        return DB::transaction(function () use ($user, $data) {
            $team = $this->access->team(lock: true);
            abort_unless(config('rpg-combat.enabled') && $team && $this->access->isMember($user->id, $team), 403);
            $hash = $this->hash($data);
            if ($existing = RpgCombat::where('submission_key', $data['submission_key'])->first()) {
                abort_unless($existing->created_by === $user->id && $existing->submission_hash === $hash, 409);

                return $existing;
            }
            $characters = RpgCharacter::whereIn('id', [$data['character_id'], $data['opponent_id']])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $own = $characters->get($data['character_id']);
            $other = $characters->get($data['opponent_id']);
            abort_unless($own && $own->user_id === $user->id && $other && $other->user_id !== $user->id && $this->access->isMember($other->user_id, $team), 403);
            $this->ensure($own->revision === (int) $data['revision'] && $other->revision === (int) $data['opponent_revision'], 'Ein Charakter wurde geändert. Bitte die Auswahl aktualisieren.');
            $this->ensure(! DB::table('rpg_combat_character_locks')->whereIn('rpg_character_id', $characters->keys())->exists(), 'Ein Charakter befindet sich bereits in einem Kampf.');
            $duplicate = RpgCombat::where('status', 'challenged')->where('expires_at', '>', now('UTC'))
                ->whereHas('participants', fn ($q) => $q->where('rpg_character_id', $own->id))
                ->whereHas('participants', fn ($q) => $q->where('rpg_character_id', $other->id))->exists();
            $this->ensure(! $duplicate, 'Für dieses Charakterpaar besteht bereits eine Herausforderung.');
            $combat = RpgCombat::create(['team_id' => $team->id, 'created_by' => $user->id, 'submission_key' => $data['submission_key'], 'submission_hash' => $hash,
                'rule_version' => RpgCombatRules::VERSION, 'distance' => $data['distance'] * 100, 'round_limit' => config('rpg-combat.round_limit'),
                'expires_at' => now('UTC')->addDays(config('rpg-combat.invitation_days'))]);
            foreach ([$own, $other] as $index => $character) {
                $snapshot = $this->snapshots->make($character);
                $combat->participants()->create(['side' => $index + 1, 'rpg_character_id' => $character->id, 'owner_id' => $character->user_id,
                    'character_revision' => $character->revision, 'character_name' => $character->displayName(), 'snapshot_hash' => $this->hash($snapshot), 'snapshot' => $snapshot]);
            }
            $this->log($combat, [['kind' => 'challenged', 'data' => ['message' => 'Herausforderung für eine offene, ebene Arena erstellt.', 'distance_cm' => $combat->distance, 'round_limit' => $combat->round_limit,
                'rule_version' => RpgCombatRules::VERSION, 'rulebook_sha256' => RpgCombatRules::RULEBOOK_SHA256]]], $user);
            $this->milestone($combat, 'challenged');
            $this->delivery($combat, $other->user_id, 'invitation');

            return $combat;
        }, 3);
    }

    public function command(User $user, int $id, string $command, string $key): RpgCombat
    {
        Validator::make(['key' => $key], ['key' => 'required|uuid'])->validate();

        return $this->locked($id, function (RpgCombat $combat) use ($user, $command, $key, $id) {
            Gate::forUser($user)->authorize('view', $combat);
            $side = $combat->participants->firstWhere('owner_id', $user->id)?->side;
            abort_unless($side, 403);
            $hash = $this->hash([$id, $command, $user->id]);
            if ($previous = DB::table('rpg_combat_commands')->where('submission_key', $key)->first()) {
                abort_unless($previous->input_hash === $hash, 409);

                return;
            }
            if (! $this->maintain($combat)) {
                return;
            }
            if (in_array($command, ['accept', 'decline', 'withdraw'], true)) {
                abort_unless($side === ($command === 'withdraw' ? 1 : 2), 403);
                $this->ensure($combat->status === 'challenged', 'Diese Einladung ist nicht mehr offen.');
                if ($command === 'accept') {
                    foreach ($combat->participants as $participant) {
                        $this->ensure($participant->character->revision === $participant->character_revision, 'Ein Charakter wurde geändert. Bitte eine neue Herausforderung erstellen.');
                        $this->ensure($this->hash($this->snapshots->make($participant->character)) === $participant->snapshot_hash, 'Charakterdaten stimmen nicht mehr mit der Vorschau überein.');
                    }
                    $this->ensure(! DB::table('rpg_combat_character_locks')->whereIn('rpg_character_id', $combat->participants->pluck('rpg_character_id'))->exists(), 'Ein Charakter kämpft bereits.');
                    foreach ($combat->participants as $participant) {
                        DB::table('rpg_combat_character_locks')->insert(['rpg_character_id' => $participant->rpg_character_id, 'rpg_combat_id' => $combat->id]);
                    }
                    $combat->accepted_at = now('UTC');
                    $this->persist($combat, $this->engine->start($combat->participants->pluck('snapshot')->all(), $combat->distance, $combat->round_limit), $user);
                    $this->milestone($combat, 'started');
                    foreach ($combat->participants as $participant) {
                        $this->delivery($combat, $participant->owner_id, 'started');
                    }
                } else {
                    $this->close($combat, $command === 'decline' ? 'declined' : 'withdrawn', null, $user);
                }
            } else {
                $this->ensure($combat->accepted_at !== null && $combat->isOpen(), 'Der Kampf läuft nicht.');
                if ($command === 'surrender') {
                    $this->close($combat, 'surrender', 3 - $side, $user);
                } elseif ($command === 'abort') {
                    $state = $combat->state;
                    if ($state['abort_offer'] !== null && $state['abort_offer'] !== $side) {
                        $this->close($combat, 'cancelled', null, $user);
                    } else {
                        $state['abort_offer'] = $side;
                        $combat->state = $state;
                        $combat->revision++;
                        $combat->save();
                        $this->log($combat, [['kind' => 'abort_offer', 'side' => $side, 'data' => ['message' => 'Einvernehmlichen Abbruch angeboten. Laufende Entscheidungen bleiben offen.']]], $user);
                    }
                } else {
                    $this->ensure(false, 'Unbekannte Kampfaktion.');
                }
            }
            DB::table('rpg_combat_commands')->insert(['rpg_combat_id' => $id, 'user_id' => $user->id, 'submission_key' => $key, 'input_hash' => $hash, 'created_at' => now('UTC')]);
        });
    }

    public function decide(?User $user, int $id, int $decisionId, array $input = []): RpgCombat
    {
        return $this->locked($id, function (RpgCombat $combat) use ($user, $decisionId, $input) {
            if ($user) {
                Gate::forUser($user)->authorize('view', $combat);
            }
            $decision = $combat->decisions()->lockForUpdate()->findOrFail($decisionId);
            if ($user) {
                $allowed = $decision->type === 'ruling' ? Gate::forUser($user)->allows('rule', $combat)
                    : $combat->participants->firstWhere('side', $decision->controller_side)?->owner_id === $user->id;
                abort_unless($allowed, 403);
            }
            if ($decision->status === 'resolved') {
                abort_if($user && ! $decision->automatic && ($decision->resolved_by !== $user->id || $decision->response_hash !== $this->hash($input)), 409);

                return;
            }
            if (! $this->maintain($combat)) {
                return;
            }
            $this->ensure($decision->status === 'pending' && $decision->due_at !== null, 'Diese Entscheidung ist pausiert oder beendet.');
            $timedOut = now('UTC')->greaterThanOrEqualTo($decision->due_at);
            if (! $user && ! $timedOut) {
                return;
            }
            $task = $combat->state['tasks'][$decision->token] ?? null;
            $this->ensure($task !== null, 'Diese Entscheidung ist nicht mehr offen.');
            $response = $timedOut ? $this->automatic->input($combat->state, $task) : $input;
            try {
                $output = $this->engine->decide($combat->state, $decision->token, $response);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['decision' => $exception->getMessage()]);
            }
            $decision->update(['status' => 'resolved', 'resolved_at' => now('UTC'), 'resolved_by' => $timedOut ? null : $user?->id,
                'automatic' => $timedOut, 'response' => $response, 'response_hash' => $this->hash($response)]);
            // Do not expose sealed simultaneous declarations through decision audit entries.
            $this->log($combat, [['kind' => 'decision', 'side' => $task['side'], 'data' => ['message' => $timedOut ? '24-Stunden-Frist abgelaufen: Standardentscheidung ausgeführt.' : 'Entscheidung verbindlich übernommen.', 'type' => $task['type']]]], $timedOut ? null : $user, $timedOut ? 'timeout' : 'player');
            $this->persist($combat, $output, $timedOut ? null : $user, $timedOut ? 'timeout' : 'player');
        });
    }

    public function sweep(int $id): void
    {
        $this->locked($id, fn (RpgCombat $combat) => $this->maintain($combat));
    }

    private function locked(int $id, callable $callback): RpgCombat
    {
        return DB::transaction(function () use ($id, $callback) {
            $this->access->team(lock: true);
            $reference = RpgCombat::with('participants')->findOrFail($id);
            RpgCharacter::whereIn('id', $reference->participants->pluck('rpg_character_id')->filter())->orderBy('id')->lockForUpdate()->get();
            $combat = RpgCombat::lockForUpdate()->findOrFail($id);
            $combat->load('participants.character');
            $callback($combat);

            return $combat->fresh(['participants']);
        }, 3);
    }

    private function maintain(RpgCombat $combat): bool
    {
        if (! $combat->isOpen()) {
            return false;
        }
        $team = $this->access->team();
        if (! $team || $combat->team_id !== $team->id || $combat->participants->contains(fn ($p) => ! $p->character || $p->character->user_id !== $p->owner_id || ! $this->access->isMember($p->owner_id, $team))) {
            $this->close($combat, 'membership_changed');

            return false;
        }
        if ($combat->status === 'challenged' && now('UTC')->greaterThanOrEqualTo($combat->expires_at)) {
            $this->close($combat, 'expired');

            return false;
        }
        // A new leader receives the open case without resetting its deadline.
        foreach ($combat->decisions()->where('type', 'ruling')->where('status', 'pending')->get() as $decision) {
            if (! $combat->participants->contains('owner_id', $team->user_id) && $this->access->isMember($team->user_id, $team)) {
                $this->delivery($combat, $team->user_id, 'decision', $decision);
            }
        }

        return true;
    }

    private function persist(RpgCombat $combat, array $output, ?User $user, string $origin = 'player'): void
    {
        $state = $output['state'];
        $combat->state = $state;
        $combat->revision++;
        $combat->status = match ($state['phase']) {
            'preparing' => 'preparing', 'awaiting_ruling' => 'awaiting_ruling', 'completed' => 'completed', default => 'active'
        };
        $combat->save();
        $this->log($combat, $output['events'], $user, $origin);
        if ($state['end']) {
            $this->close($combat, $state['end']['reason'], $state['end']['winner'], $user, false);

            return;
        }
        foreach ($state['tasks'] as $task) {
            $paused = $state['continuation'] !== null && $task['type'] !== 'ruling';
            $decision = $combat->decisions()->firstOrCreate(['token' => $task['token']], ['side' => $task['side'], 'controller_side' => $task['controller'], 'type' => $task['type'],
                'context' => $task['context'], 'opened_at' => now('UTC'), 'due_at' => now('UTC')->addHours(config('rpg-combat.decision_hours'))]);
            if ($paused && $decision->due_at) {
                $decision->update(['remaining_seconds' => max(0, (int) now('UTC')->diffInSeconds($decision->due_at, false)), 'due_at' => null, 'status' => 'paused']);
            } elseif (! $paused && $decision->status === 'paused') {
                $decision->update(['due_at' => now('UTC')->addSeconds($decision->remaining_seconds), 'remaining_seconds' => null, 'status' => 'pending']);
            }
            if (! $paused) {
                $recipient = $task['controller'] ? $combat->participants->firstWhere('side', $task['controller'])?->owner_id : $this->access->team()?->user_id;
                if ($recipient && ($task['controller'] || ! $combat->participants->contains('owner_id', $recipient))) {
                    $this->delivery($combat, $recipient, 'decision', $decision);
                }
            }
        }
    }

    public function close(RpgCombat $combat, string $reason, ?int $winner = null, ?User $user = null, bool $log = true): void
    {
        $state = $combat->state;
        if ($state) {
            $state['tasks'] = [];
            $state['end'] = ['winner' => $winner, 'reason' => $reason];
            $state['phase'] = 'completed';
        }
        $combat->update(['status' => $combat->accepted_at ? 'completed' : $reason, 'state' => $state, 'winner_side' => $winner,
            'result' => $reason, 'completed_at' => now('UTC'), 'revision' => $combat->revision + 1]);
        $combat->decisions()->whereIn('status', ['pending', 'paused'])->update(['status' => 'cancelled', 'resolved_at' => now('UTC')]);
        DB::table('rpg_combat_character_locks')->where('rpg_combat_id', $combat->id)->delete();
        if ($log) {
            $this->log($combat, [['kind' => 'completed', 'data' => ['message' => 'Übungskampf beendet.', 'reason' => $reason, 'winner' => $winner]]], $user, 'system');
        }
        if ($combat->accepted_at) {
            $this->milestone($combat, 'completed');
        }
    }

    private function log(RpgCombat $combat, array $events, ?User $user, string $origin = 'player'): void
    {
        $sequence = (int) $combat->events()->max('sequence');
        foreach ($events as $event) {
            $combat->events()->create(['sequence' => ++$sequence, 'round' => $event['round'] ?? ($combat->state['round'] ?? 0), 'kind' => $event['kind'],
                'side' => $event['side'] ?? null, 'actor_id' => $user?->id, 'origin' => $origin, 'data' => $event['data'], 'created_at' => now('UTC')]);
        }
    }

    private function milestone(RpgCombat $combat, string $kind): void
    {
        $combat->loadMissing('participants.owner');
        [$a, $b] = $combat->participants->all();
        $milestone = RpgCombatMilestone::firstOrCreate(['rpg_combat_id' => $combat->id, 'kind' => $kind], [
            'challenger_name' => $a->owner?->nicknameOrName() ?? 'Ehemaliges Mitglied', 'defender_name' => $b->owner?->nicknameOrName() ?? 'Ehemaliges Mitglied',
            'challenger_character' => $a->character_name, 'defender_character' => $b->character_name, 'winner_side' => $combat->winner_side, 'result' => $combat->result]);
        if ($milestone->wasRecentlyCreated) {
            Activity::create(['user_id' => $combat->created_by, 'subject_type' => RpgCombatMilestone::class, 'subject_id' => $milestone->id, 'action' => 'rpg_combat_'.$kind]);
        }
    }

    private function delivery(RpgCombat $combat, int $recipient, string $kind, ?RpgCombatDecision $decision = null): void
    {
        RpgCombatDelivery::firstOrCreate(['event_key' => implode(':', [$combat->id, $kind, $decision?->id ?? 0, $recipient])], [
            'rpg_combat_id' => $combat->id, 'rpg_combat_decision_id' => $decision?->id, 'recipient_id' => $recipient, 'kind' => $kind]);
    }

    private function hash(array $input): string
    {
        $canonical = function (array $data) use (&$canonical): array {
            if (! array_is_list($data)) {
                ksort($data);
            }
            foreach ($data as &$value) {
                if (is_array($value)) {
                    $value = $canonical($value);
                }
            }

            return $data;
        };

        return hash('sha256', json_encode($canonical($input), JSON_THROW_ON_ERROR));
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['combat' => $message]);
        }
    }
}
