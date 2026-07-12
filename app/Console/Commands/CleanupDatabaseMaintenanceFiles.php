<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanupDatabaseMaintenanceFiles extends Command
{
    protected $signature = 'database-maintenance:cleanup';

    protected $description = 'Loescht alte Datenbank-Wartungsdumps und temporaere Dateien.';

    public function handle(): int
    {
        $root = $this->safeStorageRoot();

        if ($root === null) {
            $this->warn('database-maintenance.storage_root ist ungueltig oder liegt nicht unter storage_path(). Cleanup abgebrochen.');

            return self::FAILURE;
        }

        $retentionDays = max(1, (int) config('database-maintenance.pre_restore_retention_days', 7));

        $deleted = 0;
        $deleted += $this->deleteOlderThan($root.DIRECTORY_SEPARATOR.'pre-restore', now()->subDays($retentionDays)->getTimestamp());
        $deleted += $this->deleteOlderThan($root.DIRECTORY_SEPARATOR.'downloads', now()->subDay()->getTimestamp());
        $deleted += $this->deleteOlderThan($root.DIRECTORY_SEPARATOR.'uploads', now()->subDay()->getTimestamp());
        $deleted += $this->deleteOlderThan($root.DIRECTORY_SEPARATOR.'temp', now()->subDay()->getTimestamp());

        $this->info("{$deleted} Datenbank-Wartungsdatei(en) geloescht.");

        return self::SUCCESS;
    }

    private function deleteOlderThan(string $directory, int $timestamp): int
    {
        if (! is_dir($directory)) {
            return 0;
        }

        $deleted = 0;

        foreach (File::files($directory) as $file) {
            if ($file->getMTime() >= $timestamp) {
                continue;
            }

            if (File::delete($file->getPathname())) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function safeStorageRoot(): ?string
    {
        $root = $this->normalizePath((string) config('database-maintenance.storage_root'));
        $storagePath = $this->normalizePath((string) realpath(storage_path()));

        if ($root === '' || $root === DIRECTORY_SEPARATOR || $storagePath === '' || $root === $storagePath) {
            return null;
        }

        $resolvedRoot = $this->resolvePathForSafety($root);

        if ($resolvedRoot === null || $resolvedRoot === $storagePath) {
            return null;
        }

        if (! $this->isPathInside($resolvedRoot, $storagePath)) {
            return null;
        }

        return $resolvedRoot;
    }

    private function resolvePathForSafety(string $path): ?string
    {
        $realPath = realpath($path);

        if ($realPath !== false) {
            return $this->normalizePath($realPath);
        }

        $suffix = [];
        $candidate = $path;

        while ($candidate !== '' && $candidate !== DIRECTORY_SEPARATOR) {
            $realPath = realpath($candidate);

            if ($realPath !== false) {
                $resolvedParent = $this->normalizePath($realPath);
                $resolvedSuffix = implode(DIRECTORY_SEPARATOR, $suffix);

                return $resolvedSuffix === ''
                    ? $resolvedParent
                    : $resolvedParent.DIRECTORY_SEPARATOR.$resolvedSuffix;
            }

            $parent = dirname($candidate);

            if ($parent === $candidate) {
                break;
            }

            array_unshift($suffix, basename($candidate));
            $candidate = $parent;
        }

        return null;
    }

    private function isPathInside(string $path, string $parent): bool
    {
        $comparisonPath = $path;
        $comparisonParent = $parent;

        if (PHP_OS_FAMILY === 'Windows') {
            $comparisonPath = strtolower($comparisonPath);
            $comparisonParent = strtolower($comparisonParent);
        }

        return str_starts_with($comparisonPath.DIRECTORY_SEPARATOR, $comparisonParent.DIRECTORY_SEPARATOR);
    }

    private function normalizePath(string $path): string
    {
        $path = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));

        if ($path === '') {
            return '';
        }

        $prefix = '';
        if (preg_match('/^[A-Za-z]:/', $path, $matches) === 1) {
            $prefix = $matches[0];
            $path = substr($path, 2);
        } elseif (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $prefix = DIRECTORY_SEPARATOR;
        }

        $segments = [];
        foreach (explode(DIRECTORY_SEPARATOR, $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments !== []) {
                    array_pop($segments);
                }

                continue;
            }

            $segments[] = $segment;
        }

        $normalized = implode(DIRECTORY_SEPARATOR, $segments);

        if ($prefix === DIRECTORY_SEPARATOR) {
            return $normalized === '' ? DIRECTORY_SEPARATOR : DIRECTORY_SEPARATOR.$normalized;
        }

        if ($prefix !== '') {
            return $normalized === '' ? $prefix.DIRECTORY_SEPARATOR : $prefix.DIRECTORY_SEPARATOR.$normalized;
        }

        return $normalized;
    }
}
