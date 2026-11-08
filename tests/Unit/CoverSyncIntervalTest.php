<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CoverRatings\CoverSyncInterval;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class);
dataset('CoverSyncIntervalTest.validIntervalProvider', static fn (): array => array_combine(array_map(static fn (int $hours): string => "{$hours} hours", CoverSyncInterval::ALLOWED_HOURS), array_map(static fn (int $hours): array => [$hours], CoverSyncInterval::ALLOWED_HOURS)));
dataset('CoverSyncIntervalTest.invalidIntervalProvider', static fn (): array => ['zero' => [0], 'five hours' => [5], 'seven hours' => [7], 'more than one day' => [25]]);
test('valid day divisors are preserved', function (int $hours): void {
    expect(CoverSyncInterval::normalize($hours))->toBe($hours)
        ->and(24 % $hours)->toBe(0);
})->with('CoverSyncIntervalTest.validIntervalProvider');
test('non divisors and out of range values fall back to daily', function (int $hours): void {
    expect(CoverSyncInterval::normalize($hours))->toBe(CoverSyncInterval::DEFAULT_HOURS);
})->with('CoverSyncIntervalTest.invalidIntervalProvider');
