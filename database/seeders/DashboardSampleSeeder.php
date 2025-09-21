<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\TodoStatus;
use App\Models\Activity;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\Team;
use App\Models\Todo;
use App\Models\User;
use App\Models\UserPoint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

class DashboardSampleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $team = Team::membersTeam();

        if (! $team) {
            return;
        }

        $admin = User::firstWhere('email', 'info@maddraxikon.com');

        if (! $admin) {
            $admin = User::withoutEvents(function () use ($team) {
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
                    'telefon' => '0123456789',
                    'verein_gefunden' => 'Sonstiges',
                    'mitgliedsbeitrag' => 36.00,
                ]);

                return $user;
            });

            $team->users()->attach($admin, ['role' => Role::Admin->value]);
        }

        if ($admin->current_team_id !== $team->id) {
            $admin->forceFill(['current_team_id' => $team->id])->save();
        }

        $members = collect([
            ['name' => 'Alex Beispiel', 'email' => 'alex.beispiel@example.com', 'points' => 180],
            ['name' => 'Bianca Beispiel', 'email' => 'bianca.beispiel@example.com', 'points' => 140],
            ['name' => 'Chris Beispiel', 'email' => 'chris.beispiel@example.com', 'points' => 95],
        ])->map(function (array $attributes) use ($team) {
            $user = User::firstOrCreate(
                ['email' => $attributes['email']],
                [
                    'name' => $attributes['name'],
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                    'vorname' => Arr::get(explode(' ', $attributes['name']), 0, 'Vorname'),
                    'nachname' => Arr::get(explode(' ', $attributes['name']), 1, 'Nachname'),
                    'strasse' => 'Musterstraße',
                    'hausnummer' => '1',
                    'plz' => '12345',
                    'stadt' => 'Musterstadt',
                    'land' => 'Deutschland',
                    'telefon' => '0123456789',
                    'verein_gefunden' => 'Sonstiges',
                    'mitgliedsbeitrag' => 36.00,
                ]
            );

            $user->forceFill(['current_team_id' => $team->id])->save();

            $team->users()->syncWithoutDetaching([$user->id => ['role' => Role::Mitglied->value]]);

            return [$user, $attributes['points']];
        });

        foreach ($members as [$member, $points]) {
            UserPoint::updateOrCreate([
                'user_id' => $member->id,
                'team_id' => $team->id,
            ], [
                'points' => $points,
            ]);
        }

        UserPoint::updateOrCreate([
            'user_id' => $admin->id,
            'team_id' => $team->id,
        ], [
            'points' => 120,
        ]);

        $team->users()->syncWithoutDetaching([$admin->id => ['role' => Role::Admin->value]]);

        Todo::firstOrCreate([
            'team_id' => $team->id,
            'title' => 'Dashboard Sichtung',
        ], [
            'created_by' => $admin->id,
            'assigned_to' => $admin->id,
            'description' => 'Überprüfe die neuen Dashboard-Kennzahlen.',
            'points' => 15,
            'status' => TodoStatus::Assigned->value,
        ]);

        Todo::firstOrCreate([
            'team_id' => $team->id,
            'title' => 'Dashboard Verifizierung',
        ], [
            'created_by' => $admin->id,
            'assigned_to' => $admin->id,
            'description' => 'Diese Aufgabe wartet auf Verifizierung.',
            'points' => 20,
            'status' => TodoStatus::Completed->value,
        ]);

        $offer = BookOffer::firstOrCreate([
            'user_id' => $admin->id,
            'series' => 'Maddrax',
            'book_number' => 1,
        ], [
            'book_title' => 'Maddrax Sammelband 1',
            'condition' => 'neuwertig',
            'completed' => false,
        ]);

        $requestUser = $members->first()[0] ?? $admin;

        $request = BookRequest::firstOrCreate([
            'user_id' => $requestUser->id,
            'series' => 'Maddrax',
            'book_number' => 1,
        ], [
            'book_title' => 'Maddrax Sammelband 1',
            'condition' => 'gut',
            'completed' => false,
        ]);

        BookSwap::firstOrCreate([
            'offer_id' => $offer->id,
            'request_id' => $request->id,
        ]);

        $applicant = User::firstOrCreate([
            'email' => 'anwaerter-dashboard@example.com',
        ], [
            'name' => 'Playwright Anwärter',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'vorname' => 'Playwright',
            'nachname' => 'Anwärter',
            'strasse' => 'Beispielweg',
            'hausnummer' => '42',
            'plz' => '54321',
            'stadt' => 'Teststadt',
            'land' => 'Deutschland',
            'telefon' => '0987654321',
            'verein_gefunden' => 'Sonstiges',
            'mitgliedsbeitrag' => 36.00,
        ]);

        $team->users()->syncWithoutDetaching([
            $applicant->id => ['role' => Role::Anwaerter->value],
        ]);

        Activity::firstOrCreate([
            'user_id' => $admin->id,
            'subject_type' => BookOffer::class,
            'subject_id' => $offer->id,
            'action' => 'created_offer',
        ]);
    }
}
