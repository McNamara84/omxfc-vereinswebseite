<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CrawlAbenteurer extends Command
{
    #[\Override]
    protected $signature = 'crawlabenteurer';

    #[\Override]
    protected $description = 'Safely refresh Die Abenteurer novel information';

    public function handle(): int
    {
        return $this->call('books:refresh', ['--series' => 'abenteurer']);
    }
}
