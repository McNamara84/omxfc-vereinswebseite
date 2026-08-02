<?php

namespace App\Services\ErrorReporting;

use App\Data\ErrorReporting\ThrottleDecision;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

class ErrorNotificationThrottle
{
    public function __construct(private readonly CacheRepository $cache) {}

    public function decide(string $fingerprint): ThrottleDecision
    {
        $windowSeconds = max(1, (int) config('error-reporting.throttle_seconds', 900));
        $counterTtl = max($windowSeconds, (int) config('error-reporting.throttle_count_ttl_seconds', 604800));
        $windowKey = $this->key($fingerprint, 'window');
        $counterKey = $this->key($fingerprint, 'suppressed');

        try {
            if ($this->cache->add($windowKey, true, $windowSeconds)) {
                $suppressed = $this->cache->pull($counterKey, 0);

                return new ThrottleDecision(true, max(0, (int) $suppressed));
            }

            $this->cache->add($counterKey, 0, $counterTtl);
            $this->cache->increment($counterKey);
            $this->cache->touch($counterKey, $counterTtl);

            return new ThrottleDecision(false);
        } catch (Throwable $exception) {
            $this->logCacheFailure($exception);

            return new ThrottleDecision(true);
        }
    }

    private function key(string $fingerprint, string $suffix): string
    {
        return 'error-reporting:'.$fingerprint.':'.$suffix;
    }

    private function logCacheFailure(Throwable $exception): void
    {
        try {
            Log::warning('Fehlerbericht-Drosselung konnte nicht auf den Cache zugreifen.', [
                'error_reporting_internal' => true,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);
        } catch (Throwable) {
            // Das Fehlerreporting darf die ursprüngliche Exception niemals überdecken.
        }
    }
}
