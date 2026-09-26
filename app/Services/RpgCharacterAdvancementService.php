<?php

namespace App\Services;

use App\Models\RpgAdvancementRequest;
use App\Models\RpgCharacter;
use App\Models\User;
use App\Support\RpgExperienceRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class RpgCharacterAdvancementService
{
    public function __construct(private RpgAccess $access, private RpgCharacterAdvancementEvaluator $evaluator) {}

    public function preview(User $actor, RpgCharacter $character, array $input): array
    {
        Gate::forUser($actor)->authorize('improve', $character);
        $data = $this->validated($input);
        $this->ensure((int) $data['revision'] === $character->revision, 'Der Charakter wurde inzwischen geändert. Bitte die Seite neu öffnen.');
        $result = $this->evaluator->evaluate($character->payload, $data['operations']);
        $balance = $character->experienceBalance();
        $this->ensure($result['cost'] <= $balance, 'Die verfügbaren Erfahrungspunkte reichen nicht aus.');

        return $result + ['balance' => $balance, 'remaining' => $balance - $result['cost']];
    }

    public function submit(User $actor, int $characterId, array $input): RpgAdvancementRequest
    {
        $data = $this->validated($input);

        return DB::transaction(function () use ($actor, $characterId, $data): RpgAdvancementRequest {
            $this->access->team(lock: true);
            $character = RpgCharacter::lockForUpdate()->findOrFail($characterId);
            Gate::forUser($actor)->authorize('improve', $character);
            $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $existing = RpgAdvancementRequest::where('submission_key', $data['submission_key'])->first();
            if ($existing) {
                abort_unless($existing->user_id === $actor->id && $existing->rpg_character_id === $characterId, 403);
                $this->ensure($existing->submission_hash === $hash, 'Dieser Antrag wurde bereits mit anderen Angaben eingereicht.');

                return $existing;
            }
            $this->ensure(! $character->advancements()->where('status', 'pending')->exists(), 'Für diesen Charakter liegt bereits ein offener Antrag vor.');
            $result = $this->preview($actor, $character, $data);

            return $character->advancements()->create([
                'user_id' => $actor->id, 'submission_key' => $data['submission_key'], 'submission_hash' => $hash,
                'status' => 'pending', 'revision' => $character->revision, 'rule_version' => RpgExperienceRules::VERSION,
                'operations' => $data['operations'], 'changes' => $result['changes'],
                'before_payload' => $character->payload, 'after_payload' => $result['payload'], 'cost' => $result['cost'],
            ]);
        }, 3);
    }

    public function decide(User $actor, int $requestId, string $decision, ?string $reason = null): RpgAdvancementRequest
    {
        abort_unless(in_array($decision, ['approved', 'rejected', 'withdrawn'], true), 422);

        return DB::transaction(function () use ($actor, $requestId, $decision, $reason): RpgAdvancementRequest {
            $team = $decision === 'withdrawn' ? $this->access->team(lock: true) : $this->access->requireLeader($actor, lock: true);
            $reference = RpgAdvancementRequest::findOrFail($requestId);
            $character = RpgCharacter::lockForUpdate()->findOrFail($reference->rpg_character_id);
            $request = RpgAdvancementRequest::lockForUpdate()->findOrFail($requestId);
            if ($decision === 'withdrawn') {
                Gate::forUser($actor)->authorize('improve', $character);
            }
            if ($request->status === $decision) {
                return $request;
            }
            $this->ensure($request->status === 'pending', 'Über diesen Antrag wurde bereits entschieden.');
            if ($decision === 'approved') {
                $this->ensure($this->access->isMember($character->user_id, $team), 'Der Besitzer ist nicht mehr Mitglied der AG Rollenspiel.');
                $this->ensure($request->revision === $character->revision && $request->before_payload === $character->payload, 'Der Charakter wurde inzwischen geändert. Bitte einen neuen Antrag einreichen.');
                $this->ensure($request->rule_version === RpgExperienceRules::VERSION, 'Die Regelversion hat sich geändert. Bitte einen neuen Antrag einreichen.');
                $result = $this->evaluator->evaluate($character->payload, $request->operations);
                $this->ensure($result['cost'] === $request->cost && $result['payload'] === $request->after_payload, 'Die Vorschau ist nicht mehr aktuell. Bitte den Antrag erneuern.');
                $this->ensure($character->experienceBalance() >= $result['cost'], 'Die verfügbaren Erfahrungspunkte reichen nicht aus.');
                $character->experienceEntries()->create(['rpg_advancement_request_id' => $request->id, 'amount' => -$result['cost']]);
                $character->payload = $result['payload'];
                $character->revision++;
                $character->save();
            }
            if ($decision === 'rejected') {
                Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:4000']])->validate();
                $this->ensure(trim($reason ?? '') !== '', 'Bitte die Ablehnung begründen.');
            }
            $request->update([
                'status' => $decision, 'reviewer_id' => $actor->id, 'decided_at' => now(),
                'decision_reason' => $decision === 'rejected' ? trim($reason) : null,
            ]);

            return $request;
        }, 3);
    }

    private function validated(array $input): array
    {
        return Validator::make($input, [
            'submission_key' => ['required', 'uuid'],
            'revision' => ['required', 'integer', 'min:0'],
            'operations' => ['required', 'array', 'min:1', 'max:30'],
        ])->validate();
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['operations' => $message]);
        }
    }
}
