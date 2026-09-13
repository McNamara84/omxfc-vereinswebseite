<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CrawlHardcovers extends Command
{
    protected $signature = 'crawlhardcovers';

    protected $description = 'Safely refresh Maddraxikon hardcover information';

    public function handle(): int
    {
        return $this->call('books:refresh', ['--series' => 'hardcovers']);
    }
}
