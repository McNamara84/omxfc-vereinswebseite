<?php

namespace App\Services;

use App\Models\RpgCharacter;
use App\Models\RpgCheck;
use App\Models\RpgCheckBatch;
use App\Models\Team;
use App\Models\User;
use App\Support\RpgCheckInput;
use App\Support\RpgCheckRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RpgCheckService
{
    public function __construct(private RpgAccess $access, private RpgCheckCalculator $calculator, private RpgCheckDice $dice) {}

    public function preview(User $actor, array $input): array
    {
        $team = $this->access->requireLeader($actor);
        $data = RpgCheckInput::validate($input);

        return ['participants' => $this->snapshots($team, $data), 'difficulty' => $data['difficulty'], 'mode' => $data['mode']];
    }

    public function create(User $actor, array $input): RpgCheckBatch
    {
        $data = RpgCheckInput::validate($input, store: true);

        return DB::transaction(function () use ($actor, $data): RpgCheckBatch {
            $team = $this->access->requireLeader($actor, lock: true);
            $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $existing = RpgCheckBatch::where('submission_key', $data['submission_key'])->first();
            if ($existing) {
                abort_unless($existing->created_by === $actor->id && $existing->team_id === $team->id, 403);
                RpgCheckInput::ensure($existing->submission_hash === $hash, 'Diese Aufforderung wurde bereits mit anderen Angaben gespeichert.');

                return $existing;
            }
            RpgCharacter::whereIn('id', array_column($data['participants'], 'character_id'))->orderBy('id')->lockForUpdate()->get();
            $snapshots = $this->snapshots($team, $data, checkRevision: true);
            $batch = RpgCheckBatch::create([
                'team_id' => $team->id, 'created_by' => $actor->id, 'submission_key' => $data['submission_key'],
                'submission_hash' => $hash, 'description' => $data['description'], 'visibility' => $data['visibility'],
                'rule_version' => RpgCheckRules::VERSION,
            ]);
            $groups = $data['mode'] === 'opposed' ? [$snapshots] : array_map(fn ($snapshot) => [$snapshot], $snapshots);
            foreach ($groups as $group) {
                $check = $batch->checks()->create(['mode' => $data['mode'], 'difficulty' => $data['difficulty'], 'status' => 'pending']);
                foreach ($group as $i => $snapshot) {
                    $check->participants()->create($snapshot + ['position' => $i + 1]);
                }
            }

            return $batch;
        }, 3);
    }

    private function snapshots(Team $team, array $data, bool $checkRevision = false): array
    {
        $characters = RpgCharacter::whereIn('id', array_column($data['participants'], 'character_id'))->get()->keyBy('id');
        $memberIds = $team->activeUsers()->pluck('users.id')->all();
        $names = new RpgCharacterSheetService;
        $rows = [];
        foreach ($data['participants'] as $spec) {
            $character = $characters->get($spec['character_id']);
            RpgCheckInput::ensure($character !== null && $character->user_id !== $team->user_id && in_array($character->user_id, $memberIds, true),
                'Alle Charaktere müssen anderen aktiven Mitgliedern der AG Rollenspiel gehören.');
            RpgCheckInput::ensure(! $checkRevision || $character->revision === $spec['revision'], 'Ein Charakter wurde geändert. Bitte die Vorschau erneut prüfen.');
            $payload = $character->payload;
            $attribute = $payload['attributes'][$spec['attribute_key']] ?? null;
            RpgCheckInput::ensure(filter_var($attribute, FILTER_VALIDATE_INT) !== false && $attribute !== null && abs((int) $attribute) <= 1000,
                'Der benötigte Attributswert fehlt oder ist ungültig: '.$character->displayName());
            $skill = null;
            if ($spec['check_type'] === 'skill') {
                RpgCheckInput::ensure(in_array($spec['skill_name'], RpgCheckRules::skills($payload), true), 'Diese Fertigkeit kann nicht geprüft werden.');
                $skill = 0;
                foreach ($payload['skills'] ?? [] as $entry) {
                    if ($names->canonicalSkillName($entry['name']) === $spec['skill_name']) {
                        $value = $entry['value'] ?? null;
                        RpgCheckInput::ensure($value !== null && filter_var($value, FILTER_VALIDATE_INT) !== false && $value >= 0 && $value <= 10000,
                            'Der Fertigkeitswert ist ungültig.');
                        $skill = max($skill, (int) $value);
                    }
                }
            }
            $modifier = array_sum(array_column($spec['modifiers'], 'value'));
            $rows[] = [
                'rpg_character_id' => $character->id, 'owner_id' => $character->user_id, 'character_name' => $character->displayName(),
                'character_revision' => $character->revision, 'check_type' => $spec['check_type'],
                'attribute_key' => $spec['attribute_key'], 'skill_name' => $spec['skill_name'],
                'attribute_value' => (int) $attribute, 'skill_value' => $skill, 'modifiers' => $spec['modifiers'],
                'modifier_total' => $modifier, 'base_total' => $this->calculator->base($spec['check_type'], (int) $attribute, $skill, $modifier),
            ];
        }

        return $rows;
    }

    public function executable(RpgCheck $check, Team $team, ?array $memberIds = null): bool
    {
        $memberIds ??= $team->activeUsers()->pluck('users.id')->all();

        return $check->participants->every(fn ($p) => $p->character !== null && $p->owner_id !== $team->user_id
            && $p->character->user_id === $p->owner_id && in_array($p->owner_id, $memberIds, true));
    }

    private function locked(User $actor, int $checkId, callable $action): RpgCheck
    {
        return DB::transaction(function () use ($actor, $checkId, $action): RpgCheck {
            $team = $this->access->team(lock: true);
            abort_unless($team && $this->access->isMember($actor->id, $team), 403);
            $reference = RpgCheck::with(['batch', 'participants'])->findOrFail($checkId);
            Gate::forUser($actor)->authorize('view', $reference);
            RpgCharacter::whereIn('id', $reference->participants->pluck('rpg_character_id')->filter())->orderBy('id')->lockForUpdate()->get();
            $check = RpgCheck::lockForUpdate()->findOrFail($checkId);
            $check->setRelation('participants', $check->participants()->lockForUpdate()->with('character')->get());
            $check->load('batch');
            $action($check, $team);

            return $check->fresh(['batch', 'participants.character']);
        }, 3);
    }

    public function roll(User $actor, int $checkId, int $participantId): RpgCheck
    {
        return $this->locked($actor, $checkId, function (RpgCheck $check, Team $team) use ($actor, $participantId): void {
            $participant = $check->participants->firstWhere('id', $participantId);
            abort_unless($participant && $participant->owner_id === $actor->id && $team->user_id !== $actor->id, 403);
            // Historical retries remain readable, but never authorize a new roll.
            if ($participant->rolled_at) {
                return;
            }
            RpgCheckInput::ensure($check->status === 'pending' && $this->executable($check, $team), 'Diese Probe kann nicht mehr gewürfelt werden.');
            $participant->update($this->calculator->roll($participant->base_total, $this->dice->roll())
                + ['rolled_by' => $actor->id, 'rolled_at' => now()]);
            if ($check->mode === 'fixed') {
                $margin = $participant->total - $check->difficulty;
                $participant->update(['margin' => $margin, 'result_kind' => $this->calculator->result($participant->raw_total, $margin)]);
                $check->update(['status' => 'completed', 'completed_at' => now()]);
            } elseif ($check->participants->every(fn ($p) => $p->rolled_at !== null)) {
                [$first, $second] = $check->participants->all();
                $difference = $first->total - $second->total;
                $check->update(['comparison_margin' => $difference, 'winner_position' => $difference === 0 ? null : ($difference > 0 ? 1 : 2),
                    'status' => $difference === 0 ? 'awaiting_decision' : 'completed', 'completed_at' => $difference === 0 ? null : now()]);
                if ($difference !== 0) {
                    foreach ($check->participants as $p) {
                        $margin = $p->position === 1 ? $difference : -$difference;
                        $p->update(['margin' => $margin, 'result_kind' => $this->calculator->result($p->raw_total, $margin)]);
                    }
                }
            }
        });
    }

    public function cancel(User $actor, int $checkId, string $reason): RpgCheck
    {
        $reason = $this->reason($reason);

        return $this->locked($actor, $checkId, function (RpgCheck $check) use ($actor, $reason): void {
            Gate::forUser($actor)->authorize('manage', $check);
            if ($check->status === 'cancelled') {
                RpgCheckInput::ensure($check->cancellation_reason === $reason, 'Die Probe wurde bereits mit anderer Begründung storniert.');

                return;
            }
            RpgCheckInput::ensure($check->isOpen(), 'Abgeschlossene Proben können nicht storniert werden.');
            $check->update(['status' => 'cancelled', 'cancelled_by' => $actor->id, 'cancellation_reason' => $reason, 'cancelled_at' => now()]);
        });
    }

    public function resolve(User $actor, int $checkId, string $resolution, string $reason): RpgCheck
    {
        Validator::make(['resolution' => $resolution], ['resolution' => ['required', Rule::in(['side_1', 'side_2', 'neutral'])]])->validate();
        $reason = $this->reason($reason);

        return $this->locked($actor, $checkId, function (RpgCheck $check, Team $team) use ($actor, $resolution, $reason): void {
            Gate::forUser($actor)->authorize('manage', $check);
            if ($check->resolved_at) {
                RpgCheckInput::ensure($check->resolution === $resolution && $check->resolution_reason === $reason, 'Der Gleichstand wurde bereits anders entschieden.');

                return;
            }
            RpgCheckInput::ensure($check->status === 'awaiting_decision' && $this->executable($check, $team), 'Es liegt kein entscheidbarer Gleichstand vor.');
            $check->update(['resolution' => $resolution, 'resolution_reason' => $reason, 'resolved_by' => $actor->id,
                'resolved_at' => now(), 'status' => 'completed', 'completed_at' => now(),
                'winner_position' => match ($resolution) {
                    'side_1' => 1, 'side_2' => 2, default => null
                }]);
        });
    }

    private function reason(string $reason): string
    {
        $data = Validator::make(['reason' => trim($reason)], ['reason' => ['required', 'string', 'max:2000']])->validate();

        return $data['reason'];
    }
}
