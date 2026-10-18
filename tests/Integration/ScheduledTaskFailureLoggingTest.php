<?php

namespace Tests\Integration;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use RuntimeException;
use Tests\TestCase;

class ScheduledTaskFailureLoggingTest extends TestCase
{
    public function test_failure_diagnostic_records_context_without_exposing_arguments_or_exception_messages(): void
    {
        $task = Schedule::exec('example --token=private-token')->name('private-description');

        Log::shouldReceive('warning')->once()->with('Geplante Aufgabe fehlgeschlagen.', [
            'task_id' => $task->mutexName(),
            'runtime_seconds' => null,
            'exception_type' => RuntimeException::class,
        ]);

        Event::dispatch(new ScheduledTaskFailed($task, new RuntimeException('private-message')));
    }

    public function test_failure_without_timing_does_not_invent_a_zero_runtime(): void
    {
        $task = Schedule::exec('example');

        Log::shouldReceive('warning')->once()->with('Geplante Aufgabe fehlgeschlagen.', [
            'task_id' => $task->mutexName(),
            'runtime_seconds' => null,
            'exception_type' => RuntimeException::class,
        ]);

        Event::dispatch(new ScheduledTaskFailed($task, new RuntimeException));
    }
}
