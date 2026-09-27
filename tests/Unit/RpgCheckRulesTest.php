<?php

namespace Tests\Unit;

use App\Services\RpgCheckCalculator;
use App\Services\RpgCheckDice;
use App\Support\RpgCheckRules;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RpgCheckRulesTest extends TestCase
{
    public static function examples(): array
    {
        return [
            'attribute rulebook example' => ['attribute', 1, null, 0, [3, 6], 12, 12, 'success'],
            'attribute equality' => ['attribute', 1, null, 0, [4, 3], 10, 10, 'success'],
            'skill with negative modifier' => ['skill', 1, 2, -1, [4, 3], 10, 9, 'failure'],
            'untrained with negative attribute' => ['skill', -2, 0, 0, [2, 3], 4, 3, 'failure'],
            'negative total' => ['attribute', -2, null, -4, [1, 2], -6, -7, 'failure'],
            'critical' => ['skill', 0, 2, 0, [6, 6], 14, 14, 'critical_success'],
            'twelve is no automatic success' => ['skill', 0, 0, 0, [6, 6], 14, 12, 'failure'],
            'fumble' => ['attribute', 0, null, 0, [1, 1], 7, 2, 'fumble'],
            'two can succeed' => ['skill', 2, 6, 0, [1, 1], 10, 10, 'success'],
        ];
    }

    #[DataProvider('examples')]
    public function test_rulebook_examples(string $type, int $attribute, ?int $skill, int $modifier, array $dice, int $difficulty, int $total, string $kind): void
    {
        $calculator = new RpgCheckCalculator;
        $roll = $calculator->roll($calculator->base($type, $attribute, $skill, $modifier), $dice);
        $this->assertSame($total, $roll['total']);
        $this->assertSame($kind, $calculator->result($roll['raw_total'], $roll['total'] - $difficulty));
    }

    public function test_presets_match_page_twelve(): void
    {
        $this->assertSame(['Leicht' => 7, 'Mittel' => 10, 'Schwer' => 11, 'Extrem' => 12, 'Legendär' => 14], RpgCheckRules::DIFFICULTIES);
    }

    public static function invalidDice(): array
    {
        return [[[0, 6]], [[7, 1]], [[1]], [[1, 2, 3]], [['1', 2]]];
    }

    #[DataProvider('invalidDice')]
    public function test_invalid_dice_are_rejected(array $dice): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new RpgCheckCalculator)->roll(0, $dice);
    }

    public function test_secure_dice_only_produce_two_w6(): void
    {
        foreach (range(1, 50) as $_) {
            $dice = (new RpgCheckDice)->roll();
            $this->assertCount(2, $dice);
            foreach ($dice as $die) {
                $this->assertContains($die, [1, 2, 3, 4, 5, 6]);
            }
        }
    }
}
