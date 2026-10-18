<?php

namespace App\Services\CoverRatings;

use App\Exceptions\CoverSyncTimeoutException;

class CoverSyncDeadline
{
    private readonly float $expiresAt;

    public function __construct(int $seconds = 6600)
    {
        $this->expiresAt = hrtime(true) / 1e9 + max(0, $seconds);
    }

    public function remainingSeconds(): float
    {
        return max(0.0, $this->expiresAt - hrtime(true) / 1e9);
    }

    public function ensureRemaining(float $seconds = 0): void
    {
        if ($this->remainingSeconds() <= $seconds) {
            throw new CoverSyncTimeoutException;
        }
    }

    public function timeout(int $configuredSeconds): float
    {
        $this->ensureRemaining();

        // A zero HTTP timeout would disable the transport's time limit.
        return max(0.001, min(max(1, $configuredSeconds), $this->remainingSeconds()));
    }
}
