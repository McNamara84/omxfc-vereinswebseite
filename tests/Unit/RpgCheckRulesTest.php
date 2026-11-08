<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RpgCheckCalculator;
use App\Services\RpgCheckDice;
use App\Support\RpgCheckRules;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class);
dataset('RpgCheckRulesTest.examples', static fn (): array => ['attribute rulebook example' => ['attribute', 1, null, 0, [3, 6], 12, 12, 'success'], 'attribute equality' => ['attribute', 1, null, 0, [4, 3], 10, 10, 'success'], 'skill with negative modifier' => ['skill', 1, 2, -1, [4, 3], 10, 9, 'failure'], 'untrained with negative attribute' => ['skill', -2, 0, 0, [2, 3], 4, 3, 'failure'], 'negative total' => ['attribute', -2, null, -4, [1, 2], -6, -7, 'failure'], 'critical' => ['skill', 0, 2, 0, [6, 6], 14, 14, 'critical_success'], 'twelve is no automatic success' => ['skill', 0, 0, 0, [6, 6], 14, 12, 'failure'], 'fumble' => ['attribute', 0, null, 0, [1, 1], 7, 2, 'fumble'], 'two can succeed' => ['skill', 2, 6, 0, [1, 1], 10, 10, 'success']]);
dataset('RpgCheckRulesTest.invalidDice', static fn (): array => [[[0, 6]], [[7, 1]], [[1]], [[1, 2, 3]], [['1', 2]]]);
test('rulebook examples', function (string $type, int $attribute, ?int $skill, int $modifier, array $dice, int $difficulty, int $total, string $kind): void {
    expect($dice)->toBeList();
    /** @var list<mixed> $dice */
    $calculator = new RpgCheckCalculator;
    $roll = $calculator->roll($calculator->base($type, $attribute, $skill, $modifier), $dice);
    expect($roll['total'])->toBe($total)
        ->and($calculator->result($roll['raw_total'], $roll['total'] - $difficulty))->toBe($kind);
})->with('RpgCheckRulesTest.examples');
test('presets match page twelve', function (): void {
    expect(RpgCheckRules::DIFFICULTIES)->toBe(['Leicht' => 7, 'Mittel' => 10, 'Schwer' => 11, 'Extrem' => 12, 'Legendär' => 14]);
});
test('invalid dice are rejected', function (array $dice): void {
    expect($dice)->toBeList();
    /** @var list<mixed> $dice */
    expect(fn () => new RpgCheckCalculator()->roll(0, $dice))->toThrow(InvalidArgumentException::class);
})->with('RpgCheckRulesTest.invalidDice');
test('secure dice only produce two w6', function (): void {
    foreach (range(1, 50) as $_) {
        $dice = new RpgCheckDice()->roll();
        expect($dice)->toHaveCount(2);
        foreach ($dice as $die) {
            expect([1, 2, 3, 4, 5, 6])->toContain($die);
        }
    }
});
