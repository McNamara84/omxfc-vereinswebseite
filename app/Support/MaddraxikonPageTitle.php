<?php

namespace App\Support;

final class MaddraxikonPageTitle
{
    public static function fromUrl(string $url): ?string
    {
        if (! UriSupport::isAbsoluteUrlForHost($url, 'https', 'de.maddraxikon.com')) {
            return null;
        }

        $query = parse_url($url, PHP_URL_QUERY);
        $title = null;

        if (is_string($query)) {
            parse_str($query, $parameters);
            $title = $parameters['title'] ?? null;
        }

        if (! is_string($title)) {
            $path = parse_url($url, PHP_URL_PATH);

            if (is_string($path) && str_starts_with($path, '/wiki/')) {
                $title = rawurldecode(substr($path, strlen('/wiki/')));
            }
        }

        if (! is_string($title)) {
            return null;
        }

        $title = trim(str_replace('_', ' ', $title));

        if (
            $title === ''
            || ! mb_check_encoding($title, 'UTF-8')
            || mb_strlen($title) > 255
            || preg_match('/[\x00-\x1F\x7F]/u', $title)
        ) {
            return null;
        }

        return $title;
    }

    public static function databaseKey(string $title): ?string
    {
        if (
            ! mb_check_encoding($title, 'UTF-8')
            || preg_match('/[\x00-\x1F\x7F]/u', $title)
        ) {
            return null;
        }

        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? '');

        if (
            $title === ''
            || mb_strlen($title) > 255
        ) {
            return null;
        }

        return str_replace(' ', '_', $title);
    }

    public static function displayTitle(string $databaseTitle): string
    {
        return str_replace('_', ' ', $databaseTitle);
    }
}
