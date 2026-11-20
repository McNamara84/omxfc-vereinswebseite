<?php

namespace App\Services;

use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\RpgCharacter;
use App\Models\RpgCharacterRename;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class RpgCharacterRenameService
{
    public const string REWARD_SLUG = 'rpg-character-rename';

    public function __construct(private RpgAccess $access, private RewardService $rewards) {}

    public function costBaxx(): int
    {
        return max(1, (int) config('rewards.rpg_character_rename_cost_baxx', 25));
    }

    /** @param array<string, mixed> $input */
    public function rename(User $actor, int $characterId, array $input): ?RpgCharacterRename
    {
        $this->ensure(array_diff(array_keys($input), ['character_name', 'revision', 'submission_key']) === [], 'Unzulässige Angaben zur Umbenennung.');
        if (is_string($input['character_name'] ?? null)) {
            $input['character_name'] = Str::trim($input['character_name']);
        }
        $data = Validator::make($input, [
            'character_name' => ['required', 'string', 'max:255'],
            'revision' => ['required', 'integer', 'min:0'],
            'submission_key' => ['required', 'uuid'],
        ], [
            'character_name.required' => 'Bitte gib einen neuen Charakternamen ein.',
            'character_name.string' => 'Der Charaktername muss ein Text sein.',
            'character_name.max' => 'Der Charaktername darf maximal 255 Zeichen lang sein.',
        ])->validate();
        $data['revision'] = (int) $data['revision'];
        $data['submission_key'] = strtolower($data['submission_key']);
        $hash = hash('sha256', json_encode([$actor->id, $characterId, $data], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $characterId, $data, $hash): ?RpgCharacterRename {
            // Match character creation with slot purchase: AG -> wallet user -> character.
            // Do not establish a consistent-read snapshot before the wallet user lock.
            $this->access->team(lock: true);
            $user = User::lockForUpdate()->findOrFail($actor->id);
            $character = RpgCharacter::lockForUpdate()->findOrFail($characterId);
            Gate::forUser($user)->authorize('rename', $character);

            $existing = RpgCharacterRename::where('submission_key', $data['submission_key'])->lockForUpdate()->first();
            if ($existing) {
                abort_unless($existing->user_id === $user->id && $existing->rpg_character_id === $characterId, 403);
                $this->ensure(hash_equals($existing->submission_hash, $hash), 'Dieser Vorgang wurde bereits mit anderen Angaben ausgeführt.');

                return $existing->load('purchase');
            }

            $this->ensure($character->revision === $data['revision'], 'Der Charakter wurde inzwischen geändert. Bitte prüfe den aktuellen Stand und bestätige die Umbenennung erneut.');
            if ($character->character_name === $data['character_name']) {
                return null;
            }
            $this->ensure(! $character->advancements()->where('status', 'pending')->exists(), 'Für diesen Charakter liegt ein offener Verbesserungsantrag vor. Schließe ihn zuerst ab oder ziehe ihn zurück.');

            $wallet = $this->rewards->getWalletState($user);
            $this->ensure(is_int($wallet['availableBaxx']), $wallet['warning'] ?? 'Das Baxx-Guthaben ist aktuell nicht verfügbar.');
            $cost = $this->costBaxx();
            $this->ensure($wallet['availableBaxx'] >= $cost, "Du benötigst {$cost} Baxx, hast aber nur {$wallet['availableBaxx']} verfügbar.");
            $reward = Reward::where('slug', self::REWARD_SLUG)->first();
            $this->ensure($reward !== null, 'Das Umbenennen ist derzeit nicht verfügbar. Bitte versuche es später erneut.');

            $purchase = RewardPurchase::create([
                'user_id' => $user->id, 'reward_id' => $reward->id,
                'wallet_team_id' => Team::membersTeam()->id, 'cost_baxx' => $cost, 'purchased_at' => now(),
            ]);
            $oldName = $character->character_name;
            $payload = $character->payload;
            $payload['character']['character_name'] = $data['character_name'];
            $character->character_name = $data['character_name'];
            $character->payload = $payload;
            $character->revision++;
            $character->save();

            return $character->renames()->create([
                'user_id' => $user->id, 'reward_purchase_id' => $purchase->id,
                'old_name' => $oldName, 'new_name' => $data['character_name'],
                'revision_before' => $data['revision'], 'revision_after' => $character->revision,
                'submission_key' => $data['submission_key'], 'submission_hash' => $hash,
            ])->setRelation('purchase', $purchase);
        }, 3);
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['character_name' => $message]);
        }
    }
}
