<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\CoverSyncTimeoutException;
use App\Services\CoverRatings\CoverSyncDeadline;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class);

test('cli budget leaves time before the two hour lock expires', function (): void {
    $deadline = new CoverSyncDeadline;
    $deadline->ensureRemaining();
    expect($deadline->remainingSeconds())->toBeGreaterThan(6599)
        ->and($deadline->remainingSeconds())->toBeLessThanOrEqual(6600)
        ->and($deadline->timeout(15))->toBe(15.0);
});

test('expired budgets fail instead of disabling http timeouts', function (int $seconds): void {
    $deadline = new CoverSyncDeadline($seconds);
    expect($deadline->remainingSeconds())->toBe(0.0)
        ->and(fn () => $deadline->timeout(15))->toThrow(CoverSyncTimeoutException::class);
})->with([[0], [-1]]);

test('http timeout is capped and never zero', function (): void {
    $deadline = new CoverSyncDeadline(5);
    expect($deadline->timeout(300))->toBeGreaterThan(4)
        ->and($deadline->timeout(300))->toBeLessThanOrEqual(5)
        ->and($deadline->timeout(0))->toBe(1.0);
});

test('backoff must fit in the remaining budget', function (): void {
    expect(fn () => (new CoverSyncDeadline(1))->ensureRemaining(5))->toThrow(CoverSyncTimeoutException::class);
});
