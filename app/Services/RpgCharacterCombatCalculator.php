<?php

namespace App\Services;

use App\Support\RpgCharEditorEquipment;

final class RpgCharacterCombatCalculator
{
    private const ATTRIBUTE_LABELS = [
        'st' => 'ST',
        'ge' => 'GE',
        'ro' => 'RO',
        'wi' => 'WI',
        'wa' => 'WA',
        'in' => 'IN',
        'au' => 'AU',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function calculate(array $data): array
    {
        $attributes = $this->attributes($data['attributes'] ?? []);
        $skills = $this->skills($data['skills'] ?? []);
        $advantages = $this->stringList($data['advantages'] ?? []);
        $disadvantages = $this->stringList($data['disadvantages'] ?? []);
        $advantageCounts = is_array($data['advantage_counts'] ?? null) ? $data['advantage_counts'] : [];
        $equipment = is_array($data['equipment'] ?? null) ? $data['equipment'] : [];
        $items = $this->selectedItems($equipment['items'] ?? []);
        $activeArmor = $this->activeItem($items, (string) ($equipment['active_armor_id'] ?? ''), 'armor');
        $activeShield = $this->activeItem($items, (string) ($equipment['active_shield_id'] ?? ''), 'shield');

        $shieldBonus = (int) ($activeShield['combat']['defenseBonus'] ?? 0);
        $coldBloodedBonus = in_array('Kaltblütig', $advantages, true) ? 1 : 0;
        $combatReflexBonus = in_array('Kampfreflexe', $advantages, true) ? 2 : 0;
        $fastBonus = in_array('Schnell', $advantages, true) ? 1 : 0;
        $toughBonus = in_array('Zäh', $advantages, true) ? 1 : 0;
        $armorAdvantageBonus = in_array('Panzerung', $advantages, true)
            ? max(1, (int) ($advantageCounts['Panzerung'] ?? 1))
            : 0;
        $armorProtection = (int) ($activeArmor['combat']['protection'] ?? 0);

        $movement = 4 + $attributes['ge'] + ($fastBonus * 2);

        return [
            'defense' => [
                'parade' => $this->skillValue($skills, 'Nahkampf') + $attributes['ge'] + $coldBloodedBonus + $shieldBonus,
                'dodge' => $this->skillValue($skills, 'Athletik') + $attributes['ge'] + $combatReflexBonus + $coldBloodedBonus + $shieldBonus,
                'damage_reduction' => $attributes['ro'] + $armorProtection + $armorAdvantageBonus + $toughBonus,
                'components' => [
                    'robustness' => $attributes['ro'],
                    'armor' => $armorProtection,
                    'panzerung' => $armorAdvantageBonus,
                    'zaeh' => $toughBonus,
                    'shield' => $shieldBonus,
                    'kampfreflexe' => $combatReflexBonus,
                    'kaltbluetig' => $coldBloodedBonus,
                ],
            ],
            'initiative' => [
                'Nahkampf' => $this->skillValue($skills, 'Nahkampf') + $attributes['wa'] + $fastBonus,
                'Fernkampf' => $this->skillValue($skills, 'Fernkampf') + $attributes['wa'] + $fastBonus,
                'Feuerwaffen' => $this->skillValue($skills, 'Feuerwaffen') + $attributes['wa'] + $fastBonus,
            ],
            'movement' => [
                'base' => $movement,
                'run' => $movement * 4,
            ],
            'weapons' => $this->weapons($items, $attributes, $skills, $advantages),
            'armor' => $this->armorRows($items, (string) ($equipment['active_armor_id'] ?? ''), (string) ($equipment['active_shield_id'] ?? '')),
            'active_armor' => $this->publicItem($activeArmor),
            'active_shield' => $this->publicItem($activeShield),
            'situational_notes' => $this->situationalNotes($advantages, $disadvantages, $data['disadvantage_details'] ?? []),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function attributes(mixed $values): array
    {
        $values = is_array($values) ? $values : [];
        $attributes = [];

        foreach (array_keys(self::ATTRIBUTE_LABELS) as $key) {
            $attributes[$key] = is_numeric($values[$key] ?? null) ? (int) $values[$key] : 0;
        }

        return $attributes;
    }

    /**
     * @return array<string, int>
     */
    private function skills(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $skills = [];

        foreach ($values as $skill) {
            if (! is_array($skill)) {
                continue;
            }

            $name = trim((string) ($skill['name'] ?? ''));

            if ($name === '' || ! is_numeric($skill['value'] ?? null)) {
                continue;
            }

            $value = (int) $skill['value'];
            $skills[$name] = max($skills[$name] ?? PHP_INT_MIN, $value);
        }

        return $skills;
    }

    /**
     * @param  array<string, int>  $skills
     */
    private function skillValue(array $skills, string $name): int
    {
        if (array_key_exists($name, $skills)) {
            return $skills[$name];
        }

        $prefix = $name.':';
        $values = [];

        foreach ($skills as $skillName => $value) {
            if (str_starts_with($skillName, $prefix)) {
                $values[] = $value;
            }
        }

        return $values === [] ? 0 : max($values);
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '',
            $values,
        ))));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function selectedItems(mixed $selected): array
    {
        if (! is_array($selected)) {
            return [];
        }

        $itemMap = RpgCharEditorEquipment::itemMap();
        $items = [];

        foreach ($selected as $selectedItem) {
            if (! is_array($selectedItem)) {
                continue;
            }

            $id = (string) ($selectedItem['id'] ?? '');

            if (! isset($itemMap[$id])) {
                continue;
            }

            $items[] = $itemMap[$id] + [
                'quantity' => max(1, (int) ($selectedItem['quantity'] ?? 1)),
            ];
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    private function activeItem(array $items, string $id, string $kind): ?array
    {
        if ($id === '') {
            return null;
        }

        foreach ($items as $item) {
            if (($item['id'] ?? null) === $id && ($item['combat']['kind'] ?? null) === $kind) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, int>  $attributes
     * @param  array<string, int>  $skills
     * @param  list<string>  $advantages
     * @return list<array<string, mixed>>
     */
    private function weapons(array $items, array $attributes, array $skills, array $advantages): array
    {
        $weapons = [];

        foreach ($items as $item) {
            if (($item['combat']['kind'] ?? null) !== 'weapon') {
                continue;
            }

            foreach ($item['combat']['modes'] ?? [] as $index => $mode) {
                if (! is_array($mode)) {
                    continue;
                }

                $weapons[] = $this->weaponRow(
                    $item,
                    $mode,
                    $attributes,
                    $skills,
                    $advantages,
                    $index > 0,
                );
            }
        }

        if (in_array('Natürliche Waffen', $advantages, true)) {
            $naturalItem = [
                'id' => 'natuerliche-waffen',
                'name' => 'Natürliche Waffen',
                'quantity' => 1,
            ];
            $naturalMode = [
                'kind' => 'melee',
                'skill' => 'Natürliche Waffen',
                'attributes' => ['st', 'ge'],
                'damageAttribute' => 'st',
                'precision' => 0,
                'damage' => 1,
            ];
            $weapons[] = $this->weaponRow($naturalItem, $naturalMode, $attributes, $skills, $advantages, false);
        }

        return $weapons;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $mode
     * @param  array<string, int>  $attributes
     * @param  array<string, int>  $skills
     * @param  list<string>  $advantages
     * @return array<string, mixed>
     */
    private function weaponRow(array $item, array $mode, array $attributes, array $skills, array $advantages, bool $alternate): array
    {
        $skill = (string) ($mode['skill'] ?? 'Nahkampf');
        $skillValue = $this->skillValue($skills, $skill);
        $attributeNames = is_array($mode['attributes'] ?? null) ? $mode['attributes'] : [];
        $chosenAttribute = 'st';
        $attack = PHP_INT_MIN;

        foreach ($attributeNames as $attributeName) {
            $attributeName = (string) $attributeName;
            $candidate = $skillValue + ($attributes[$attributeName] ?? 0);

            if ($candidate > $attack) {
                $attack = $candidate;
                $chosenAttribute = $attributeName;
            }
        }

        if ($attack === PHP_INT_MIN) {
            $attack = $skillValue;
        }

        $isRanged = ($mode['kind'] ?? null) === 'ranged';
        $precision = (int) ($mode['precision'] ?? 0);
        $sharpshooter = $isRanged && in_array('Scharfschütze', $advantages, true) ? 1 : 0;
        $attack += $precision + $sharpshooter;
        $damageAttribute = (string) ($mode['damageAttribute'] ?? ($isRanged ? 'wa' : 'st'));
        $damageModifier = ($attributes[$damageAttribute] ?? 0) + (int) ($mode['damage'] ?? 0);

        return [
            'id' => (string) ($item['id'] ?? ''),
            'name' => (string) ($item['name'] ?? ''),
            'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
            'kind' => $isRanged ? 'ranged' : 'melee',
            'alternate' => $alternate,
            'skill' => $skill,
            'attack_attribute' => self::ATTRIBUTE_LABELS[$chosenAttribute] ?? strtoupper($chosenAttribute),
            'attack' => $attack,
            'damage_modifier' => $damageModifier,
            'damage_attribute' => self::ATTRIBUTE_LABELS[$damageAttribute] ?? strtoupper($damageAttribute),
            'precision' => $precision,
            'type' => (string) ($mode['type'] ?? ''),
            'fire_rate' => (string) ($mode['fireRate'] ?? ''),
            'range_increment' => (string) ($mode['rangeIncrement'] ?? ''),
            'max_range' => (string) ($mode['maxRange'] ?? ''),
            'magazine' => $mode['magazine'] ?? null,
            'core_range_damage_bonus' => $sharpshooter,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function armorRows(array $items, string $activeArmorId, string $activeShieldId): array
    {
        $rows = [];

        foreach ($items as $item) {
            $kind = (string) ($item['combat']['kind'] ?? '');

            if (! in_array($kind, ['armor', 'shield'], true)) {
                continue;
            }

            $id = (string) ($item['id'] ?? '');
            $rows[] = [
                'id' => $id,
                'name' => (string) ($item['name'] ?? ''),
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'kind' => $kind,
                'active' => ($kind === 'armor' && $id === $activeArmorId) || ($kind === 'shield' && $id === $activeShieldId),
                'protection' => (int) ($item['combat']['protection'] ?? 0),
                'movement_modifier' => (int) ($item['combat']['movementModifier'] ?? 0),
                'defense_bonus' => (int) ($item['combat']['defenseBonus'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>|null  $item
     * @return array<string, mixed>|null
     */
    private function publicItem(?array $item): ?array
    {
        if ($item === null) {
            return null;
        }

        return [
            'id' => (string) ($item['id'] ?? ''),
            'name' => (string) ($item['name'] ?? ''),
            'combat' => $item['combat'] ?? [],
        ];
    }

    /**
     * @param  list<string>  $advantages
     * @param  list<string>  $disadvantages
     * @return list<string>
     */
    private function situationalNotes(array $advantages, array $disadvantages, mixed $details): array
    {
        $notes = [];
        $details = is_array($details) ? $details : [];

        if (in_array('Scharfschütze', $advantages, true)) {
            $notes[] = 'Scharfschütze: +1 Schaden im ersten Reichweiteninkrement.';
        }

        if (in_array('Taratzenfutter', $disadvantages, true)) {
            $notes[] = 'Taratzenfutter: gegen den Charakter gerichtete Schadenswürfe +1.';
        }

        if (in_array('Verwundbarkeit', $disadvantages, true)) {
            $trigger = trim((string) ($details['Verwundbarkeit'] ?? 'definierten Auslöser'));
            $notes[] = "Verwundbarkeit ({$trigger}): RO zählt nicht gegen Schaden.";
        }

        return $notes;
    }
}
