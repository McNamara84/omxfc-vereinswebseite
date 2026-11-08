<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\ErrorReporting\ErrorNotificationThrottle;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array', 'error-reporting.throttle_seconds' => 900, 'error-reporting.throttle_count_ttl_seconds' => 86400]);
    Carbon::setTestNow('2026-08-02 14:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('it sends first occurrence and counts suppressed duplicates', function (): void {
    $throttle = app(ErrorNotificationThrottle::class);
    $first = $throttle->decide('fingerprint');
    $second = $throttle->decide('fingerprint');
    $third = $throttle->decide('fingerprint');
    expect($first->shouldSend)->toBeTrue()
        ->and($first->suppressedOccurrences)->toBe(0)
        ->and($second->shouldSend)->toBeFalse()
        ->and($third->shouldSend)->toBeFalse();
    Carbon::setTestNow(now()->addMinutes(16));
    $afterWindow = $throttle->decide('fingerprint');
    expect($afterWindow->shouldSend)->toBeTrue()
        ->and($afterWindow->suppressedOccurrences)->toBe(2);
});

test('it throttles fingerprints independently', function (): void {
    $throttle = app(ErrorNotificationThrottle::class);
    expect($throttle->decide('first')->shouldSend)->toBeTrue()
        ->and($throttle->decide('second')->shouldSend)->toBeTrue()
        ->and($throttle->decide('first')->shouldSend)->toBeFalse();
});

test('it fails open and logs when the cache is unavailable', function (): void {
    Log::spy();
    $cache = Mockery::mock(CacheRepository::class);
    $cache->shouldReceive('add')->once()->andThrow(new RuntimeException('Cache nicht erreichbar'));
    $decision = (new ErrorNotificationThrottle($cache))->decide('fingerprint');
    expect($decision->shouldSend)->toBeTrue()
        ->and($decision->suppressedOccurrences)->toBe(0);
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => str_contains($message, 'Cache') && $context['error_reporting_internal'] === true && $context['exception_message'] === 'Cache nicht erreichbar');
});
