<?php

namespace Tests\Unit;

use App\Services\RpgCharacterCombatCalculator;
use PHPUnit\Framework\TestCase;

class RpgCharacterCombatCalculatorTest extends TestCase
{
    public function test_it_calculates_defense_initiative_movement_and_weapon_values(): void
    {
        $combat = (new RpgCharacterCombatCalculator)->calculate([
            'attributes' => ['st' => 2, 'ge' => 1, 'ro' => 1, 'wa' => 1],
            'skills' => [
                ['name' => 'Nahkampf', 'value' => 3],
                ['name' => 'Athletik', 'value' => 2],
                ['name' => 'Fernkampf', 'value' => 1],
                ['name' => 'Feuerwaffen', 'value' => 4],
            ],
            'advantages' => ['Zäh', 'Kaltblütig', 'Kampfreflexe', 'Schnell', 'Panzerung', 'Scharfschütze'],
            'advantage_counts' => ['Panzerung' => 2],
            'equipment' => [
                'items' => [
                    ['id' => 'schwert', 'quantity' => 1],
                    ['id' => 'bogen', 'quantity' => 1],
                    ['id' => 'verstaerktes-leder', 'quantity' => 1],
                    ['id' => 'holzschild', 'quantity' => 1],
                ],
                'active_armor_id' => 'verstaerktes-leder',
                'active_shield_id' => 'holzschild',
            ],
        ]);

        $this->assertSame(6, $combat['defense']['parade']);
        $this->assertSame(7, $combat['defense']['dodge']);
        $this->assertSame(5, $combat['defense']['damage_reduction']);
        $this->assertSame(['Nahkampf' => 5, 'Fernkampf' => 3, 'Feuerwaffen' => 6], $combat['initiative']);
        $this->assertSame(['base' => 7, 'run' => 28], $combat['movement']);
        $this->assertSame('Verstärktes Leder', $combat['active_armor']['name']);
        $this->assertSame('Holzschild', $combat['active_shield']['name']);

        $weapons = collect($combat['weapons'])->keyBy('id');
        $this->assertSame(5, $weapons['schwert']['attack']);
        $this->assertSame('ST', $weapons['schwert']['attack_attribute']);
        $this->assertSame(3, $weapons['schwert']['damage_modifier']);
        $this->assertSame(4, $weapons['bogen']['attack']);
        $this->assertSame(0, $weapons['bogen']['damage_modifier']);
        $this->assertSame(1, $weapons['bogen']['core_range_damage_bonus']);
        $this->assertSame('15m', $weapons['bogen']['range_increment']);
    }

    public function test_it_exposes_alternate_throwing_and_natural_weapon_modes(): void
    {
        $combat = (new RpgCharacterCombatCalculator)->calculate([
            'attributes' => ['st' => 1, 'ge' => 2, 'wa' => -1],
            'skills' => [
                ['name' => 'Nahkampf', 'value' => 2],
                ['name' => 'Fernkampf', 'value' => 3],
                ['name' => 'Natürliche Waffen', 'value' => 1],
            ],
            'advantages' => ['Natürliche Waffen'],
            'equipment' => ['items' => [['id' => 'messer-dolch', 'quantity' => 2]]],
        ]);

        $daggerModes = array_values(array_filter(
            $combat['weapons'],
            static fn (array $weapon): bool => $weapon['id'] === 'messer-dolch',
        ));

        $this->assertCount(2, $daggerModes);
        $this->assertFalse($daggerModes[0]['alternate']);
        $this->assertSame(4, $daggerModes[0]['attack']);
        $this->assertTrue($daggerModes[1]['alternate']);
        $this->assertSame(2, $daggerModes[1]['attack']);
        $this->assertSame(-1, $daggerModes[1]['damage_modifier']);

        $natural = collect($combat['weapons'])->firstWhere('id', 'natuerliche-waffen');
        $this->assertSame(4, $natural['attack']);
        $this->assertSame('Nahkampf', $natural['skill']);
        $this->assertSame(2, $natural['damage_modifier']);
    }

    public function test_it_ignores_unselected_active_protection_and_adds_situational_notes(): void
    {
        $combat = (new RpgCharacterCombatCalculator)->calculate([
            'attributes' => ['ro' => -1],
            'advantages' => ['Zäh', 'Scharfschütze'],
            'disadvantages' => ['Taratzenfutter', 'Verwundbarkeit'],
            'disadvantage_details' => ['Verwundbarkeit' => 'Feuer'],
            'equipment' => [
                'items' => [['id' => 'kampfpanzer', 'quantity' => 1]],
                'active_armor_id' => 'nicht-gewaehlt',
                'active_shield_id' => 'holzschild',
            ],
        ]);

        $this->assertNull($combat['active_armor']);
        $this->assertNull($combat['active_shield']);
        $this->assertSame(0, $combat['defense']['damage_reduction']);
        $this->assertContains('Scharfschütze: +1 Schaden im ersten Reichweiteninkrement.', $combat['situational_notes']);
        $this->assertContains('Taratzenfutter: gegen den Charakter gerichtete Schadenswürfe +1.', $combat['situational_notes']);
        $this->assertContains('Verwundbarkeit (Feuer): RO zählt nicht gegen Schaden.', $combat['situational_notes']);
    }

    public function test_it_exposes_all_non_numeric_advantage_effects_as_rule_notes(): void
    {
        $combat = (new RpgCharacterCombatCalculator)->calculate([
            'advantages' => [
                'Anführer',
                'Gestaltwandler',
                'Gesteigerter Sinn',
                'Kiemen',
                'Nachtsicht',
                'Natürliche Waffen',
                'Psychische Kraft',
                'Psychisches Reservoir',
                'Regeneration',
                'Sprachbegabt',
            ],
            'advantage_effects' => [
                ['name' => 'Gesteigerter Sinn', 'target' => 'Hören', 'justification' => 'Mutation'],
                ['name' => 'Psychische Kraft', 'target' => 'Telekinese', 'justification' => ''],
                ['name' => 'Regeneration', 'target' => '', 'justification' => 'Mutation'],
                ['name' => 'Regeneration', 'target' => '', 'justification' => 'Naniten'],
                ['name' => 'Regeneration', 'target' => '', 'justification' => 'Implantat'],
            ],
        ]);

        $notes = implode("\n", $combat['situational_notes']);
        $this->assertStringContainsString('Anführer: +2', $notes);
        $this->assertStringContainsString('Statur und Größe ±20 %', $notes);
        $this->assertStringContainsString('Gesteigerter Sinn (Hören): +3', $notes);
        $this->assertStringContainsString('Kiemen: unbegrenztes Atmen unter Wasser.', $notes);
        $this->assertStringContainsString('Nachtsicht: keine Abzüge durch Dunkelheit.', $notes);
        $this->assertStringContainsString('Natürliche Waffen: Angriff mit Nahkampf und ST/GE; Schaden +1 S.', $notes);
        $this->assertStringContainsString('Psychische Kraft: Telekinese.', $notes);
        $this->assertStringContainsString('Psychisches Reservoir: höchster psychischer FW zählt bei der PEP-Ermittlung doppelt.', $notes);
        $this->assertStringContainsString('Regeneration: Heilung mit Faktor 1000.', $notes);
        $this->assertStringContainsString('Sprachbegabt: bis zu drei Sprachen oder Dialekte je Fertigkeitspunkt.', $notes);
    }
}
