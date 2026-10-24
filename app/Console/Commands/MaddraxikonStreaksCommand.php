<?php

namespace App\Console\Commands;

use App\Models\MaddraxikonStreakState;
use App\Services\Maddraxikon\MaddraxikonStreakService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class MaddraxikonStreaksCommand extends Command
{
    protected $signature = 'maddraxikon:streaks
        {--recheck : Bereits geprüfte Revisionen erneut prüfen}
        {--user= : Nur Tagesansprüche dieses Mitglieds neu berechnen}
        {--from= : Frühester neu zu berechnender Tag (YYYY-MM-DD)}
        {--pretend : Nur lokal gespeicherte Ansprüche vergleichen, keine Änderungen oder API-Abfragen}';

    protected $description = 'Prüft Maddraxikon-Serien und verbucht fehlende Bonusdifferenzen.';

    public function handle(MaddraxikonStreakService $service): int
    {
        $validation = Validator::make(['user' => $this->option('user'), 'from' => $this->option('from')], [
            'user' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
        ]);
        if ($validation->fails()) {
            $this->error($validation->errors()->first());

            return self::FAILURE;
        }
        if ($this->option('pretend') || $this->option('user') || $this->option('from')) {
            $states = MaddraxikonStreakState::query()->where('wiki_key', config('maddraxikon.wiki_key'))
                ->when($this->option('user'), fn ($query, $id) => $query->where('user_id', $id))->orderBy('id')->cursor();
            $count = 0;
            foreach ($states as $state) {
                $count += $service->reconcile($state->user_id, (bool) $this->option('pretend'), $this->option('from'));
            }
        } else {
            $count = $service->run((bool) $this->option('recheck'));
        }
        $this->info(($this->option('pretend') ? 'Vorschau: ' : '').$count.' Bonusdifferenzen. Offene Prüfungen bleiben ausstehend.');

        return self::SUCCESS;
    }
}
