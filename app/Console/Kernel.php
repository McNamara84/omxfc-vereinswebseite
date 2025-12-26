<?php

namespace App\Console;

use App\Console\Commands\ArchiveEndedPolls;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array<int, class-string>
     */
    protected $commands = [
        ArchiveEndedPolls::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('member-map:refresh')->hourly();
        $schedule->command('polls:archive-ended')->hourly();
    }
}

