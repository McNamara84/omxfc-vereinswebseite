<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RpgInventoryTransaction;
use App\Models\RpgItemType;
use App\Models\RpgItemVersion;
use App\Services\RpgEquipmentCatalogResolver;
use App\Services\RpgItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\RpgInventoryFixtures;

trait RpgItemManagementTestFixtures
{
    use RefreshDatabase, RpgInventoryFixtures, \Tests\Support\RpgItemCreationFixtures;

    private function input(array $changes = []): array
    {
        $definition = array_replace(['name' => 'Ruinenschwert', 'description' => 'Alte Klinge', 'reference' => 'Schwert S. 41', 'category' => 'melee_weapons', 'size' => 'medium', 'rarity' => 'common', 'hands' => 1, 'bucks' => 90], $changes);
        if (! in_array($definition['category'], ['melee_weapons', 'ranged_weapons'], true)) {
            unset($definition['hands']);
        }

        return ['input' => $definition, 'revision' => 0, 'submission_key' => (string) Str::uuid()];
    }

    private function createItem(array $input = [], ?RpgItemType $type = null): RpgItemVersion
    {
        $data = $this->input($input);
        $data['revision'] = $type?->fresh()->revision ?? 0;
        $service = app(RpgItemService::class);
        $preview = $service->preview($this->leader, $data, $type?->id);

        return $service->save($this->leader, $data + ['preview_hash' => $preview['preview_hash']], $type?->id);
    }

    private function bookCustom(string $kind, array $changes): RpgInventoryTransaction
    {
        return $this->inventoryBook($kind, $changes + ['catalog_version' => app(RpgEquipmentCatalogResolver::class)->resolve()->version()]);
    }
}
