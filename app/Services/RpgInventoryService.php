<?php

namespace App\Services;

use App\Models\RpgAdvancementRequest;
use App\Models\RpgCharacter;
use App\Models\RpgInventoryTransaction;
use App\Models\User;
use App\Support\RpgInventoryCatalog;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class RpgInventoryService
{
    public function __construct(private RpgAccess $access, private RpgInventoryAdapter $adapter, private RpgInventoryEvaluator $evaluator) {}

    public function preview(User $actor, int $characterId, array $input): array
    {
        $data = $this->validated($input);

        return DB::transaction(function () use ($actor, $characterId, $data): array {
            $this->access->requireLeader($actor, lock: true);
            $this->refreshCatalog($data);
            $characters = $this->characters($actor, $characterId, $data);

            return $this->calculate($characters, $characterId, $data);
        }, 3);
    }

    public function book(User $actor, int $characterId, array $input): RpgInventoryTransaction
    {
        $data = $this->validated($input);
        RpgInventoryAdapter::ensure(is_string($data['preview_hash']) && $data['preview_hash'] !== '', 'Bitte zuerst die Vorschau prüfen.');

        return DB::transaction(function () use ($actor, $characterId, $data): RpgInventoryTransaction {
            $team = $this->access->requireLeader($actor, lock: true);
            $characters = $this->characters($actor, $characterId, $data);
            $hash = $this->hash([$characterId, $data]);
            $existing = RpgInventoryTransaction::where('submission_key', $data['submission_key'])->first();
            if ($existing) {
                abort_unless($existing->actor_id === $actor->id && $existing->entries()->where('rpg_character_id', $characterId)->exists(), 403);
                RpgInventoryAdapter::ensure($existing->submission_hash === $hash, 'Dieser Buchungsschlüssel wurde bereits mit anderen Angaben verwendet.');

                return $existing;
            }
            $this->refreshCatalog($data);
            $result = $this->calculate($characters, $characterId, $data);
            RpgInventoryAdapter::ensure(hash_equals($result['preview_hash'], $data['preview_hash']), 'Die Vorschau ist nicht mehr aktuell. Bitte erneut prüfen.');
            $transaction = RpgInventoryTransaction::create([
                'team_id' => $team->id, 'actor_id' => $actor->id, 'submission_key' => $data['submission_key'],
                'submission_hash' => $hash, 'kind' => $data['kind'], 'reason' => $data['reason'],
                'occurred_on' => $data['occurred_on'], 'created_at' => now(), 'catalog_version' => $this->catalogVersion(),
                'reversal_of' => $data['reversal_of'],
                'details' => ['price' => $result['price'], 'price_reason' => $data['price_reason'], 'external_party' => $data['external_party'],
                    'trade_values' => $result['trade_values'], 'winner' => $data['winner'], 'success_points' => $data['success_points'],
                    'minutes' => $data['minutes'], 'entry_count' => count($result['entries'])],
            ]);
            foreach ($result['entries'] as $id => $entry) {
                $character = $characters[$id];
                $payload = $character->payload;
                $payload['equipment'] = $this->adapter->equipment($entry['after'], $payload['equipment'] ?? []);
                $payload['equipment']['stock_confirmed_at'] ??= now()->toIso8601String();
                $payload['character']['equipment'] = $entry['after']['notes'];
                $this->record($transaction, $character, $entry);
                $character->payload = $payload;
                $character->revision++;
                $character->save();
                $character->advancements()->where('status', 'pending')->orderBy('id')->lockForUpdate()->get()->each->update([
                    'status' => 'superseded', 'decided_at' => now(), 'reviewer_id' => $actor->id,
                    'decision_reason' => 'Durch Inventarbuchung #'.$transaction->id.' überholt. Bitte auf dem aktuellen Stand erneut prüfen.',
                ]);
            }

            return $transaction;
        }, 3);
    }

    public function recordAdvancement(User $actor, RpgCharacter $character, int $requestId, array $beforePayload, array $afterPayload): void
    {
        $before = $this->adapter->state($beforePayload);
        $after = $this->adapter->state($afterPayload);
        $changes = $this->evaluator->changes($before, $after);
        if (($beforePayload['equipment'] ?? []) === ($afterPayload['equipment'] ?? [])) {
            return;
        }
        $transaction = RpgInventoryTransaction::create([
            'team_id' => $this->access->team()->id, 'actor_id' => $actor->id, 'submission_key' => (string) Str::uuid(),
            'submission_hash' => $this->hash([$requestId, $changes]), 'kind' => 'advancement', 'reason' => 'Genehmigter High-Tech-Vorteil, Antrag #'.$requestId,
            'occurred_on' => now()->timezone('Europe/Berlin')->toDateString(), 'created_at' => now(),
            'catalog_version' => $this->catalogVersion(), 'advancement_id' => $requestId, 'details' => ['entry_count' => 1],
        ]);
        $this->record($transaction, $character, compact('before', 'after', 'changes'));
    }

    private function record(RpgInventoryTransaction $transaction, RpgCharacter $character, array $entry): void
    {
        $transaction->entries()->create(['rpg_character_id' => $character->id, 'revision_before' => $character->revision,
            'revision_after' => $character->revision + 1, 'before_state' => $entry['before'], 'after_state' => $entry['after'], 'changes' => $entry['changes']]);
    }

    private function characters(User $actor, int $id, array $data): Collection
    {
        $ids = [$id];
        if ($data['reversal_of'] !== null) {
            $original = RpgInventoryTransaction::with('entries')->findOrFail($data['reversal_of']);
            abort_unless($original->entries->contains('rpg_character_id', $id), 403);
            RpgInventoryAdapter::ensure(count($original->entries) === ($original->details['entry_count'] ?? 0), 'Eine beteiligte Figur wurde gelöscht; bitte eine begründete Korrektur buchen.');
            $ids = $original->entries->pluck('rpg_character_id')->all();
        } elseif ($data['other_character_id'] !== null) {
            RpgInventoryAdapter::ensure($data['other_character_id'] !== $id, 'Ein Charakter kann nicht mit sich selbst handeln.');
            $ids[] = $data['other_character_id'];
        }
        $characters = RpgCharacter::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($characters->count() !== count($ids)) {
            throw (new ModelNotFoundException)->setModel(RpgCharacter::class, array_diff($ids, $characters->keys()->all()));
        }
        foreach ($characters as $character) {
            Gate::forUser($actor)->authorize('manageInventory', $character);
        }

        return $characters;
    }

    private function calculate(Collection $characters, int $id, array $data): array
    {
        RpgInventoryAdapter::ensure($characters[$id]->revision === $data['revision'], 'Der Charakter wurde inzwischen geändert. Bitte die Seite neu öffnen.');
        $entries = [];
        $price = null;
        $tradeValues = null;
        if ($data['kind'] === 'reversal') {
            $original = RpgInventoryTransaction::with(['entries', 'reversal'])->findOrFail($data['reversal_of']);
            RpgInventoryAdapter::ensure($original->reversal === null && ! in_array($original->kind, ['initialize', 'advancement', 'reversal'], true), 'Diese Buchung kann nicht zurückgebucht werden. Bitte eine Bestandskorrektur verwenden.');
            foreach ($original->entries as $entry) {
                $character = $characters[$entry->rpg_character_id];
                if ($character->id !== $id) {
                    RpgInventoryAdapter::ensure($character->revision === $data['other_revision'], 'Die Gegenpartei wurde inzwischen geändert. Bitte neu laden.');
                }
                $entries[$character->id] = $this->evaluator->reverse($this->adapter->state($character->payload), $entry->before_state, $entry->after_state);
            }
        } else {
            $other = $data['other_character_id'] !== null ? $characters[$data['other_character_id']] : null;
            if ($other) {
                RpgInventoryAdapter::ensure($other->revision === $data['other_revision'], 'Die Gegenpartei wurde inzwischen geändert. Bitte neu laden.');
            }
            $result = $this->evaluator->evaluate($characters[$id]->payload, $data, $other?->payload);
            $entries[$id] = array_intersect_key($result, array_flip(['before', 'after', 'changes']));
            if ($other) {
                $entries[$other->id] = $result['other'];
            }
            $price = $result['price'];
            $tradeValues = $result['trade_values'];
        }
        ksort($entries);
        $result = ['entries' => $entries, 'price' => $price, 'trade_values' => $tradeValues,
            'superseded_requests' => RpgAdvancementRequest::whereIn('rpg_character_id', $characters->keys())->where('status', 'pending')->orderBy('id')->pluck('id')->all()];
        $result['preview_hash'] = $this->hash([$result, array_diff_key($data, ['preview_hash' => true])]);

        return $result;
    }

    private function catalogVersion(): string
    {
        return app(RpgEquipmentCatalogResolver::class)->resolve()->version();
    }

    private function refreshCatalog(array $data): void
    {
        $catalog = app(RpgEquipmentCatalogResolver::class)->resolve();
        RpgInventoryAdapter::ensure($catalog->version() === $data['catalog_version'], 'Der Katalog wurde inzwischen geaendert. Bitte neu laden.');
        $this->adapter->useCatalog($catalog);
        $this->evaluator->useCatalog($catalog);
    }

    private function hash(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function validated(array $input): array
    {
        $rules = [
            'submission_key' => ['required', 'uuid'], 'revision' => ['required', 'integer', 'min:0'],
            'catalog_version' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::in(array_diff(array_keys(RpgInventoryTransaction::kinds()), ['advancement']))],
            'reason' => ['required', 'string', 'max:4000'], 'occurred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now()->timezone('Europe/Berlin')->toDateString()],
            'preview_hash' => ['nullable', 'string', 'size:64'], 'other_character_id' => ['nullable', 'integer', 'min:1'],
            'other_revision' => ['nullable', 'integer', 'min:0'], 'external_party' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'integer', 'between:0,'.RpgInventoryCatalog::MONEY_MAX], 'price_reason' => ['nullable', 'string', 'max:4000'],
            'cash_direction' => ['sometimes', Rule::in(['income', 'expense'])], 'winner' => ['sometimes', Rule::in(['none', 'buyer', 'seller'])],
            'success_points' => ['sometimes', 'integer', 'between:0,1000'], 'minutes' => ['sometimes', 'integer', 'between:0,1000000'],
            'metadata' => ['sometimes', 'array:clothing,armor,shield,notes'], 'metadata.*' => ['nullable', 'string', 'max:4000'],
            'reversal_of' => ['nullable', 'integer', 'min:1'],
            'version_change' => ['sometimes', 'array:source,target,quantity'],
            'version_change.source' => ['required_with:version_change', 'string', 'max:255'],
            'version_change.target' => ['required_with:version_change', 'string', 'max:255'],
            'version_change.quantity' => ['required_with:version_change', 'integer', 'between:1,20'],
        ];
        foreach (['incoming', 'outgoing', 'set'] as $field) {
            $rules[$field] = ['sometimes', 'array', 'max:128'];
            $rules[$field.'.*'] = ['required', 'array:id,quantity'];
            $rules[$field.'.*.id'] = ['required', 'string', 'max:255', 'distinct'];
            $rules[$field.'.*.quantity'] = ['required', 'integer', 'between:0,1000000'];
        }
        RpgInventoryAdapter::ensure(array_diff(array_keys($input), array_filter(array_keys($rules), fn ($key) => ! str_contains($key, '.'))) === [], 'Die Anfrage enthält unzulässige Felder.');
        $data = Validator::make($input, $rules)->validate();
        $data = array_replace(['preview_hash' => null, 'other_character_id' => null, 'other_revision' => null,
            'external_party' => '', 'amount' => null, 'price_reason' => '', 'cash_direction' => 'income', 'winner' => 'none',
            'success_points' => 0, 'minutes' => 0, 'metadata' => [], 'version_change' => [], 'incoming' => [], 'outgoing' => [], 'set' => [], 'reversal_of' => null], $data);
        foreach (['reason', 'external_party', 'price_reason'] as $field) {
            $data[$field] = trim($data[$field] ?? '');
        }
        RpgInventoryAdapter::ensure($data['reason'] !== '', 'Bitte den Vorgang aus der Handlung begründen.');
        foreach (['revision', 'other_character_id', 'other_revision', 'amount', 'success_points', 'minutes', 'reversal_of'] as $key) {
            $data[$key] = $data[$key] === null ? null : (int) $data[$key];
        }
        foreach (['incoming', 'outgoing', 'set'] as $key) {
            $data[$key] = array_map(fn ($row) => ['id' => $row['id'], 'quantity' => (int) $row['quantity']], $data[$key]);
        }
        if ($data['version_change'] !== []) {
            $data['version_change']['quantity'] = (int) $data['version_change']['quantity'];
        }
        $data['metadata'] = array_map(fn ($value) => trim($value ?? ''), $data['metadata']);
        RpgInventoryAdapter::ensure(($data['kind'] === 'reversal') === ($data['reversal_of'] !== null), 'Unzulässiger Rückbuchungsbezug.');
        RpgInventoryAdapter::ensure($data['other_character_id'] === null || in_array($data['kind'], ['purchase', 'sale', 'swap', 'reversal'], true), 'Eine Gegenpartei ist nur beim Handel möglich.');
        RpgInventoryAdapter::ensure($data['other_character_id'] === null || $data['external_party'] === '', 'Bitte entweder einen Charakter oder eine externe Gegenpartei wählen.');
        if ($data['kind'] === 'reversal') {
            RpgInventoryAdapter::ensure($data['incoming'] === [] && $data['outgoing'] === [] && $data['set'] === [] && $data['version_change'] === []
                && $data['metadata'] === [] && $data['amount'] === null && $data['minutes'] === 0
                && $data['external_party'] === '' && $data['price_reason'] === '' && $data['winner'] === 'none'
                && $data['success_points'] === 0, 'Eine Rückbuchung darf keine zusätzlichen Bestands- oder Preisänderungen enthalten.');
        }

        return $data;
    }
}
