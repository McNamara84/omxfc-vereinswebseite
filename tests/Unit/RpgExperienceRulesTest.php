<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RpgCharacterPsychicCalculator;
use App\Support\RpgExperienceRules;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class);

mutates(RpgExperienceRules::class);
dataset('RpgExperienceRulesTest.prices', static fn (): array => [[1, 6], [2, 6], [3, 6], [4, 12], [5, 12], [6, 18], [20, 18]]);
test('skill cost depends on each target value', function (int $target, int $cost): void {
    expect(RpgExperienceRules::skillCost($target))->toBe($cost)
        ->and(RpgExperienceRules::skillCost($target, true))->toBe($cost * 2);
})->with('RpgExperienceRulesTest.prices');
test('advantage prices preserve special costs and the default', function (string $advantage, int $cost): void {
    expect(RpgExperienceRules::advantageCost($advantage))->toBe($cost);
})->with([
    'shapechanger costs three advantages' => ['Gestaltwandler', 60],
    'armor costs one advantage' => ['Panzerung', 20],
    'uncatalogued advantage uses the default' => ['Unbekannter Vorteil', 20],
]);
test('psychic capacity latent powers and reservoir', function (): void {
    $payload = ['attributes' => ['wi' => 1], 'advantage_effects' => [['name' => 'Psychische Kraft', 'target' => 'Telepathie'], ['name' => 'Psychische Kraft', 'target' => 'Empathie']], 'skills' => [['name' => 'Telepathie', 'value' => 3]], 'advantages' => []];
    $calculator = new RpgCharacterPsychicCalculator;
    $result = $calculator->calculate($payload);
    expect($result['pep'])->toBe(9)
        ->and($result['powers'][0]['usable'])->toBeTrue()
        ->and($result['powers'][1]['usable'])->toBeFalse();
    $payload['advantages'][] = 'Psychisches Reservoir';
    expect($calculator->calculate($payload)['pep'])->toBe(18);
    $payload['attributes']['wi'] = -1;
    expect($calculator->calculate($payload)['powers'][0]['usable'])->toBeFalse();
});
