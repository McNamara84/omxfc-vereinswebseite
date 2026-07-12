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
        $root = $this->canonicalPath((string) config('database-maintenance.storage_root'));
        $storagePath = $this->canonicalPath(storage_path());

        if ($root === '' || $root === DIRECTORY_SEPARATOR || $root === $storagePath) {
            return null;
        }

        $comparisonRoot = strtolower($root);
        $comparisonStoragePath = strtolower($storagePath);

        if (! str_starts_with($comparisonRoot.DIRECTORY_SEPARATOR, $comparisonStoragePath.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $root;
    }

    private function canonicalPath(string $path): string
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
                array_pop($segments);

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
