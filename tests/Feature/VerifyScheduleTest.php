<?php

namespace Tests\Feature;

use App\Support\ScheduleInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use UnexpectedValueException;

class VerifyScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_actual_schedule_contains_commands_and_named_jobs_in_berlin(): void
    {
        $this->artisan('app:verify-schedule')
            ->expectsOutputToContain('maddraxikon:heartbeat – on_one_server=false')
            ->expectsOutputToContain('maddraxikon:sync-job – on_one_server=false')
            ->assertSuccessful();
    }

    public function test_wrong_timezone_and_command_prefixes_cannot_satisfy_the_inventory(): void
    {
        Artisan::call('schedule:list', ['--json' => true]);
        $events = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        foreach ($events as &$event) {
            if (str_contains($event['command'], 'maddraxikon:heartbeat')) {
                $event['command'] = 'php artisan maddraxikon:heartbeat-impostor';
            }
        }
        unset($event);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Aufgaben fehlen: maddraxikon:heartbeat');
        ScheduleInventory::verify(json_encode($events, JSON_THROW_ON_ERROR));
    }

    public function test_wrong_timezone_is_rejected(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Europe/Berlin');
        ScheduleInventory::verify('[{"command":"php artisan member-map:refresh","expression":"0 * * * *","timezone":"UTC","on_one_server":false}]');
    }

    public function test_missing_laravel_13_metadata_is_rejected(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Unvollständiger');
        ScheduleInventory::verify('[{"command":"php artisan member-map:refresh","expression":"0 * * * *","timezone":"Europe/Berlin"}]');
    }
}
