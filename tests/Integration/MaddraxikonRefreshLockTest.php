<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\Maddraxikon\MaddraxikonRefreshLock;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);
dataset('MaddraxikonRefreshLockTest.leaseProvider', static function (): iterable {
    yield 'undersized lease gets runtime safety margin' => [1800, 60, 2100];
    yield 'longer configured lease is preserved' => [1800, 3600, 3600];
    yield 'longer runtime also extends lease' => [3600, 3600, 3900];
});
test('lock lease outlives the configured refresh runtime', function (int $runtimeSeconds, int $configuredLeaseSeconds, int $expectedLeaseSeconds): void {
    config(['maddraxikon.crawler.max_runtime_seconds' => $runtimeSeconds, 'maddraxikon.crawler.lock_seconds' => $configuredLeaseSeconds]);
    $lock = Mockery::mock(Lock::class);
    $lock->shouldReceive('get')->once()->andReturnTrue();
    Cache::shouldReceive('lock')->once()->with('maddraxikon-books-refresh', $expectedLeaseSeconds)->andReturn($lock);
    $service = new MaddraxikonRefreshLock;
    expect($service->runtimeLimitSeconds())->toBe($runtimeSeconds)
        ->and($service->acquire())->toBe($lock);
})->with('MaddraxikonRefreshLockTest.leaseProvider');
