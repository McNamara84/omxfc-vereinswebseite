<?php

declare(strict_types=1);

namespace Tests\Unit;

trait RpgItemCreationRulesTestFixtures
{
    private function input(array $changes = []): array
    {
        return array_replace(['name' => 'Ruinenschwert', 'description' => 'Eine alte Klinge.', 'category' => 'melee_weapons', 'size' => 'medium', 'rarity' => 'common', 'bucks' => 100, 'reference' => 'Schwert auf S. 41', 'hands' => 1], $changes);
    }
}
