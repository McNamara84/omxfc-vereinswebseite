<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Crawl2012 extends Command
{
    #[\Override]
    protected $signature = 'crawl2012';

    #[\Override]
    protected $description = 'Safely refresh 2012 mini-series novel information';

    public function handle(): int
    {
        return $this->call('books:refresh', ['--series' => '2012']);
    }
}
