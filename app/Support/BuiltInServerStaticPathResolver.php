<?php

namespace App\Support;

final class BuiltInServerStaticPathResolver
{
    public static function resolve(string $projectRoot, string $requestPath): ?string
    {
        $normalizedRequestPath = self::normalizeRequestPath($requestPath);

        if ($normalizedRequestPath === null || $normalizedRequestPath === '/') {
            return null;
        }

        $publicPath = self::buildProjectPath($projectRoot, 'public', $normalizedRequestPath);

        if ($publicPath !== null && is_file($publicPath)) {
            return $publicPath;
        }

        if (! str_starts_with($normalizedRequestPath, '/storage/')) {
            return null;
        }

        return self::resolveStoragePath($projectRoot, substr($normalizedRequestPath, strlen('/storage/')));
    }

    public static function normalizeRequestPath(string $requestPath): ?string
    {
        if ($requestPath === '' || ! str_starts_with($requestPath, '/')) {
            return null;
        }

        if (str_contains($requestPath, '\\')) {
            return null;
        }

        $normalizedSegments = [];

        foreach (explode('/', $requestPath) as $index => $segment) {
            if ($index === 0 || $segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                return null;
            }

            $normalizedSegments[] = $segment;
        }

        return '/'.implode('/', $normalizedSegments);
    }

    private static function resolveStoragePath(string $projectRoot, string $relativePath): ?string
    {
        if ($relativePath === '' || str_contains($relativePath, '\\')) {
            return null;
        }

        $storageRoot = realpath(self::buildProjectPath($projectRoot, 'storage/app/public', '/'));

        if ($storageRoot === false) {
            return null;
        }

        $candidatePath = $storageRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, trim($relativePath, '/'));
        $resolvedPath = realpath($candidatePath);

        if ($resolvedPath === false || ! is_file($resolvedPath)) {
            return null;
        }

        return self::pathIsWithinBase($resolvedPath, $storageRoot) ? $resolvedPath : null;
    }

    private static function buildProjectPath(string $projectRoot, string $baseDirectory, string $requestPath): ?string
    {
        $relativePath = ltrim($requestPath, '/');
        $basePath = rtrim($projectRoot, '/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $baseDirectory);

        if ($relativePath === '') {
            return $basePath;
        }

        return $basePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    private static function pathIsWithinBase(string $resolvedPath, string $basePath): bool
    {
        $normalizedBasePath = rtrim(str_replace('\\', '/', $basePath), '/');
        $normalizedResolvedPath = str_replace('\\', '/', $resolvedPath);

        return $normalizedResolvedPath === $normalizedBasePath
            || str_starts_with($normalizedResolvedPath, $normalizedBasePath.'/');
    }
}