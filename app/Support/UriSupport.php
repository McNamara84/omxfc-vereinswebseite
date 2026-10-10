<?php

namespace App\Support;

use Illuminate\Support\Str;
use Throwable;
use Uri\Rfc3986\Uri;

final class UriSupport
{
    public static function normalizeAbsoluteHttpUrl(string $uri): ?string
    {
        $parsed = self::parse($uri);

        if ($parsed === null) {
            return null;
        }

        $scheme = $parsed->getScheme();

        if (! in_array($scheme, ['http', 'https'], true) || ! self::hasNonEmptyHost($parsed)) {
            return null;
        }

        return $parsed->toString();
    }

    public static function isAbsoluteUrlForHost(
        string $uri,
        string $scheme,
        string $host,
        ?int $port = null,
    ): bool {
        $parsed = self::parse($uri);

        if (
            $parsed === null
            || $parsed->getUserInfo() !== null
        ) {
            return false;
        }

        $expectedScheme = strtolower($scheme);
        if ($parsed->getScheme() !== $expectedScheme
            || ! self::hasNonEmptyHost($parsed)
            || $parsed->getHost() !== strtolower($host)) {
            return false;
        }

        $defaultPorts = ['http' => 80, 'https' => 443];
        $actualPort = $parsed->getPort() ?? ($defaultPorts[$expectedScheme] ?? null);
        $expectedPort = $port ?? ($defaultPorts[$expectedScheme] ?? null);

        return $actualPort === $expectedPort;
    }

    public static function resolve(string $base, string $reference): ?string
    {
        $parsedBase = self::parse($base);

        if ($parsedBase === null
            || ! in_array($parsedBase->getScheme(), ['http', 'https'], true)
            || ! self::hasNonEmptyHost($parsedBase)) {
            return null;
        }

        try {
            return $parsedBase->resolve($reference)->toString();
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

        if ($scheme !== null) {
            return match ($scheme) {
                'http', 'https' => self::hasNonEmptyHost($parsed),
                'mailto' => $parsed->getPath() !== '',
                default => false,
            };
        }

        if ($parsed->getHost() !== null) {
            return false;
        }

        $isHashLink = Str::startsWith($href, '#');
        $isRelativePath = Str::startsWith($href, ['/', './', '../']);
        $looksLikeFile = preg_match('/^[A-Za-z_][A-Za-z0-9._\-\/]*([?#][^\s]*)?$/', $href) === 1;

        return $isHashLink || $isRelativePath || $looksLikeFile;
    }

    private static function parse(string $uri): ?Uri
    {
        try {
            // RFC 3986 rejects control characters, whitespace and backslashes,
            // and normalizes scheme/host casing. Keep the native parser as the
            // single validation boundary; invalid syntax returns null.
            return Uri::parse($uri);
        } catch (Throwable) {
            return null;
        }
    }

    private static function hasNonEmptyHost(Uri $uri): bool
    {
        return $uri->getHost() !== null && $uri->getHost() !== '';
    }
}
