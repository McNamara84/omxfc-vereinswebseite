<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\Veranstaltung;
use Illuminate\Database\Seeder;

class VeranstaltungsBaxxPlaywrightSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite' || ! str_ends_with((string) config('database.connections.sqlite.database'), 'playwright.sqlite')) {
            throw new \RuntimeException('Dieser Seeder benötigt die isolierte Playwright-Testdatenbank.');
        }

        $event = Veranstaltung::create([
            'titel' => 'Baxx Browserprüfung', 'slug' => 'baxx-browserpruefung',
            'status' => 'veroeffentlicht', 'anmeldung_aktiv' => true,
        ]);
        $team = Team::membersTeam();
        foreach (['Anwesend', 'Unbestaetigt'] as $name) {
            $member = User::factory()->create([
                'vorname' => 'Baxx', 'nachname' => $name, 'name' => 'Baxx '.$name,
                'current_team_id' => $team->id,
            ]);
            $member->teams()->attach($team->id, ['role' => Role::Mitglied->value]);
            $event->anmeldungen()->create([
                'user_id' => $member->id, 'email' => $member->email,
                'ist_mitglied' => true, 'payment_status' => 'free', 'payment_amount' => 0,
            ]);
        }
    }
}
