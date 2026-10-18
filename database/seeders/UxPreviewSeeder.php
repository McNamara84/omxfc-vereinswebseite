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
            ['admin', 'Alex', Role::Admin],
            ['vorstand', 'Vera', Role::Vorstand],
            ['kassenwart', 'Klara', Role::Kassenwart],
            ['mitglied', 'Mara', Role::Mitglied],
        ] as [$key, $firstName, $role]) {
            $user = User::firstWhere('email', 'ux-'.$key.'@example.com');
            if (! $user) {
                $user = User::factory()->create([
                    'name' => $firstName.' '.$role->value,
                    'vorname' => $firstName,
                    'nachname' => $role->value,
                    'email' => 'ux-'.$key.'@example.com',
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                    'current_team_id' => $team->id,
                ]);
            }
            $team->users()->syncWithoutDetaching([$user->id => ['role' => $role->value]]);
        }
    }
}
