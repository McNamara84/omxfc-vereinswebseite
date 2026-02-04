<?php

namespace Database\Seeders;

use App\Enums\TodoStatus;
use App\Models\Team;
use App\Models\Todo;
use App\Models\TodoCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Jetstream\Jetstream;

class TodoPlaywrightSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $team = Team::membersTeam();

        if (! $team) {
            $admin = User::withoutEvents(function () {
                return User::create([
                    'name' => 'Holger Ehrmann',
                    'email' => 'info@maddraxikon.com',
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                    'vorname' => 'Holger',
                    'nachname' => 'Ehrmann',
                    'strasse' => 'Musterstraße',
                    'hausnummer' => '123',
                    'plz' => '12345',
                    'stadt' => 'Musterstadt',
                    'land' => 'Deutschland',
                    'lat' => 48.137154,
                    'lon' => 11.576124,
                    'telefon' => '0123456789',
                    'verein_gefunden' => 'Sonstiges',
                    'mitgliedsbeitrag' => 36.00,
                ]);
            });

            $team = Jetstream::newTeamModel()->forceFill([
                'name' => 'Mitglieder',
                'user_id' => $admin->id,
                'personal_team' => false,
            ]);

            $team->save();
            $team->users()->attach($admin, ['role' => 'Admin']);

            $admin->forceFill([
                'current_team_id' => $team->id,
            ])->save();
        } else {
            $admin = User::firstWhere('email', 'info@maddraxikon.com');

            if ($admin) {
                $admin->forceFill([
                    'lat' => 48.137154,
                    'lon' => 11.576124,
                ])->save();
            }
        }

        if (! $admin) {
            $admin = User::withoutEvents(function () {
                $user = User::create([
                    'name' => 'Holger Ehrmann',
                    'email' => 'info@maddraxikon.com',
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                    'vorname' => 'Holger',
                    'nachname' => 'Ehrmann',
                    'strasse' => 'Musterstraße',
                    'hausnummer' => '123',
                    'plz' => '12345',
                    'stadt' => 'Musterstadt',
                    'land' => 'Deutschland',
                    'lat' => 48.137154,
                    'lon' => 11.576124,
                    'telefon' => '0123456789',
                    'verein_gefunden' => 'Sonstiges',
                    'mitgliedsbeitrag' => 36.00,
                ]);

                return $user;
            });

            $team->users()->attach($admin, ['role' => 'Admin']);

            $admin->forceFill([
                'current_team_id' => $team->id,
            ])->save();
        }

        $category = TodoCategory::firstOrCreate(
            ['slug' => 'playwright-tests'],
            ['name' => 'Playwright Tests']
        );

        $member = User::factory()->create([
            'name' => 'Playwright Mitglied',
            'email' => 'playwright-member@example.com',
            'current_team_id' => $team->id,
            'plz' => '50765',
            'stadt' => 'Köln',
            'land' => 'Deutschland',
            'lat' => 50.9767,
            'lon' => 6.8868,
        ]);

        $team->users()->attach($member, ['role' => 'Mitglied']);

        Todo::create([
            'team_id' => $team->id,
            'created_by' => $admin->id,
            'title' => 'Offene Playwright Challenge',
            'description' => 'Diese Aufgabe bleibt offen, bis sie übernommen wird.',
            'points' => 5,
            'category_id' => $category->id,
            'status' => TodoStatus::Open->value,
        ]);

        Todo::create([
            'team_id' => $team->id,
            'created_by' => $admin->id,
            'title' => 'Übernommene Playwright Challenge',
            'description' => 'Diese Aufgabe ist derzeit in Bearbeitung.',
            'points' => 8,
            'category_id' => $category->id,
            'status' => TodoStatus::Assigned->value,
            'assigned_to' => $member->id,
        ]);

        Todo::create([
            'team_id' => $team->id,
            'created_by' => $admin->id,
            'title' => 'Abzuschließende Playwright Challenge',
            'description' => 'Diese Aufgabe wartet auf Verifizierung.',
            'points' => 10,
            'category_id' => $category->id,
            'status' => TodoStatus::Completed->value,
            'assigned_to' => $member->id,
            'completed_at' => now()->subHours(3),
        ]);
    }
}
