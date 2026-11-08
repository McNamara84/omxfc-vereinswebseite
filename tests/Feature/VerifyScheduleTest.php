<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\ScheduleInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;
use UnexpectedValueException;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('actual schedule contains commands and named jobs in berlin', function (): void {
    $output = new BufferedOutput;
    $status = Artisan::call('app:verify-schedule', [], $output);
    $text = $output->fetch();
    expect($status)->toBe(0)
        ->and($text)->toContain('maddraxikon:heartbeat – on_one_server=false')
        ->toContain('maddraxikon:sync-job – on_one_server=false');
});

test('wrong timezone and command prefixes cannot satisfy the inventory', function (): void {
    Artisan::call('schedule:list', ['--json' => true]);
    $events = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    foreach ($events as &$event) {
        if (str_contains($event['command'], 'maddraxikon:heartbeat')) {
            $event['command'] = 'php artisan maddraxikon:heartbeat-impostor';
        }
    }
    unset($event);
    expect(fn () => ScheduleInventory::verify(json_encode($events, JSON_THROW_ON_ERROR)))->toThrow(UnexpectedValueException::class, 'Aufgaben fehlen: maddraxikon:heartbeat');
});

test('wrong timezone is rejected', function (): void {
    expect(fn () => ScheduleInventory::verify('[{"command":"php artisan member-map:refresh","expression":"0 * * * *","timezone":"UTC","on_one_server":false}]'))->toThrow(UnexpectedValueException::class, 'Europe/Berlin');
});

test('missing laravel 13 metadata is rejected', function (): void {
    expect(fn () => ScheduleInventory::verify('[{"command":"php artisan member-map:refresh","expression":"0 * * * *","timezone":"Europe/Berlin"}]'))->toThrow(UnexpectedValueException::class, 'Unvollständiger');
});
