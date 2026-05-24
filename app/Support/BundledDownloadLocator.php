<?php

namespace App\Support;

final class BundledDownloadLocator
{
    public static function normalizeStoragePath(string $path): ?string
    {
        $path = str_replace('\\', '/', $path);

        if ($path === '' || str_starts_with($path, '/')) {
            return null;
        }

        if (preg_match('/^[A-Za-z]:\//', $path) === 1) {
            return null;
        }

        if (! str_starts_with($path, 'downloads/')) {
            return null;
        }

        $segments = explode('/', $path);

        if (in_array('.', $segments, true) || in_array('..', $segments, true) || in_array('', $segments, true)) {
            return null;
        }

        return $path;
    }

    public static function sourcePath(string $path): ?string
    {
        $normalizedPath = self::normalizeStoragePath($path);

        if ($normalizedPath === null) {
            return null;
        }

        $downloadsRoot = realpath(resource_path('downloads'));
        $sourcePath = realpath(resource_path($normalizedPath));

        if ($downloadsRoot === false || $sourcePath === false || ! is_file($sourcePath)) {
            return null;
        }

        $downloadsRoot = str_replace('\\', '/', $downloadsRoot);
        $sourcePath = str_replace('\\', '/', $sourcePath);

        if ($sourcePath !== $downloadsRoot && ! str_starts_with($sourcePath, $downloadsRoot.'/')) {
            return null;
        }

        return $sourcePath;
    }

    public static function fileSize(string $path): ?int
    {
        $sourcePath = self::sourcePath($path);

        if ($sourcePath === null) {
            return null;
        }

        $fileSize = filesize($sourcePath);

        return $fileSize === false ? null : $fileSize;
    }
}