<?php

namespace App\Console\Commands;

use App\Services\Maddraxikon\MaddraxikonRewardService;
use Illuminate\Console\Command;

class EvaluateMaddraxikonCommand extends Command
{
    protected $signature = 'maddraxikon:evaluate
        {--force : Auswertung auch bei deaktiviertem Feature-Schalter ausführen}';

    protected $description = 'Wertet fällige Maddraxikon-Beiträge aus und verbucht Baxx.';

    public function handle(MaddraxikonRewardService $rewardService): int
    {
        $count = $rewardService->evaluate((bool) $this->option('force'));

        $this->info(sprintf('Ausgewertete Maddraxikon-Quellen: %d', $count));

        return self::SUCCESS;
    }
}
