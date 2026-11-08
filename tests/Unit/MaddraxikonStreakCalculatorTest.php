<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Maddraxikon\MaddraxikonStreakCalculator;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class);
dataset('MaddraxikonStreakCalculatorTest.days', static fn (): array => [['valid', 0, 1], ['valid', 4, 5], ['valid', 365, 366], ['valid', null, null], ['pending', 5, null], ['pending', null, null], ['inactive', 5, 0], ['inactive', null, 0]]);
test('calendar state transition', function (string $status, ?int $previous, ?int $expected): void {
    expect((new MaddraxikonStreakCalculator)->length($status, $previous))->toBe($expected);
})->with('MaddraxikonStreakCalculatorTest.days');
