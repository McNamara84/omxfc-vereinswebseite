<?php

namespace Tests\Support;

use App\Services\RpgEquipmentCatalogResolver;

trait RpgItemCreationFixtures
{
    protected function creationInput(string $reference): array
    {
        return ['figurenstaerke' => 3, 'player_name' => 'Spieler', 'character_name' => 'Neuer Held', 'gender' => 'maennlich',
            'race' => 'Barbar', 'culture' => 'Landbewohner', 'barbar_attribute_bonus' => 'st', 'description' => 'Ein erfahrener Held.',
            'attributes' => ['st' => 2, 'ge' => 1],
            'attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0],
            'skills' => [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Ueberleben', 'value' => 1], ['name' => 'Athletik', 'value' => 4],
                ['name' => 'Intuition', 'value' => 1], ['name' => 'Beruf: Viehzuechter', 'value' => 2], ['name' => 'Kunde: Wetter', 'value' => 1],
                ['name' => 'Fernkampf', 'value' => 4], ['name' => 'Handeln', 'value' => 4], ['name' => 'Fahren', 'value' => 4], ['name' => 'Feuerwaffen', 'value' => 4]],
            'advantages' => ['Zaeh'], 'disadvantages' => ['Auffaellig'], 'clothing' => 'kleidung-einfach',
            'equipment_items' => [['id' => $reference, 'quantity' => 1], ['id' => 'seil', 'quantity' => 1], ['id' => 'rucksack', 'quantity' => 1],
                ['id' => 'wasserschlauch', 'quantity' => 1], ['id' => 'wochenration', 'quantity' => 1], ['id' => 'bogen', 'quantity' => 1]],
            'equipment_catalog_version' => app(RpgEquipmentCatalogResolver::class)->resolve()->version()];
    }
}
