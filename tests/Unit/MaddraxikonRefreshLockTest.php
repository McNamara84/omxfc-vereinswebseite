<?php

namespace Tests\Unit;

use App\Services\Maddraxikon\MaddraxikonRefreshLock;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaddraxikonRefreshLockTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('leaseProvider')]
    public function test_lock_lease_outlives_the_configured_refresh_runtime(
        int $runtimeSeconds,
        int $configuredLeaseSeconds,
        int $expectedLeaseSeconds,
    ): void {
        config([
            'maddraxikon.crawler.max_runtime_seconds' => $runtimeSeconds,
            'maddraxikon.crawler.lock_seconds' => $configuredLeaseSeconds,
        ]);
        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('get')->once()->andReturnTrue();
        Cache::shouldReceive('lock')
            ->once()
            ->with('maddraxikon-books-refresh', $expectedLeaseSeconds)
            ->andReturn($lock);

        $service = new MaddraxikonRefreshLock;

        $this->assertSame($runtimeSeconds, $service->runtimeLimitSeconds());
        $this->assertSame($lock, $service->acquire());
    }

    /** @return iterable<string, array{int, int, int}> */
    public static function leaseProvider(): iterable
    {
        yield 'undersized lease gets runtime safety margin' => [1800, 60, 2100];
        yield 'longer configured lease is preserved' => [1800, 3600, 3600];
        yield 'longer runtime also extends lease' => [3600, 3600, 3900];
    }
}
