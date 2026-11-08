<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RpgCombat\CombatDice;
use App\Services\RpgCombat\CombatMath;
use App\Services\RpgCombat\CombatTraits;
use App\Support\RpgCharEditorEquipment;
use App\Support\RpgCharEditorSpecialRules;
use App\Support\RpgCombatRules;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class);
dataset('RpgCombatMathTest.invalidRolls', static fn (): array => [[[], []], [[1, 2, 3], []], [[0, 4], []], [[7], []], [['6'], []], [[2.5], []], [[3], ['ST' => '2']]]);
dataset('RpgCombatMathTest.ranges', static fn (): array => [[0, 0], [800, 0], [801, -1], [1500, -1], [1600, -1], [1601, -2], [8000, -9]]);
dataset('RpgCombatMathTest.invalidRanges', static fn (): array => [[-1, 100, 1000], [1, 0, 1000], [1001, 100, 1000]]);
dataset('RpgCombatMathTest.damageBoundaries', static fn (): array => [[-10, 0], [0, 0], [1, 1], [2, 1], [3, 2], [4, 2], [5, 3], [6, 3], [7, 4], [20, 4]]);
dataset('RpgCombatMathTest.invalidPsychicParameters', static fn (): array => [[0, 0, 0, 0], [1, -1, 0, 0], [1, 1, 1, 1], [20, 13, 0, 0]]);
test('rolls keep every modifier and require actual success or failure', function (): void {
    $roll = CombatMath::roll([4, 3], ['Nahkampf' => 4, 'ST' => 1, 'Verletzung' => -1]);
    expect($roll['total'])->toBe(11)
        ->and($roll['modifiers']['Verletzung'])->toBe(-1)
        ->and(CombatMath::result($roll, 11)['success'])->toBeTrue()
        ->and(CombatMath::result(CombatMath::roll([6, 6], []), 13)['critical'])->toBeFalse()
        ->and(CombatMath::result(CombatMath::roll([6, 6], []), 12)['critical'])->toBeTrue()
        ->and(CombatMath::result(CombatMath::roll([1, 1], ['FW' => 10]), 11)['fumble'])->toBeFalse()
        ->and(CombatMath::result(CombatMath::roll([1, 1], []), 3)['fumble'])->toBeTrue()
        ->and(CombatMath::result(CombatMath::roll([2], []), 7)['fumble'])->toBeFalse();
});
test('invalid rolls are rejected', function (array $dice, array $modifiers): void {
    expect(fn () => CombatMath::roll($dice, $modifiers))->toThrow(InvalidArgumentException::class);
})->with('RpgCombatMathTest.invalidRolls');
test('range boundaries in centimetres', function (int $distance, int $expected): void {
    expect(CombatMath::rangePenalty($distance, 800, 8000))->toBe($expected);
})->with('RpgCombatMathTest.ranges');
test('illegal ranges are rejected', function (int $distance, int $increment, int $maximum): void {
    expect(fn () => CombatMath::rangePenalty($distance, $increment, $maximum))->toThrow(InvalidArgumentException::class);
})->with('RpgCombatMathTest.invalidRanges');
test('fire rates match page 49 and cannot exceed the weapon', function (): void {
    expect(CombatMath::fireMode('A', 'A'))->toBe(['ammunition' => 15, 'attack' => -4, 'damage' => 1, 'area' => 6])
        ->and(CombatMath::fireMode('H', 'A'))->toBe(['ammunition' => 2, 'attack' => -1, 'damage' => 0, 'area' => 1])
        ->and(CombatMath::fireMode('S', 'A')['ammunition'])->toBe(6)
        ->and(CombatMath::fireMode('E', 'H')['ammunition'])->toBe(1);
    foreach ([['S', 'H'], ['X', 'A'], ['E', 'X']] as [$mode, $maximum]) {
        try {
            CombatMath::fireMode($mode, $maximum);
            $this->fail('Unzulässige Feuerart akzeptiert.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
    }
});
test('aimed attacks and critical damage never double the bonus', function (): void {
    expect(CombatMath::aimedLimit(5))->toBe(3)
        ->and(CombatMath::aimedLimit(-2))->toBe(0)
        ->and(CombatMath::aimedLimit(0))->toBe(0)
        ->and(CombatMath::criticalDamage(CombatMath::roll([6, 6], []), 12))->toBe(0)
        ->and(CombatMath::criticalDamage(CombatMath::roll([6, 6], []), 11))->toBe(1)
        ->and(CombatMath::criticalDamage(CombatMath::roll([6, 6], []), 8))->toBe(1)
        ->and(CombatMath::criticalDamage(CombatMath::roll([4, 3], ['FW' => 7]), 11))->toBe(0)
        ->and(CombatMath::criticalDamage(CombatMath::roll([4, 3], ['FW' => 7]), 10))->toBe(1);
});
test('wound boundaries', function (int $damage, int $wound): void {
    expect(CombatMath::wound($damage))->toBe($wound);
})->with('RpgCombatMathTest.damageBoundaries');
test('wound accumulation matches the book example and both published rulings', function (): void {
    expect(CombatMath::injuryModifier([1, 1, 1]))->toBe(0)
        ->and(CombatMath::injuryModifier([2]))->toBe(1)
        ->and(CombatMath::injuryModifier([2, 3]))->toBe(2)
        ->and(CombatMath::injuryModifier([2, 3, 3]))->toBe(3)
        ->and(CombatMath::injuryModifier([2, 2]))->toBe(2)
        ->and(CombatMath::injuryModifier([2, 2, 2, 2]))->toBe(3)
        ->and(CombatMath::injuryModifier([2, 3, 2]))->toBe(3)
        ->and(CombatMath::injuryModifier([2, 3, 2], 'reset'))->toBe(2)
        ->and(CombatMath::heal([1, 3, 3]))->toBe([1, 3, 2])
        ->and(CombatMath::heal([1]))->toBe([])
        ->and(CombatMath::heal([]))->toBe([]);
});
test('psychic parameters and pyrokinetic faq example', function (): void {
    $parameters = CombatMath::psychicParameters(4, 1, 3, 4);
    expect($parameters['duration'])->toBe(10)
        ->and($parameters['range'])->toBe(5000)
        ->and($parameters['strength'])->toBe(40)
        ->and(CombatMath::pyrokineticCost($parameters, 2, 4))->toBe(36)
        ->and(CombatMath::psychicParameters(1, 0, 0, 0)['cost'])->toBe(0)
        ->and(CombatMath::psychicParameters(1, 0, 0, 0)['strength'])->toBe(1);
    $large = CombatMath::psychicParameters(9, 6, 6, 6);
    expect($large['duration'])->toBe(64800)
        ->and($large['range'])->toBe(500000)
        ->and($large['strength'])->toBe(160);
});
test('invalid psychic parameters', function (int $fw, int $d, int $r, int $s): void {
    expect(fn () => CombatMath::psychicParameters($fw, $d, $r, $s))->toThrow(InvalidArgumentException::class);
})->with('RpgCombatMathTest.invalidPsychicParameters');
test('pyrokinetic damage is bounded and rounded down', function (): void {
    expect(fn () => CombatMath::pyrokineticCost(['cost' => 1], 2, 3))->toThrow(InvalidArgumentException::class);
});
test('negative pyrokinetic damage is rejected', function (): void {
    expect(fn () => CombatMath::pyrokineticCost(['cost' => 1], -1, 3))->toThrow(InvalidArgumentException::class);
});
test('w66 uses tens and units and single dice do not become 2w6', function (): void {
    $dice = new class extends CombatDice
    {
        public array $values = [4, 3, 6];

        public function die(): int
        {
            return array_shift($this->values);
        }
    };
    expect($dice->w66())->toBe(['dice' => [4, 3], 'total' => 43])
        ->and($dice->roll(1))->toBe([6])
        ->and(fn () => $dice->roll(0))->toThrow(InvalidArgumentException::class);
});
test('real dice are in range', function (): void {
    $dice = new CombatDice;
    expect($dice->roll())->each->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(6);
});
test('corrected weapon values come from page 41', function (): void {
    $profiles = RpgCharEditorEquipment::combatProfiles();
    foreach (['geworfener-stein' => [-2, 0], 'schleuder' => [-1, 0], 'bogen' => [-1, 1]] as $id => [$precision, $damage]) {
        expect($profiles[$id]['modes'][0]['precision'])->toBe($precision)
            ->and($profiles[$id]['modes'][0]['damage'])->toBe($damage);
    }
});
test('every ruling has an explicit default source and options', function (): void {
    foreach (RpgCombatRules::rulings() as $key => $rule) {
        expect($rule['id'])->toStartWith('SL-')
            ->and($rule['pages'])->not->toBeEmpty()
            ->and($rule['options'])->toHaveKey(RpgCombatRules::defaultRuling($key));
    }
    expect(fn () => RpgCombatRules::defaultRuling('unknown'))->toThrow(InvalidArgumentException::class);
});
test('every editor trait has an explicit combat explanation', function (): void {
    $advantages = array_keys(RpgCharEditorSpecialRules::advantages());
    $disadvantages = array_keys(RpgCharEditorSpecialRules::disadvantages());
    $rows = CombatTraits::describe(compact('advantages', 'disadvantages'));
    foreach ([...$advantages, ...$disadvantages] as $name) {
        expect($rows)->toHaveKey($name)
            ->and($rows[$name])->not->toBeEmpty();
    }
    expect($rows['Gesteigertes Attribut'])->toContain('kein zweiter Bonus')
        ->and($rows['Tiergefährte'])->toContain('Ausgeschlossen');
});
