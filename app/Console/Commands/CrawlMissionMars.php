<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CrawlMissionMars extends Command
{
    protected $signature = 'crawlmissionmars';

    protected $description = 'Safely refresh Mission Mars novel information';

    public function handle(): int
    {
        return $this->call('books:refresh', ['--series' => 'missionmars']);
    }
}
