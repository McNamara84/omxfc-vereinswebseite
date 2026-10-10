<?php

namespace App\Console\Commands;

use App\Support\ScheduleInventory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use JsonException;
use Symfony\Component\Console\Output\BufferedOutput;
use UnexpectedValueException;

class VerifySchedule extends Command
{
    protected $signature = 'app:verify-schedule';

    protected $description = 'Prüft erforderliche Aufgaben, Zeitzone und Mehrserver-Metadaten des Schedulers';

    public function handle(): int
    {
        $output = new BufferedOutput;

        if (Artisan::call('schedule:list', ['--json' => true, '--no-ansi' => true], $output) !== self::SUCCESS) {
            $this->error('Der Scheduler konnte nicht abgefragt werden.');

            return self::FAILURE;
        }

        try {
            $tasks = ScheduleInventory::verify($output->fetch());
        } catch (JsonException|UnexpectedValueException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($tasks as $name => $onOneServer) {
            $this->line($name.' – on_one_server='.($onOneServer ? 'true' : 'false'));
        }

        $this->info('Alle erforderlichen Aufgaben sind registriert; Zeitzone Europe/Berlin.');

        return self::SUCCESS;
    }
}
