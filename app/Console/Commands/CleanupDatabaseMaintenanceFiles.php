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
        $root = rtrim((string) config('database-maintenance.storage_root'), DIRECTORY_SEPARATOR);
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
}
