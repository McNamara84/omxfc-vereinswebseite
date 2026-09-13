<?php

namespace App\Services\Maddraxikon;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

final class MaddraxikonRefreshLock
{
    private const NAME = 'maddraxikon-books-refresh';

    private const LEASE_SAFETY_MARGIN_SECONDS = 300;

    public function runtimeLimitSeconds(): int
    {
        return max(60, (int) config('maddraxikon.crawler.max_runtime_seconds', 1800));
    }

    public function acquire(): ?Lock
    {
        $minimumLease = $this->runtimeLimitSeconds() + self::LEASE_SAFETY_MARGIN_SECONDS;
        $lock = Cache::lock(
            self::NAME,
            max($minimumLease, (int) config('maddraxikon.crawler.lock_seconds', 3600)),
        );

        return $lock->get() ? $lock : null;
    }
}
