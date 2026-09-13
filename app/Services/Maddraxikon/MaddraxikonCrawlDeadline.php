<?php

namespace App\Services\Maddraxikon;

use App\Exceptions\MaddraxikonCrawlException;

final readonly class MaddraxikonCrawlDeadline
{
    private function __construct(
        private float $expiresAt,
    ) {}

    public static function afterSeconds(int $seconds): self
    {
        return new self(microtime(true) + max(0, $seconds));
    }

    public function remainingSeconds(): float
    {
        return max(0.0, $this->expiresAt - microtime(true));
    }

    public function ensureNotExpired(?string $url = null): void
    {
        if ($this->remainingSeconds() > 0.0) {
            return;
        }

        throw new MaddraxikonCrawlException(
            'Maximale Maddraxikon-Crawl-Laufzeit wurde überschritten.',
            $url,
        );
    }
}
