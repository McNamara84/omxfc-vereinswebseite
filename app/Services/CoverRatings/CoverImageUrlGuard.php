<?php

namespace App\Services\CoverRatings;

use App\Exceptions\CoverImageException;

class CoverImageUrlGuard
{
    public function assertAllowed(string $url): void
    {
        $parts = parse_url($url);

        if (
            ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
        ) {
            throw new CoverImageException('Die Cover-Bildquelle ist nicht sicher konfiguriert.');
        }

        $allowed = collect(config('cover-ratings.images.allowed_origins', []))
            ->contains(fn (mixed $origin): bool => is_string($origin)
                && $this->matchesOrigin($parts, $origin));

        if (! $allowed) {
            throw new CoverImageException('Die Cover-Bildquelle gehört nicht zu einer erlaubten Origin.');
        }
    }

    /** @param array<string, mixed> $urlParts */
    private function matchesOrigin(array $urlParts, string $origin): bool
    {
        $originParts = parse_url(rtrim($origin, '/'));

        if (
            ! is_array($originParts)
            || strtolower((string) ($originParts['scheme'] ?? '')) !== 'https'
            || ! isset($originParts['host'])
            || isset($originParts['user'])
            || isset($originParts['pass'])
            || isset($originParts['query'])
            || isset($originParts['fragment'])
            || (($originParts['path'] ?? '') !== '' && ($originParts['path'] ?? '') !== '/')
        ) {
            return false;
        }

        $urlPort = $urlParts['port'] ?? 443;
        $originPort = $originParts['port'] ?? 443;

        return strcasecmp((string) $urlParts['host'], (string) $originParts['host']) === 0
            && $urlPort === $originPort;
    }
}
