<?php

namespace App\Console;

use App\Console\Commands\ArchiveEndedPolls;
use App\Console\Commands\CleanupDatabaseMaintenanceFiles;
use App\Console\Commands\EvaluateMaddraxikonCommand;
use App\Console\Commands\MaddraxikonHeartbeatCommand;
use App\Console\Commands\MaddraxikonStatusCommand;
use App\Console\Commands\PruneMaddraxikonAuditCommand;
use App\Console\Commands\RecoverMaddraxikonCommand;
use App\Console\Commands\RepairLegacyRewardWallets;
use App\Console\Commands\SyncMaddraxikonCommand;
use App\Jobs\EvaluateMaddraxikonContributions;
use App\Jobs\SyncMaddraxikonContributions;
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
        EvaluateMaddraxikonCommand::class,
        MaddraxikonHeartbeatCommand::class,
        MaddraxikonStatusCommand::class,
        PruneMaddraxikonAuditCommand::class,
        RecoverMaddraxikonCommand::class,
        SyncMaddraxikonCommand::class,
        ArchiveEndedPolls::class,
        CleanupDatabaseMaintenanceFiles::class,
        RepairLegacyRewardWallets::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $syncMinutes = max(
            1,
            min(59, (int) config('maddraxikon.sync.interval_minutes', 15))
        );

        $schedule->command('member-map:refresh')->hourly();
        $schedule->command('polls:archive-ended')->hourly();
        $schedule->command('database-maintenance:cleanup')->daily();
        $schedule->command('maddraxikon:prune-audit')
            ->monthlyOn(1, '03:30')
            ->name('maddraxikon:prune-audit')
            ->withoutOverlapping(30);
        $schedule->command('maddraxikon:heartbeat')
            ->everyMinute()
            ->name('maddraxikon:scheduler-heartbeat');
        $schedule->job(new SyncMaddraxikonContributions)
            ->cron("*/{$syncMinutes} * * * *")
            ->name('maddraxikon:sync-job')
            ->withoutOverlapping(15);

        $schedule->job(new EvaluateMaddraxikonContributions)
            ->hourly()
            ->name('maddraxikon:evaluate-job')
            ->withoutOverlapping(60);
    }
}
