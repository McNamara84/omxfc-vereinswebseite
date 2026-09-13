<?php

namespace App\Services\Maddraxikon;

use App\Exceptions\MaddraxikonCrawlException;
use App\Support\UriSupport;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MaddraxikonCrawlerHttpClient
{
    public function get(
        string $url,
        ?MaddraxikonCrawlDeadline $deadline = null,
    ): string {
        $this->assertTrustedUrl($url);
        $deadline?->ensureNotExpired($url);

        $attempts = max(1, (int) config('maddraxikon.http.attempts', 3));
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $deadline?->ensureNotExpired($url);
            $startedAt = microtime(true);

            try {
                $response = Http::withUserAgent((string) config(
                    'maddraxikon.http.user_agent',
                    'OMXFC-Vereinswebsite/1.0'
                ))
                    ->accept('text/html,application/xhtml+xml')
                    ->connectTimeout($this->timeoutWithinDeadline(
                        (int) config('maddraxikon.http.connect_timeout', 5),
                        $deadline,
                        $url,
                    ))
                    ->timeout($this->timeoutWithinDeadline(
                        (int) config('maddraxikon.http.timeout', 15),
                        $deadline,
                        $url,
                    ))
                    ->withOptions(['allow_redirects' => false])
                    ->get($url);

                if ($response->successful()) {
                    $deadline?->ensureNotExpired($url);

                    return $response->body();
                }

                $exception = new MaddraxikonCrawlException(
                    "Maddraxikon antwortete für {$url} mit HTTP {$response->status()}.",
                    $url,
                    $response->status(),
                );

                $this->logFailure($url, $attempt, $attempts, $startedAt, $response->status());

                if ($this->isTransientStatus($response->status()) && $attempt < $attempts) {
                    $lastException = $exception;
                    $this->waitBeforeRetry($attempt, $url, $deadline, $response);

                    continue;
                }

                throw $exception;
            } catch (ConnectionException $exception) {
                $lastException = $exception;
                $this->logFailure($url, $attempt, $attempts, $startedAt);
                $deadline?->ensureNotExpired($url);

                if ($attempt < $attempts) {
                    $this->waitBeforeRetry($attempt, $url, $deadline);

                    continue;
                }
            }
        }

        throw new MaddraxikonCrawlException(
            'Maddraxikon ist nach mehreren Versuchen nicht erreichbar: '.
            ($lastException?->getMessage() ?? 'unbekannter Netzwerkfehler'),
            $url,
        );
    }

    private function assertTrustedUrl(string $url): void
    {
        $baseUrl = rtrim((string) config(
            'maddraxikon.base_url',
            'https://de.maddraxikon.com'
        ), '/');
        $parts = parse_url($baseUrl);
        $scheme = is_array($parts) ? (string) ($parts['scheme'] ?? 'https') : '';
        $host = is_array($parts) ? (string) ($parts['host'] ?? 'de.maddraxikon.com') : '';
        $port = is_array($parts) && isset($parts['port']) ? (int) $parts['port'] : 443;

        if (
            strtolower($scheme) !== 'https'
            || ! UriSupport::isAbsoluteUrlForHost($url, 'https', $host, $port)
        ) {
            throw new MaddraxikonCrawlException(
                'Nicht erlaubte Maddraxikon-URL.',
            );
        }
    }

    private function isTransientStatus(int $status): bool
    {
        return in_array($status, [408, 425, 429], true) || $status >= 500;
    }

    private function waitBeforeRetry(
        int $attempt,
        string $url,
        ?MaddraxikonCrawlDeadline $deadline = null,
        ?Response $response = null,
    ): void {
        $baseDelayMs = max(0, (int) config('maddraxikon.http.retry_delay_ms', 500));
        $maximumDelayMs = max(0, (int) config('maddraxikon.http.retry_max_delay_ms', 5000));

        if ($maximumDelayMs === 0) {
            return;
        }

        $backoffMs = $baseDelayMs * (2 ** ($attempt - 1));
        $retryAfterMs = $response instanceof Response
            ? $this->retryAfterDelayMs($response)
            : 0;
        $delayMs = min($maximumDelayMs, max($backoffMs, $retryAfterMs));

        if ($delayMs > 0) {
            $jitterMs = random_int(0, min(250, max(1, intdiv($delayMs, 5))));
            $sleepMs = min($maximumDelayMs, $delayMs + $jitterMs);

            if ($deadline instanceof MaddraxikonCrawlDeadline) {
                $sleepMs = min(
                    $sleepMs,
                    max(0, (int) floor($deadline->remainingSeconds() * 1000)),
                );
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $deadline?->ensureNotExpired($url);
    }

    private function timeoutWithinDeadline(
        int $configuredSeconds,
        ?MaddraxikonCrawlDeadline $deadline,
        string $url,
    ): float {
        $timeout = (float) max(1, $configuredSeconds);

        if (! $deadline instanceof MaddraxikonCrawlDeadline) {
            return $timeout;
        }

        $deadline->ensureNotExpired($url);

        return max(0.001, min($timeout, $deadline->remainingSeconds()));
    }

    private function retryAfterDelayMs(Response $response): int
    {
        $retryAfter = trim((string) $response->header('Retry-After'));

        if ($retryAfter === '') {
            return 0;
        }

        if (ctype_digit($retryAfter)) {
            return (int) $retryAfter * 1000;
        }

        $retryAt = strtotime($retryAfter);

        return $retryAt === false ? 0 : max(0, ($retryAt - time()) * 1000);
    }

    private function logFailure(
        string $url,
        int $attempt,
        int $attempts,
        float $startedAt,
        ?int $status = null,
    ): void {
        Log::warning('Maddraxikon-Crawl-Abruf fehlgeschlagen.', [
            'url' => $url,
            'status' => $status,
            'attempt' => $attempt,
            'attempts' => $attempts,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }
}
