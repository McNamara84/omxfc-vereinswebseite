<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RpgCharacterCombatCalculator;
use App\Services\RpgCharacterSheetPresenter;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class);

test('it maps character data into the fixed sheet structure', function (): void {
    $data = ['rules' => ['creation_level' => 5], 'character' => ['character_name' => 'Aruula', 'player_name' => 'Spielerin', 'gender' => 'weiblich', 'race' => 'Barbar', 'culture' => 'Landbewohner', 'description' => 'Eine erfahrene Kämpferin.'], 'attributes' => ['st' => 2, 'au' => -1], 'skills' => [['name' => 'Nahkampf', 'value' => 4], ['name' => 'Beruf: Jägerin', 'value' => 2], ['name' => 'Kunde: Kräuter', 'value' => 3], ['name' => 'Kunde: Wetter', 'value' => 1]], 'advantages' => ['Zäh', 'Panzerung'], 'advantage_counts' => ['Panzerung' => 2], 'disadvantages' => ['Verwundbarkeit'], 'disadvantage_details' => ['Verwundbarkeit' => 'Feuer'], 'trainings' => ['Krieger', 'Arbeiter'], 'equipment' => ['clothing' => ['name' => 'Kleidung, Wanderer'], 'items' => [['id' => 'schwert', 'name' => 'Schwert', 'quantity' => 1], ['id' => 'seil', 'name' => 'Seil', 'quantity' => 2]], 'ammunition' => [['source' => 'Bogen', 'quantity' => 30, 'unit' => 'Pfeile']], 'notes' => 'Schwert frisch geschärft.']];
    $combat = (new RpgCharacterCombatCalculator)->calculate($data);
    $sheet = (new RpgCharacterSheetPresenter)->present($data, $combat);
    expect($sheet['character_name'])->toBe('Aruula')
        ->and($sheet['gender'])->toBe('weiblich')
        ->and($sheet['race_culture'])->toBe('Barbar · Landbewohner')
        ->and($sheet['creation_level'])->toBe(5)
        ->and($sheet['trainings'])->toBe('Krieger, Arbeiter')
        ->and($sheet['professions'])->toBe('Jägerin 2')
        ->and($sheet['advantages'])->toContain('Panzerung (2×)')
        ->and($sheet['disadvantages'])->toBe('Verwundbarkeit: Feuer')
        ->and($sheet['specializations'])->toContain('Kunde: Kräuter 3, Wetter 1')
        ->and($sheet['equipment'])->toBe('Kleidung, Wanderer, Schwert, 2× Seil')
        ->and($sheet['ammunition'])->toBe('Bogen: 30 Pfeile')
        ->and($sheet['skill_columns'])->toHaveCount(2)
        ->and($sheet['skill_columns'][0])->toHaveCount(10)
        ->and(collect($sheet['skill_columns'])->flatten(1)->firstWhere('name', 'Nahkampf')['value'])->toBe(4);
});

test('it uses adventurer for an empty training selection and limits long text', function (): void {
    $data = ['character' => ['character_name' => str_repeat('Langer Name ', 20), 'description' => str_repeat('Beschreibung ', 40)], 'trainings' => []];
    $combat = (new RpgCharacterCombatCalculator)->calculate($data);
    $sheet = (new RpgCharacterSheetPresenter)->present($data, $combat);
    expect($sheet['trainings'])->toBe('Abenteurer')
        ->and(mb_strlen($sheet['character_name']))->toBeLessThanOrEqual(70)
        ->and($sheet['description'])->toBe(trim($data['character']['description']))
        ->and($sheet['character_name'])->toEndWith('…');
});

test('description is normalized without discarding text for pdf layout', function (): void {
    $description = "  Eine\n\nweit\tgereiste Figur. ".str_repeat('Vollständige Beschreibung. ', 80);
    $data = ['character' => ['description' => $description]];
    $sheet = (new RpgCharacterSheetPresenter)->present($data, (new RpgCharacterCombatCalculator)->calculate($data));
    expect($sheet['description'])->toBe('Eine weit gereiste Figur. '.rtrim(str_repeat('Vollständige Beschreibung. ', 80)))
        ->and($data['character']['description'])->toBe($description);
    foreach ([null, [], new \stdClass] as $invalidDescription) {
        $data['character']['description'] = $invalidDescription;
        expect((new RpgCharacterSheetPresenter)->present($data, [])['description'])->toBe('');
    }
});

test('it formats structured advantage instances and languages', function (): void {
    $data = ['rules' => ['creation_level' => 5], 'skills' => [['name' => 'Sprachen', 'value' => 2]], 'languages' => ['Deutsch', 'Englisch', 'Schwedisch'], 'advantages' => ['Zäh', 'Gesteigerter Sinn', 'Psychische Kraft', 'Regeneration'], 'advantage_effects' => [['name' => 'Zäh', 'target' => '', 'justification' => 'Figurenstärke 5'], ['name' => 'Gesteigerter Sinn', 'target' => 'Sehen', 'justification' => 'Implantat'], ['name' => 'Gesteigerter Sinn', 'target' => 'Hören', 'justification' => 'Mutation'], ['name' => 'Psychische Kraft', 'target' => 'Telepathie', 'justification' => ''], ['name' => 'Regeneration', 'target' => '', 'justification' => 'Naniten'], ['name' => 'Regeneration', 'target' => '', 'justification' => 'Mutation']]];
    $combat = (new RpgCharacterCombatCalculator)->calculate($data);
    $sheet = (new RpgCharacterSheetPresenter)->present($data, $combat);
    expect($sheet['advantages'])->toContain('Gesteigerter Sinn (2×): Sehen – Implantat; Hören – Mutation')
        ->toContain('Psychische Kraft: Telepathie')
        ->toContain('Regeneration (2×): Naniten; Mutation')->not->toContain('Figurenstärke 5')
        ->and($sheet['specializations'])->toContain('Sprachen: Deutsch, Englisch, Schwedisch')
        ->and($combat['situational_notes'])->toContain('Regeneration: Heilung mit Faktor 100.');
});
