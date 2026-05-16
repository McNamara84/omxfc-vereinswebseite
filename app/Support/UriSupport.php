<?php

namespace App\Support;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Str;
use Throwable;

final class UriSupport
{
    public static function normalizeAbsoluteHttpUrl(string $uri): ?string
    {
        $parsed = self::parse($uri);

        if ($parsed === null) {
            return null;
        }

        $scheme = strtolower((string) $parsed->getScheme());

        if (! in_array($scheme, ['http', 'https'], true) || ! self::hasNonEmptyHost($parsed)) {
            return null;
        }

        return (string) $parsed;
    }

    public static function isAbsoluteUrlForHost(string $uri, string $scheme, string $host): bool
    {
        $parsed = self::parse($uri);

        if ($parsed === null || ! self::hasNonEmptyHost($parsed)) {
            return false;
        }

        return strtolower((string) $parsed->getScheme()) === strtolower($scheme)
            && strtolower((string) $parsed->getHost()) === strtolower($host);
    }

    public static function resolve(string $base, string $reference): ?string
    {
        $normalizedBase = self::normalizeAbsoluteHttpUrl($base);

        if ($normalizedBase === null) {
            return null;
        }

        try {
            return (string) UriResolver::resolve(new Uri($normalizedBase), new Uri($reference));
        } catch (Throwable) {
            return null;
        }
    }

    public static function isSafeMarkdownHref(string $href): bool
    {
        $parsed = self::parse($href);

        if ($parsed === null) {
            return false;
        }

        $scheme = $parsed->getScheme();

        if ($scheme !== '') {
            return match (strtolower($scheme)) {
                'http', 'https' => self::hasNonEmptyHost($parsed),
                'mailto' => $parsed->getPath() !== '',
                default => false,
            };
        }

        if (self::hasNonEmptyHost($parsed)) {
            return false;
        }

        $trimmedHref = ltrim($href);
        $isHashLink = Str::startsWith($trimmedHref, '#');
        $isRelativePath = Str::startsWith($trimmedHref, ['/', './', '../']);
        $looksLikeFile = preg_match('/^[A-Za-z_][A-Za-z0-9._\-\/]*([?#][^\s]*)?$/', $trimmedHref) === 1;

        return $isHashLink || $isRelativePath || $looksLikeFile;
    }

    private static function parse(string $uri): ?Uri
    {
        try {
            return new Uri($uri);
        } catch (Throwable) {
            return null;
        }
    }

    private static function hasNonEmptyHost(Uri $uri): bool
    {
        return $uri->getHost() !== '';
    }
}
