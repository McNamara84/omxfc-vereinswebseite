<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Crawl2012 extends Command
{
    protected $signature = 'crawl2012';

    protected $description = 'Safely refresh 2012 mini-series novel information';

    public function handle(): int
    {
        return $this->call(RefreshMaddraxBooks::class, ['--series' => '2012']);
    }
}
