<?php

namespace Tests\Unit;

use App\Services\ErrorReporting\ErrorNotificationThrottle;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ErrorNotificationThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'error-reporting.throttle_seconds' => 900,
            'error-reporting.throttle_count_ttl_seconds' => 86400,
        ]);
        Carbon::setTestNow('2026-08-02 14:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_sends_first_occurrence_and_counts_suppressed_duplicates(): void
    {
        $throttle = app(ErrorNotificationThrottle::class);

        $first = $throttle->decide('fingerprint');
        $second = $throttle->decide('fingerprint');
        $third = $throttle->decide('fingerprint');

        $this->assertTrue($first->shouldSend);
        $this->assertSame(0, $first->suppressedOccurrences);
        $this->assertFalse($second->shouldSend);
        $this->assertFalse($third->shouldSend);

        Carbon::setTestNow(now()->addMinutes(16));
        $afterWindow = $throttle->decide('fingerprint');

        $this->assertTrue($afterWindow->shouldSend);
        $this->assertSame(2, $afterWindow->suppressedOccurrences);
    }

    public function test_it_throttles_fingerprints_independently(): void
    {
        $throttle = app(ErrorNotificationThrottle::class);

        $this->assertTrue($throttle->decide('first')->shouldSend);
        $this->assertTrue($throttle->decide('second')->shouldSend);
        $this->assertFalse($throttle->decide('first')->shouldSend);
    }

    public function test_it_fails_open_and_logs_when_the_cache_is_unavailable(): void
    {
        Log::spy();
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('add')
            ->once()
            ->andThrow(new RuntimeException('Cache nicht erreichbar'));

        $decision = (new ErrorNotificationThrottle($cache))->decide('fingerprint');

        $this->assertTrue($decision->shouldSend);
        $this->assertSame(0, $decision->suppressedOccurrences);
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'Cache')
                && $context['error_reporting_internal'] === true
                && $context['exception_message'] === 'Cache nicht erreichbar');
    }
}
