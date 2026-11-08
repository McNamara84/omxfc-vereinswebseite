<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RpgCharacterSheetService;
use App\Support\RpgCharEditorTraining;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class);

test('training costs and special rules match the rulebook tables', function (): void {
    $definitions = RpgCharEditorTraining::definitions();
    expect(array_map(static fn (array $training): int => $training['cost'], $definitions))->toBe(['Arbeiter' => 5, 'Arzt (Heilkundiger)' => 5, 'Dieb' => 6, 'Forscher' => 6, 'Händler' => 5, 'Krieger' => 5, "Rev'rend" => 15, 'Schamane (Göttersprecher)' => 5, 'Seher' => 5, 'Truveer' => 6, 'Gladiator' => 5, 'Priester' => 5])
        ->and($definitions['Seher']['requiredAdvantages'])->toBe(['Psychische Kraft'])
        ->and($definitions["Rev'rend"]['suggestedSpecializations'])->toBe(['Unterhalten' => 'Predigen'])
        ->and($definitions['Krieger']['skills'])->toBe(['Nahkampf', 'Fernkampf', 'Feuerwaffen', 'Reiten', 'Fahren', 'Pilot']);
});

test('every training skill is a known or specializable base skill', function (): void {
    $skillRules = RpgCharacterSheetService::skillRuleConfig();
    $knownSkills = array_column($skillRules['skills'], 'name');
    foreach (RpgCharEditorTraining::definitions() as $training) {
        expect($training['description'])->not->toBeEmpty()
            ->and($training['cost'])->toBeGreaterThan(0)
            ->and($training['skills'])->not->toBeEmpty();
        foreach ($training['skills'] as $skill) {
            expect($knownSkills)->toContain($skill);
        }
    }
});

test('rule config is a json friendly list with backend limits', function (): void {
    $config = RpgCharEditorTraining::ruleConfig();
    expect($config['maxTrainings'])->toBe(RpgCharEditorTraining::MAX_TRAININGS)
        ->and($config['maxAllocations'])->toBe(RpgCharEditorTraining::MAX_ALLOCATIONS)
        ->and($config['trainings'])->toBe(array_values(RpgCharEditorTraining::definitions()));
});
