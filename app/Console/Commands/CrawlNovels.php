<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CrawlNovels extends Command
{
    #[\Override]
    protected $signature = 'crawlnovels';

    #[\Override]
    protected $description = 'Safely refresh all Maddraxikon novel information';

    public function handle(): int
    {
        return $this->call('books:refresh', ['--series' => 'all']);
    }
}
