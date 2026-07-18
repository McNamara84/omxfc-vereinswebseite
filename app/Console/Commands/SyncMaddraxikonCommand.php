<?php

namespace App\Console\Commands;

use App\Services\Maddraxikon\MaddraxikonContributionImporter;
use Illuminate\Console\Command;

class SyncMaddraxikonCommand extends Command
{
    protected $signature = 'maddraxikon:sync
        {--force : Synchronisation auch bei deaktiviertem Feature-Schalter ausführen}';

    protected $description = 'Importiert neue Beiträge aus den Maddraxikon-Letzten-Änderungen.';

    public function handle(MaddraxikonContributionImporter $importer): int
    {
        $count = $importer->sync((bool) $this->option('force'));

        $this->info(sprintf('Importierte Maddraxikon-Beiträge: %d', $count));

        return self::SUCCESS;
    }
}
