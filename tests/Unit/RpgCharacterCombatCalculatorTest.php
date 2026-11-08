<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RpgCharacterCombatCalculator;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class);

test('it rejects a missing custom item version instead of dropping its effects', function (): void {
    expect(fn () => (new RpgCharacterCombatCalculator)->calculate(['equipment' => ['items' => [['id' => 'custom-missing-v1', 'quantity' => 1]]]]))->toThrow(\InvalidArgumentException::class, 'Unbekannte eigene Gegenstandsversion: custom-missing-v1');
});

test('it calculates defense initiative movement and weapon values', function (): void {
    $combat = (new RpgCharacterCombatCalculator)->calculate(['attributes' => ['st' => 2, 'ge' => 1, 'ro' => 1, 'wa' => 1], 'skills' => [['name' => 'Nahkampf', 'value' => 3], ['name' => 'Athletik', 'value' => 2], ['name' => 'Fernkampf', 'value' => 1], ['name' => 'Feuerwaffen', 'value' => 4]], 'advantages' => ['Zäh', 'Kaltblütig', 'Kampfreflexe', 'Schnell', 'Panzerung', 'Scharfschütze'], 'advantage_counts' => ['Panzerung' => 2], 'equipment' => ['items' => [['id' => 'schwert', 'quantity' => 1], ['id' => 'bogen', 'quantity' => 1], ['id' => 'verstaerktes-leder', 'quantity' => 1], ['id' => 'holzschild', 'quantity' => 1]], 'active_armor_id' => 'verstaerktes-leder', 'active_shield_id' => 'holzschild']]);
    expect($combat['defense']['parade'])->toBe(6)
        ->and($combat['defense']['dodge'])->toBe(7)
        ->and($combat['defense']['damage_reduction'])->toBe(5)
        ->and($combat['initiative'])->toBe(['Nahkampf' => 5, 'Fernkampf' => 3, 'Feuerwaffen' => 6])
        ->and($combat['movement'])->toBe(['base' => 7, 'run' => 28])
        ->and($combat['active_armor']['name'])->toBe('Verstärktes Leder')
        ->and($combat['active_shield']['name'])->toBe('Holzschild');
    $weapons = collect($combat['weapons'])->keyBy('id');
    expect($weapons['schwert']['attack'])->toBe(5)
        ->and($weapons['schwert']['attack_attribute'])->toBe('ST')
        ->and($weapons['schwert']['damage_modifier'])->toBe(3)
        ->and($weapons['bogen']['attack'])->toBe(2)
        ->and($weapons['bogen']['damage_modifier'])->toBe(2)
        ->and($weapons['bogen']['core_range_damage_bonus'])->toBe(1)
        ->and($weapons['bogen']['range_increment'])->toBe('15m');
});

test('it exposes alternate throwing and natural weapon modes', function (): void {
    $combat = (new RpgCharacterCombatCalculator)->calculate(['attributes' => ['st' => 1, 'ge' => 2, 'wa' => -1], 'skills' => [['name' => 'Nahkampf', 'value' => 2], ['name' => 'Fernkampf', 'value' => 3], ['name' => 'Natürliche Waffen', 'value' => 1]], 'advantages' => ['Natürliche Waffen'], 'equipment' => ['items' => [['id' => 'messer-dolch', 'quantity' => 2]]]]);
    $daggerModes = array_values(array_filter($combat['weapons'], static fn (array $weapon): bool => $weapon['id'] === 'messer-dolch'));
    expect($daggerModes)->toHaveCount(2)
        ->and($daggerModes[0]['alternate'])->toBeFalse()
        ->and($daggerModes[0]['attack'])->toBe(4)
        ->and($daggerModes[1]['alternate'])->toBeTrue()
        ->and($daggerModes[1]['attack'])->toBe(2)
        ->and($daggerModes[1]['damage_modifier'])->toBe(-1);
    $natural = collect($combat['weapons'])->firstWhere('id', 'natuerliche-waffen');
    expect($natural['attack'])->toBe(4)
        ->and($natural['skill'])->toBe('Nahkampf')
        ->and($natural['damage_modifier'])->toBe(2);
});

test('it ignores unselected active protection and adds situational notes', function (): void {
    $combat = (new RpgCharacterCombatCalculator)->calculate(['attributes' => ['ro' => -1], 'advantages' => ['Zäh', 'Scharfschütze'], 'disadvantages' => ['Taratzenfutter', 'Verwundbarkeit'], 'disadvantage_details' => ['Verwundbarkeit' => 'Feuer'], 'equipment' => ['items' => [['id' => 'kampfpanzer', 'quantity' => 1]], 'active_armor_id' => 'nicht-gewaehlt', 'active_shield_id' => 'holzschild']]);
    expect($combat['active_armor'])->toBeNull()
        ->and($combat['active_shield'])->toBeNull()
        ->and($combat['defense']['damage_reduction'])->toBe(0)
        ->and($combat['situational_notes'])->toContain('Scharfschütze: +1 Schaden im ersten Reichweiteninkrement.')
        ->toContain('Taratzenfutter: gegen den Charakter gerichtete Schadenswürfe +1.')
        ->toContain('Verwundbarkeit (Feuer): RO zählt nicht gegen Schaden.');
});

test('it exposes all non numeric advantage effects as rule notes', function (): void {
    $combat = (new RpgCharacterCombatCalculator)->calculate(['advantages' => ['Anführer', 'Gestaltwandler', 'Gesteigerter Sinn', 'Kiemen', 'Nachtsicht', 'Natürliche Waffen', 'Psychische Kraft', 'Psychisches Reservoir', 'Regeneration', 'Sprachbegabt'], 'advantage_effects' => [['name' => 'Gesteigerter Sinn', 'target' => 'Hören', 'justification' => 'Mutation'], ['name' => 'Psychische Kraft', 'target' => 'Telekinese', 'justification' => ''], ['name' => 'Regeneration', 'target' => '', 'justification' => 'Mutation'], ['name' => 'Regeneration', 'target' => '', 'justification' => 'Naniten'], ['name' => 'Regeneration', 'target' => '', 'justification' => 'Implantat']]]);
    $notes = implode("\n", $combat['situational_notes']);
    expect($notes)->toContain('Anführer: +2')
        ->toContain('Statur und Größe ±20 %')
        ->toContain('Gesteigerter Sinn (Hören): +3')
        ->toContain('Kiemen: unbegrenztes Atmen unter Wasser.')
        ->toContain('Nachtsicht: keine Abzüge durch Dunkelheit.')
        ->toContain('Natürliche Waffen: Angriff mit Nahkampf und ST/GE; Schaden +1 S.')
        ->toContain('Psychische Kraft: Telekinese.')
        ->toContain('Psychisches Reservoir: höchster psychischer FW zählt bei der PEP-Ermittlung doppelt.')
        ->toContain('Regeneration: Heilung mit Faktor 1000.')
        ->toContain('Sprachbegabt: bis zu drei Sprachen oder Dialekte je Fertigkeitspunkt.');
});
