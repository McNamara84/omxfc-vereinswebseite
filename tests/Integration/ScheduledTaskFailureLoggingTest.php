<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class);

test('failure diagnostic records context without exposing arguments or exception messages', function (): void {
    $task = Schedule::exec('example --token=private-token')->name('private-description');
    Log::shouldReceive('warning')->once()->with('Geplante Aufgabe fehlgeschlagen.', ['task_id' => $task->mutexName(), 'runtime_seconds' => null, 'exception_type' => RuntimeException::class]);
    Event::dispatch(new ScheduledTaskFailed($task, new RuntimeException('private-message')));
});

test('failure without timing does not invent a zero runtime', function (): void {
    $task = Schedule::exec('example');
    Log::shouldReceive('warning')->once()->with('Geplante Aufgabe fehlgeschlagen.', ['task_id' => $task->mutexName(), 'runtime_seconds' => null, 'exception_type' => RuntimeException::class]);
    Event::dispatch(new ScheduledTaskFailed($task, new RuntimeException));
});
