<?php

namespace App\Services\Maddraxikon;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

final class MaddraxikonRefreshLock
{
    private const NAME = 'maddraxikon-books-refresh';

    public function acquire(): ?Lock
    {
        $lock = Cache::lock(
            self::NAME,
            max(60, (int) config('maddraxikon.crawler.lock_seconds', 3600)),
        );

        return $lock->get() ? $lock : null;
    }
}
