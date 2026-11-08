<?php

namespace App\Services;

use App\Models\RpgAmmunitionProfile;
use App\Models\RpgItemType;
use App\Models\RpgItemVersion;
use App\Models\Team;
use App\Models\User;
use App\Support\CombatHash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class RpgItemService
{
    public function __construct(private RpgAccess $access, private RpgItemCreationEvaluator $evaluator, private RpgEquipmentCatalogResolver $catalogs) {}

    public function preview(User $actor, array $input, ?int $typeId = null): array
    {
        return DB::transaction(function () use ($actor, $input, $typeId): array {
            $team = $this->access->requireLeader($actor, lock: true);
            $data = $this->validated($input);
            $type = $this->type($actor, $typeId, $data['revision']);

            return $this->evaluatePreview($team, $type, $data);
        }, 3);
    }

    public function save(User $actor, array $input, ?int $typeId = null): RpgItemVersion
    {
        return DB::transaction(function () use ($actor, $input, $typeId): RpgItemVersion {
            $team = $this->access->requireLeader($actor, lock: true);
            $data = $this->validated($input);
            $hash = CombatHash::make([$typeId, $data['revision'], $data['input']]);
            $existing = RpgItemVersion::where('submission_key', $data['submission_key'])->first();
            if ($existing) {
                $this->ensure($existing->created_by === $actor->id && $existing->submission_hash === $hash && $existing->itemType->team_id === $team->id, 'Dieser Übermittlungsschlüssel wurde bereits anders verwendet.');

                return $existing;
            }
            $type = $this->type($actor, $typeId, $data['revision']);
            $result = $this->evaluatePreview($team, $type, $data);
            $this->ensure(is_string($data['preview_hash']) && hash_equals($result['preview_hash'], $data['preview_hash']), 'Bitte eine aktuelle Vorschau prüfen.');
            $type ??= RpgItemType::create(['team_id' => $team->id, 'public_id' => (string) Str::uuid(),
                'created_by' => $actor->id, 'category' => $result['definition']['category']]);
            $version = $typeId ? $type->current_version + 1 : 1;
            $definition = $result['definition'];
            if (isset($definition['ammunitionProfile']) && $definition['ammunitionProfile']['id'] === '') {
                $profile = RpgAmmunitionProfile::create(['team_id' => $team->id, 'reference' => 'ammo:custom-'.Str::uuid(),
                    'name' => $definition['ammunitionProfile']['name'], 'unit' => $definition['ammunitionProfile']['unit']]);
                $definition['ammunitionProfile']['id'] = $profile->reference;
            }
            $type->update(['current_version' => $version, 'revision' => $type->revision + 1]);

            return $type->versions()->create(['version' => $version, 'reference' => 'custom-'.$type->public_id.'-v'.$version,
                'definition' => $definition, 'input' => $data['input'], 'calculation' => $result['calculation'], 'rule_version' => $result['rule_version'],
                'created_by' => $actor->id, 'submission_key' => $data['submission_key'], 'submission_hash' => $hash, 'created_at' => now()]);
        }, 3);
    }

    public function configure(User $actor, int $typeId, array $input): void
    {
        $data = Validator::make($input, ['revision' => 'required|integer|min:0', 'starting_equipment' => 'sometimes|boolean', 'archived' => 'sometimes|boolean'])->validate();
        $this->ensure(array_diff(array_keys($input), ['revision', 'starting_equipment', 'archived']) === [] && count($data) > 1, 'Unzulässige Freigabeänderung.');
        DB::transaction(function () use ($actor, $typeId, $data): void {
            $this->access->requireLeader($actor, lock: true);
            $type = $this->type($actor, $typeId, (int) $data['revision']);
            $type->update(array_diff_key($data, ['revision' => true]) + ['revision' => $type->revision + 1]);
        }, 3);
    }

    private function evaluatePreview(Team $team, ?RpgItemType $type, array $data): array
    {
        $this->ensure(! $type || ! $type->archived, 'Archivierte Gegenstände zuerst wieder aktivieren.');
        try {
            $result = $this->evaluator->evaluate($data['input']);
        } catch (InvalidArgumentException $error) {
            throw ValidationException::withMessages(['input' => $error->getMessage()]);
        }
        $this->ensure(! $type || $type->category === $result['definition']['category'], 'Die Kategorie eines Gegenstandstyps darf nicht geändert werden.');
        $ammo = $result['definition']['ammunitionProfile'] ?? null;
        if ($ammo && $ammo['id'] !== '') {
            $this->ensure(isset($this->catalogs->resolve()->ammunition()[$ammo['id']]), 'Unbekanntes oder fremdes Munitionsprofil.');
        }
        $result['preview_hash'] = CombatHash::make([$team->id, $type?->id, $data['revision'], $data['input'], $result]);

        return $result;
    }

    private function type(User $actor, ?int $id, int $revision): ?RpgItemType
    {
        if (! $id) {
            $this->ensure($revision === 0, 'Ein neuer Gegenstand beginnt mit Revision 0.');

            return null;
        }
        $type = RpgItemType::lockForUpdate()->findOrFail($id);
        Gate::forUser($actor)->authorize('update', $type);
        $this->ensure($type->revision === $revision, 'Der Gegenstand wurde inzwischen geändert. Bitte neu laden.');

        return $type;
    }

    private function validated(array $input): array
    {
        $this->ensure(array_diff(array_keys($input), ['input', 'revision', 'submission_key', 'preview_hash']) === [], 'Die Anfrage enthält unzulässige Felder.');
        $data = Validator::make($input, ['input' => 'required|array', 'revision' => 'required|integer|min:0', 'submission_key' => 'required|uuid', 'preview_hash' => 'nullable|string|size:64'])->validate();
        $data['revision'] = (int) $data['revision'];

        return $data + ['preview_hash' => null];
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['item' => $message]);
        }
    }
}
