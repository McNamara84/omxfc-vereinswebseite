<?php

use App\Jobs\EvaluateMaddraxikonContributions;
use App\Jobs\SyncMaddraxikonContributions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$syncMinutes = max(
    1,
    min(59, (int) config('maddraxikon.sync.interval_minutes', 15))
);

Schedule::command('member-map:refresh')->hourly();
Schedule::command('polls:archive-ended')->hourly();
Schedule::command('database-maintenance:cleanup')->daily();
Schedule::command('maddraxikon:prune-audit')
    ->monthlyOn(1, '03:30')
    ->name('maddraxikon:prune-audit')
    ->withoutOverlapping(30);
Schedule::command('maddraxikon:heartbeat')
    ->everyMinute()
    ->name('maddraxikon:scheduler-heartbeat');
Schedule::job(new SyncMaddraxikonContributions)
    ->cron("*/{$syncMinutes} * * * *")
    ->name('maddraxikon:sync-job')
    ->withoutOverlapping(15);
Schedule::job(new EvaluateMaddraxikonContributions)
    ->hourly()
    ->name('maddraxikon:evaluate-job')
    ->withoutOverlapping(60);
