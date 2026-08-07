<?php

namespace Tests\Unit;

use App\Services\RpgCharacterCombatCalculator;
use App\Services\RpgCharacterSheetPresenter;
use PHPUnit\Framework\TestCase;

class RpgCharacterSheetPresenterTest extends TestCase
{
    public function test_it_maps_character_data_into_the_fixed_sheet_structure(): void
    {
        $data = [
            'character' => [
                'character_name' => 'Aruula',
                'player_name' => 'Spielerin',
                'gender' => 'weiblich',
                'race' => 'Barbar',
                'culture' => 'Landbewohner',
                'description' => 'Eine erfahrene Kämpferin.',
            ],
            'attributes' => ['st' => 2, 'au' => -1],
            'skills' => [
                ['name' => 'Nahkampf', 'value' => 4],
                ['name' => 'Beruf: Jägerin', 'value' => 2],
                ['name' => 'Kunde: Kräuter', 'value' => 3],
                ['name' => 'Kunde: Wetter', 'value' => 1],
            ],
            'advantages' => ['Zäh', 'Panzerung'],
            'advantage_counts' => ['Panzerung' => 2],
            'disadvantages' => ['Verwundbarkeit'],
            'disadvantage_details' => ['Verwundbarkeit' => 'Feuer'],
            'trainings' => ['Krieger', 'Arbeiter'],
            'equipment' => [
                'clothing' => ['name' => 'Kleidung, Wanderer'],
                'items' => [
                    ['id' => 'schwert', 'name' => 'Schwert', 'quantity' => 1],
                    ['id' => 'seil', 'name' => 'Seil', 'quantity' => 2],
                ],
                'ammunition' => [['source' => 'Bogen', 'quantity' => 30, 'unit' => 'Pfeile']],
                'notes' => 'Schwert frisch geschärft.',
            ],
        ];
        $combat = (new RpgCharacterCombatCalculator)->calculate($data);
        $sheet = (new RpgCharacterSheetPresenter)->present($data, $combat);

        $this->assertSame('Aruula', $sheet['character_name']);
        $this->assertSame('weiblich', $sheet['gender']);
        $this->assertSame('Barbar · Landbewohner', $sheet['race_culture']);
        $this->assertSame('Krieger, Arbeiter', $sheet['trainings']);
        $this->assertSame('Jägerin 2', $sheet['professions']);
        $this->assertStringContainsString('Panzerung (2×)', $sheet['advantages']);
        $this->assertSame('Verwundbarkeit: Feuer', $sheet['disadvantages']);
        $this->assertStringContainsString('Kunde: Kräuter 3, Wetter 1', $sheet['specializations']);
        $this->assertSame('Kleidung, Wanderer, Schwert, 2× Seil', $sheet['equipment']);
        $this->assertSame('Bogen: 30 Pfeile', $sheet['ammunition']);
        $this->assertCount(2, $sheet['skill_columns']);
        $this->assertCount(10, $sheet['skill_columns'][0]);
        $this->assertSame(4, collect($sheet['skill_columns'])->flatten(1)->firstWhere('name', 'Nahkampf')['value']);
    }

    public function test_it_uses_adventurer_for_an_empty_training_selection_and_limits_long_text(): void
    {
        $data = [
            'character' => [
                'character_name' => str_repeat('Langer Name ', 20),
                'description' => str_repeat('Beschreibung ', 40),
            ],
            'trainings' => [],
        ];
        $combat = (new RpgCharacterCombatCalculator)->calculate($data);
        $sheet = (new RpgCharacterSheetPresenter)->present($data, $combat);

        $this->assertSame('Abenteurer', $sheet['trainings']);
        $this->assertLessThanOrEqual(70, mb_strlen($sheet['character_name']));
        $this->assertLessThanOrEqual(230, mb_strlen($sheet['description']));
        $this->assertStringEndsWith('…', $sheet['character_name']);
    }

    public function test_it_formats_structured_advantage_instances_and_languages(): void
    {
        $data = [
            'skills' => [['name' => 'Sprachen', 'value' => 2]],
            'languages' => ['Deutsch', 'Englisch', 'Schwedisch'],
            'advantages' => ['Zäh', 'Gesteigerter Sinn', 'Psychische Kraft', 'Regeneration'],
            'advantage_effects' => [
                ['name' => 'Zäh', 'target' => '', 'justification' => 'Figurenstärke 3'],
                ['name' => 'Gesteigerter Sinn', 'target' => 'Sehen', 'justification' => 'Implantat'],
                ['name' => 'Gesteigerter Sinn', 'target' => 'Hören', 'justification' => 'Mutation'],
                ['name' => 'Psychische Kraft', 'target' => 'Telepathie', 'justification' => ''],
                ['name' => 'Regeneration', 'target' => '', 'justification' => 'Naniten'],
                ['name' => 'Regeneration', 'target' => '', 'justification' => 'Mutation'],
            ],
        ];
        $combat = (new RpgCharacterCombatCalculator)->calculate($data);
        $sheet = (new RpgCharacterSheetPresenter)->present($data, $combat);

        $this->assertStringContainsString('Gesteigerter Sinn (2×): Sehen – Implantat; Hören – Mutation', $sheet['advantages']);
        $this->assertStringContainsString('Psychische Kraft: Telepathie', $sheet['advantages']);
        $this->assertStringContainsString('Regeneration (2×): Naniten; Mutation', $sheet['advantages']);
        $this->assertStringContainsString('Sprachen: Deutsch, Englisch, Schwedisch', $sheet['specializations']);
        $this->assertContains('Regeneration: Heilung mit Faktor 100.', $combat['situational_notes']);
    }
}
