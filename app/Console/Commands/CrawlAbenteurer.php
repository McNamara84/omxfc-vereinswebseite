<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CrawlAbenteurer extends Command
{
    protected $signature = 'crawlabenteurer';

    protected $description = 'Safely refresh Die Abenteurer novel information';

    public function handle(): int
    {
        return $this->call(RefreshMaddraxBooks::class, ['--series' => 'abenteurer']);
    }
}
