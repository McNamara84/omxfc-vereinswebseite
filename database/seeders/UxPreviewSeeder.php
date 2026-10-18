<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use LogicException;

/** Reproducible role previews, exclusively in the isolated testing database. */
class UxPreviewSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new LogicException('UX preview users may only be seeded in the testing environment.');
        }
        $team = Team::membersTeam() ?? throw new LogicException('Seed the Playwright suite first.');
        foreach ([
            ['admin', 'Alex', Role::Admin, true],
            ['vorstand', 'Vera', Role::Vorstand, true],
            ['kassenwart', 'Klara', Role::Kassenwart, true],
            ['mitglied', 'Mara', Role::Mitglied, true],
            ['admin-unconfirmed', 'Robin', Role::Admin, false],
            ['mitglied-unconfirmed', 'Kim', Role::Mitglied, false],
        ] as [$key, $firstName, $role, $verified]) {
            $user = User::firstWhere('email', 'ux-'.$key.'@example.com');
            if (! $user) {
                $user = User::factory()->create([
                    'name' => $firstName.' '.$role->value,
                    'vorname' => $firstName,
                    'nachname' => $role->value,
                    'email' => 'ux-'.$key.'@example.com',
                    'email_verified_at' => $verified ? now() : null,
                    'password' => Hash::make('password'),
                    'current_team_id' => $team->id,
                ]);
            }
            $team->users()->syncWithoutDetaching([$user->id => ['role' => $role->value]]);
        }
    }
}
