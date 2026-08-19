<?php

namespace Tests\Unit;

use App\Support\CoverRatings\CoverSyncInterval;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CoverSyncIntervalTest extends TestCase
{
    #[DataProvider('validIntervalProvider')]
    public function test_valid_day_divisors_are_preserved(int $hours): void
    {
        $this->assertSame($hours, CoverSyncInterval::normalize($hours));
        $this->assertSame(0, 24 % $hours);
    }

    public static function validIntervalProvider(): array
    {
        return array_combine(
            array_map(static fn (int $hours): string => "{$hours} hours", CoverSyncInterval::ALLOWED_HOURS),
            array_map(static fn (int $hours): array => [$hours], CoverSyncInterval::ALLOWED_HOURS),
        );
    }

    #[DataProvider('invalidIntervalProvider')]
    public function test_non_divisors_and_out_of_range_values_fall_back_to_daily(int $hours): void
    {
        $this->assertSame(CoverSyncInterval::DEFAULT_HOURS, CoverSyncInterval::normalize($hours));
    }

    public static function invalidIntervalProvider(): array
    {
        return [
            'zero' => [0],
            'five hours' => [5],
            'seven hours' => [7],
            'more than one day' => [25],
        ];
    }
}
