<?php

namespace App\Services;

use App\Models\RpgAmmunitionProfile;
use App\Models\RpgItemType;
use App\Support\RpgEquipmentCatalog;
use Illuminate\Support\Facades\Schema;

final class RpgEquipmentCatalogResolver
{
    public function resolve(): RpgEquipmentCatalog
    {
        if (! Schema::hasTable('rpg_item_types') || ! ($team = app(RpgAccess::class)->team())) {
            return new RpgEquipmentCatalog;
        }
        $types = RpgItemType::with('versions')->where('team_id', $team->id)->orderBy('id')->get();
        $custom = [];
        $revision = [];
        foreach ($types as $type) {
            $revision[] = [$type->id, $type->revision, $type->current_version, $type->starting_equipment, $type->archived];
            foreach ($type->versions as $version) {
                $custom[$version->reference] = array_replace($version->definition, ['name' => $version->definition['name'].' (Version '.$version->version.')']) + ['id' => $version->reference, 'type_id' => $type->id,
                    'version' => $version->version, 'available' => ! $type->archived && $type->current_version === $version->version,
                    'starting_equipment' => $type->starting_equipment];
            }
        }
        $ammo = RpgAmmunitionProfile::where('team_id', $team->id)->orderBy('id')->get()->mapWithKeys(fn ($row) => [$row->reference => ['name' => $row->name, 'unit' => $row->unit]])->all();

        return new RpgEquipmentCatalog($custom, $ammo, $revision === [] ? '' : json_encode([$revision, $ammo], JSON_THROW_ON_ERROR));
    }
}
