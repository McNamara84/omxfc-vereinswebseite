<?php

namespace App\Console\Commands;

use App\Services\Maddraxikon\MaddraxikonMonitoring;
use Illuminate\Console\Command;
use Throwable;

final class MaddraxikonHeartbeatCommand extends Command
{
    protected $signature = 'maddraxikon:heartbeat';

    protected $description = 'Speichert das Lebenszeichen des Laravel-Schedulers.';

    public function handle(MaddraxikonMonitoring $monitoring): int
    {
        try {
            $recordedAt = $monitoring->recordSchedulerHeartbeat();
        } catch (Throwable $exception) {
            $this->error(
                'Scheduler-Lebenszeichen konnte nicht gespeichert werden: '.
                $exception->getMessage(),
            );

            return self::FAILURE;
        }

        $this->info(
            'Maddraxikon-Scheduler-Lebenszeichen: '.
            $recordedAt->toIso8601String(),
        );

        return self::SUCCESS;
    }
}
