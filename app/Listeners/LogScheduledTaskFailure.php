<?php

namespace App\Listeners;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Support\Facades\Log;

class LogScheduledTaskFailure
{
    public function handle(ScheduledTaskFailed $event): void
    {
        // The scheduler reports the exception separately. Keep this diagnostic
        // correlated through Laravel Context without logging command arguments.
        Log::warning('Geplante Aufgabe fehlgeschlagen.', [
            'task_id' => $event->task->mutexName(),
            'runtime_seconds' => $event->runtime,
            'exception_type' => $event->exception::class,
        ]);
    }
}
