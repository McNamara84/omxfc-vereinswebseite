<?php

namespace Tests\Unit;

use App\Services\Maddraxikon\MaddraxikonStreakCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MaddraxikonStreakCalculatorTest extends TestCase
{
    #[DataProvider('days')]
    public function test_calendar_state_transition(string $status, ?int $previous, ?int $expected): void
    {
        $this->assertSame($expected, (new MaddraxikonStreakCalculator)->length($status, $previous));
    }

    public static function days(): array
    {
        return [['valid', 0, 1], ['valid', 4, 5], ['valid', 365, 366], ['valid', null, null], ['pending', 5, null], ['pending', null, null], ['inactive', 5, 0], ['inactive', null, 0]];
    }
}
