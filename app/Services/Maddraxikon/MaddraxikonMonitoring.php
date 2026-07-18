<?php

namespace App\Services\Maddraxikon;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class MaddraxikonMonitoring
{
    public const SCHEDULER_HEARTBEAT_CACHE_KEY = 'maddraxikon:monitoring:scheduler-heartbeat';

    public function recordSchedulerHeartbeat(): CarbonImmutable
    {
        $recordedAt = CarbonImmutable::now('UTC');

        Cache::forever(
            self::SCHEDULER_HEARTBEAT_CACHE_KEY,
            $recordedAt->toIso8601String(),
        );

        return $recordedAt;
    }

    public function schedulerHeartbeat(): ?CarbonImmutable
    {
        $value = Cache::get(self::SCHEDULER_HEARTBEAT_CACHE_KEY);

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }
}
