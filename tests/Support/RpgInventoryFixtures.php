<?php

namespace Tests\Support;

use App\Models\RpgCharacter;
use App\Models\RpgInventoryTransaction;
use App\Services\RpgEquipmentCatalogResolver;
use App\Services\RpgInventoryService;
use Illuminate\Support\Str;

trait RpgInventoryFixtures
{
    use RpgProgressionFixtures;

    protected function inventoryInput(string $kind = 'grant', array $overrides = [], ?RpgCharacter $character = null): array
    {
        $character ??= $this->character;

        return array_replace(['submission_key' => (string) Str::uuid(), 'revision' => $character->fresh()->revision,
            'catalog_version' => app(RpgEquipmentCatalogResolver::class)->resolve()->version(), 'kind' => $kind, 'reason' => 'Im Abenteuer ausgespielt.',
            'occurred_on' => now()->timezone('Europe/Berlin')->toDateString()], $overrides);
    }

    protected function inventoryBook(string $kind, array $overrides = [], ?RpgCharacter $character = null): RpgInventoryTransaction
    {
        $character ??= $this->character;
        $input = $this->inventoryInput($kind, $overrides, $character);
        $service = app(RpgInventoryService::class);
        $preview = $service->preview($this->leader, $character->id, $input);

        return $service->book($this->leader, $character->id, $input + ['preview_hash' => $preview['preview_hash']]);
    }

    protected function inventoryInitialize(int $bucks = 100, array $set = [], ?RpgCharacter $character = null): void
    {
        $this->inventoryBook('initialize', ['amount' => $bucks, 'set' => $set], $character);
    }

    protected function position(string $id, int $quantity = 1): array
    {
        return compact('id', 'quantity');
    }
}
