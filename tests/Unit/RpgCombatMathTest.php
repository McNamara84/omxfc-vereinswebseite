<?php

namespace Tests\Unit;

use App\Services\RpgCombat\CombatDice;
use App\Services\RpgCombat\CombatMath;
use App\Services\RpgCombat\CombatTraits;
use App\Support\RpgCharEditorEquipment;
use App\Support\RpgCharEditorSpecialRules;
use App\Support\RpgCombatRules;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RpgCombatMathTest extends TestCase
{
    public function test_rolls_keep_every_modifier_and_require_actual_success_or_failure(): void
    {
        $roll = CombatMath::roll([4, 3], ['Nahkampf' => 4, 'ST' => 1, 'Verletzung' => -1]);
        $this->assertSame(11, $roll['total']);
        $this->assertSame(-1, $roll['modifiers']['Verletzung']);
        $this->assertTrue(CombatMath::result($roll, 11)['success']);
        $this->assertFalse(CombatMath::result(CombatMath::roll([6, 6], []), 13)['critical']);
        $this->assertTrue(CombatMath::result(CombatMath::roll([6, 6], []), 12)['critical']);
        $this->assertFalse(CombatMath::result(CombatMath::roll([1, 1], ['FW' => 10]), 11)['fumble']);
        $this->assertTrue(CombatMath::result(CombatMath::roll([1, 1], []), 3)['fumble']);
        $this->assertFalse(CombatMath::result(CombatMath::roll([2], []), 7)['fumble']);
    }

    #[DataProvider('invalidRolls')]
    public function test_invalid_rolls_are_rejected(array $dice, array $modifiers): void
    {
        $this->expectException(InvalidArgumentException::class);
        CombatMath::roll($dice, $modifiers);
    }

    public static function invalidRolls(): array
    {
        return [[[], []], [[1, 2, 3], []], [[0, 4], []], [[7], []], [['6'], []], [[2.5], []], [[3], ['ST' => '2']]];
    }

    #[DataProvider('ranges')]
    public function test_range_boundaries_in_centimetres(int $distance, int $expected): void
    {
        $this->assertSame($expected, CombatMath::rangePenalty($distance, 800, 8000));
    }

    public static function ranges(): array
    {
        return [[0, 0], [800, 0], [801, -1], [1500, -1], [1600, -1], [1601, -2], [8000, -9]];
    }

    #[DataProvider('invalidRanges')]
    public function test_illegal_ranges_are_rejected(int $distance, int $increment, int $maximum): void
    {
        $this->expectException(InvalidArgumentException::class);
        CombatMath::rangePenalty($distance, $increment, $maximum);
    }

    public static function invalidRanges(): array
    {
        return [[-1, 100, 1000], [1, 0, 1000], [1001, 100, 1000]];
    }

    public function test_fire_rates_match_page_49_and_cannot_exceed_the_weapon(): void
    {
        $this->assertSame(['ammunition' => 15, 'attack' => -4, 'damage' => 1, 'area' => 6], CombatMath::fireMode('A', 'A'));
        $this->assertSame(['ammunition' => 2, 'attack' => -1, 'damage' => 0, 'area' => 1], CombatMath::fireMode('H', 'A'));
        $this->assertSame(6, CombatMath::fireMode('S', 'A')['ammunition']);
        $this->assertSame(1, CombatMath::fireMode('E', 'H')['ammunition']);
        foreach ([['S', 'H'], ['X', 'A'], ['E', 'X']] as [$mode, $maximum]) {
            try {
                CombatMath::fireMode($mode, $maximum);
                $this->fail('Unzulässige Feuerart akzeptiert.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_aimed_attacks_and_critical_damage_never_double_the_bonus(): void
    {
        $this->assertSame(3, CombatMath::aimedLimit(5));
        $this->assertSame(0, CombatMath::aimedLimit(-2));
        $this->assertSame(0, CombatMath::aimedLimit(0));
        $this->assertSame(0, CombatMath::criticalDamage(CombatMath::roll([6, 6], []), 12));
        $this->assertSame(1, CombatMath::criticalDamage(CombatMath::roll([6, 6], []), 11));
        $this->assertSame(1, CombatMath::criticalDamage(CombatMath::roll([6, 6], []), 8));
        $this->assertSame(0, CombatMath::criticalDamage(CombatMath::roll([4, 3], ['FW' => 7]), 11));
        $this->assertSame(1, CombatMath::criticalDamage(CombatMath::roll([4, 3], ['FW' => 7]), 10));
    }

    #[DataProvider('damageBoundaries')]
    public function test_wound_boundaries(int $damage, int $wound): void
    {
        $this->assertSame($wound, CombatMath::wound($damage));
    }

    public static function damageBoundaries(): array
    {
        return [[-10, 0], [0, 0], [1, 1], [2, 1], [3, 2], [4, 2], [5, 3], [6, 3], [7, 4], [20, 4]];
    }

    public function test_wound_accumulation_matches_the_book_example_and_both_published_rulings(): void
    {
        $this->assertSame(0, CombatMath::injuryModifier([1, 1, 1]));
        $this->assertSame(1, CombatMath::injuryModifier([2]));
        $this->assertSame(2, CombatMath::injuryModifier([2, 3]));
        $this->assertSame(3, CombatMath::injuryModifier([2, 3, 3]));
        $this->assertSame(2, CombatMath::injuryModifier([2, 2]));
        $this->assertSame(3, CombatMath::injuryModifier([2, 2, 2, 2]));
        $this->assertSame(3, CombatMath::injuryModifier([2, 3, 2]));
        $this->assertSame(2, CombatMath::injuryModifier([2, 3, 2], 'reset'));
        $this->assertSame([1, 3, 2], CombatMath::heal([1, 3, 3]));
        $this->assertSame([], CombatMath::heal([1]));
        $this->assertSame([], CombatMath::heal([]));
    }

    public function test_psychic_parameters_and_pyrokinetic_faq_example(): void
    {
        $parameters = CombatMath::psychicParameters(4, 1, 3, 4);
        $this->assertSame(10, $parameters['duration']);
        $this->assertSame(5000, $parameters['range']);
        $this->assertSame(40, $parameters['strength']);
        $this->assertSame(36, CombatMath::pyrokineticCost($parameters, 2, 4));
        $this->assertSame(0, CombatMath::psychicParameters(1, 0, 0, 0)['cost']);
        $this->assertSame(1, CombatMath::psychicParameters(1, 0, 0, 0)['strength']);
        $large = CombatMath::psychicParameters(9, 6, 6, 6);
        $this->assertSame(64800, $large['duration']);
        $this->assertSame(500000, $large['range']);
        $this->assertSame(160, $large['strength']);
    }

    #[DataProvider('invalidPsychicParameters')]
    public function test_invalid_psychic_parameters(int $fw, int $d, int $r, int $s): void
    {
        $this->expectException(InvalidArgumentException::class);
        CombatMath::psychicParameters($fw, $d, $r, $s);
    }

    public static function invalidPsychicParameters(): array
    {
        return [[0, 0, 0, 0], [1, -1, 0, 0], [1, 1, 1, 1], [20, 13, 0, 0]];
    }

    public function test_pyrokinetic_damage_is_bounded_and_rounded_down(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CombatMath::pyrokineticCost(['cost' => 1], 2, 3);
    }

    public function test_negative_pyrokinetic_damage_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CombatMath::pyrokineticCost(['cost' => 1], -1, 3);
    }

    public function test_w66_uses_tens_and_units_and_single_dice_do_not_become_2w6(): void
    {
        $dice = new class extends CombatDice
        {
            public array $values = [4, 3, 6];

            public function die(): int
            {
                return array_shift($this->values);
            }
        };
        $this->assertSame(['dice' => [4, 3], 'total' => 43], $dice->w66());
        $this->assertSame([6], $dice->roll(1));
        $this->expectException(InvalidArgumentException::class);
        $dice->roll(0);
    }

    public function test_real_dice_are_in_range(): void
    {
        $dice = new CombatDice;
        foreach ($dice->roll() as $die) {
            $this->assertGreaterThanOrEqual(1, $die);
            $this->assertLessThanOrEqual(6, $die);
        }
    }

    public function test_corrected_weapon_values_come_from_page_41(): void
    {
        $profiles = RpgCharEditorEquipment::combatProfiles();
        foreach (['geworfener-stein' => [-2, 0], 'schleuder' => [-1, 0], 'bogen' => [-1, 1]] as $id => [$precision, $damage]) {
            $this->assertSame($precision, $profiles[$id]['modes'][0]['precision']);
            $this->assertSame($damage, $profiles[$id]['modes'][0]['damage']);
        }
    }

    public function test_every_ruling_has_an_explicit_default_source_and_options(): void
    {
        foreach (RpgCombatRules::rulings() as $key => $rule) {
            $this->assertStringStartsWith('SL-', $rule['id']);
            $this->assertNotEmpty($rule['pages']);
            $this->assertArrayHasKey(RpgCombatRules::defaultRuling($key), $rule['options']);
        }
        $this->expectException(InvalidArgumentException::class);
        RpgCombatRules::defaultRuling('unknown');
    }

    public function test_every_editor_trait_has_an_explicit_combat_explanation(): void
    {
        $advantages = array_keys(RpgCharEditorSpecialRules::advantages());
        $disadvantages = array_keys(RpgCharEditorSpecialRules::disadvantages());
        $rows = CombatTraits::describe(compact('advantages', 'disadvantages'));
        foreach ([...$advantages, ...$disadvantages] as $name) {
            $this->assertArrayHasKey($name, $rows);
            $this->assertNotEmpty($rows[$name]);
        }
        $this->assertStringContainsString('kein zweiter Bonus', $rows['Gesteigertes Attribut']);
        $this->assertStringContainsString('Ausgeschlossen', $rows['Tiergefährte']);
    }
}
