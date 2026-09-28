<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\VeranstaltungsBaxxStatus;
use App\Models\Activity;
use App\Models\BaxxEarningProgress;
use App\Models\FantreffenAnmeldung;
use App\Models\Team;
use App\Models\User;
use App\Models\Veranstaltung;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VeranstaltungsBaxxPlaywrightSeeder extends Seeder
{
    public function run(?string $fixture = null): void
    {
        $database = (string) config('database.connections.sqlite.database');
        if (! app()->environment('testing') || config('database.default') !== 'sqlite' || ($database !== ':memory:' && ! str_ends_with($database, 'playwright.sqlite'))) {
            throw new \RuntimeException('Dieser Seeder benötigt die isolierte Playwright-Testdatenbank.');
        }

        $fixture ??= (string) env('E2E_VERANSTALTUNGS_BAXX_FIXTURE', 'default');
        if (! preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/D', $fixture)) {
            throw new \InvalidArgumentException('Ungültige Baxx-Testkennung.');
        }

        DB::transaction(function () use ($fixture): void {
            $event = Veranstaltung::firstOrNew([
                'slug' => 'baxx-browserpruefung'.($fixture === 'default' ? '' : '-'.$fixture),
            ]);
            if ($event->exists) {
                // Ausschließlich die Daten dieser Browser-/Worker-Fixture zurücksetzen.
                Activity::where('subject_type', FantreffenAnmeldung::class)
                    ->whereIn('subject_id', $event->anmeldungen()->select('id'))->delete();
                $event->baxxVergaben()->delete();
                $event->anmeldungen()->delete();
            }
            $event->forceFill([
                'titel' => 'Baxx Browserprüfung', 'status' => 'veroeffentlicht',
                'anmeldung_aktiv' => true, 'teilnahme_baxx' => 10,
                'baxx_status' => VeranstaltungsBaxxStatus::Offen,
                'baxx_abgeschlossen_am' => null, 'baxx_abgeschlossen_von' => null,
            ])->save();

            $team = Team::membersTeam();
            foreach (['Anwesend', 'Unbestaetigt'] as $name) {
                $email = 'playwright-baxx-'.$fixture.'-'.strtolower($name).'@example.com';
                $member = User::where('email', $email)->first()
                    ?? User::factory()->create(['email' => $email]);
                $member->forceFill([
                    'vorname' => 'Baxx', 'nachname' => $name, 'name' => 'Baxx '.$name,
                    'current_team_id' => $team->id,
                ])->save();
                $member->teams()->sync([$team->id => ['role' => Role::Mitglied->value]]);
                Activity::where('user_id', $member->id)->where('action', 'like', 'baxx_milestone_reached_%')->delete();
                BaxxEarningProgress::where('user_id', $member->id)->where('action_key', 'dashboard_baxx_milestone')->delete();
                $event->anmeldungen()->create([
                    'user_id' => $member->id, 'email' => $member->email,
                    'ist_mitglied' => true, 'payment_status' => 'free', 'payment_amount' => 0,
                ]);
            }
        });
    }
}
