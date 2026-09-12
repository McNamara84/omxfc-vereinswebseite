<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CrawlVolkDerTiefe extends Command
{
    protected $signature = 'crawlvolkdertiefe';

    protected $description = 'Safely refresh Das Volk der Tiefe novel information';

    public function handle(): int
    {
        return $this->call(RefreshMaddraxBooks::class, ['--series' => 'volkdertiefe']);
    }
}
