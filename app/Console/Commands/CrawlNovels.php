<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CrawlNovels extends Command
{
    protected $signature = 'crawlnovels';

    protected $description = 'Safely refresh all Maddraxikon novel information';

    public function handle(): int
    {
        return $this->call('books:refresh', ['--series' => 'all']);
    }
}
