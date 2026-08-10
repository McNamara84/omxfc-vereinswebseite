<?php

namespace App\Console\Commands;

use App\Services\Maddraxikon\MaddraxikonRatingSynchronizer;
use Illuminate\Console\Command;
use Throwable;

class SyncMaddraxikonReviewRatingsCommand extends Command
{
    protected $signature = 'maddraxikon:sync-review-ratings
        {--dry-run : Quelldaten prüfen, aber keine lokalen Daten ändern}
        {--force : Synchronisation auch bei deaktiviertem Feature ausführen}';

    protected $description = 'Synchronisiert persönliche Maddraxikon-Bewertungen zu Vereinsrezensionen.';

    public function handle(MaddraxikonRatingSynchronizer $synchronizer): int
    {
        try {
            $result = $synchronizer->sync(
                dryRun: (bool) $this->option('dry-run'),
                force: (bool) $this->option('force'),
            );
        } catch (Throwable $exception) {
            $this->error(
                'Maddraxikon-Bewertungssync fehlgeschlagen ('.class_basename($exception).').'
            );

            return self::FAILURE;
        }

        if ($result->disabled) {
            $this->comment('Maddraxikon-Bewertungen sind deaktiviert; kein Sync ausgeführt.');

            return self::SUCCESS;
        }

        $this->table(['Kandidaten', 'Aktualisiert', 'Entfernt', 'Übersprungen'], [[
            $result->candidates,
            $result->updated,
            $result->removed,
            $result->skipped,
        ]]);

        if ($result->dryRun) {
            $this->comment('Dry-Run: Lokale Snapshots und Sync-Status blieben unverändert.');
        }

        return self::SUCCESS;
    }
}
