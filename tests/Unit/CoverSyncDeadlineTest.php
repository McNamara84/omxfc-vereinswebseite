<?php

namespace Tests\Unit;

use App\Exceptions\CoverSyncTimeoutException;
use App\Services\CoverRatings\CoverSyncDeadline;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class CoverSyncDeadlineTest extends TestCase
{
    public function test_cli_budget_leaves_time_before_the_two_hour_lock_expires(): void
    {
        $deadline = new CoverSyncDeadline;
        $deadline->ensureRemaining();
        $this->assertGreaterThan(6599, $deadline->remainingSeconds());
        $this->assertLessThanOrEqual(6600, $deadline->remainingSeconds());
        $this->assertSame(15.0, $deadline->timeout(15));
    }

    #[TestWith([0])]
    #[TestWith([-1])]
    public function test_expired_budgets_fail_instead_of_disabling_http_timeouts(int $seconds): void
    {
        $deadline = new CoverSyncDeadline($seconds);
        $this->assertSame(0.0, $deadline->remainingSeconds());
        $this->expectException(CoverSyncTimeoutException::class);
        $deadline->timeout(15);
    }

    public function test_http_timeout_is_capped_and_never_zero(): void
    {
        $deadline = new CoverSyncDeadline(5);
        $this->assertGreaterThan(4, $deadline->timeout(300));
        $this->assertLessThanOrEqual(5, $deadline->timeout(300));
        $this->assertSame(1.0, $deadline->timeout(0));
    }

    public function test_backoff_must_fit_in_the_remaining_budget(): void
    {
        $this->expectException(CoverSyncTimeoutException::class);
        (new CoverSyncDeadline(1))->ensureRemaining(5);
    }
}
