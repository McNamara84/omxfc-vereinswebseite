<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class ArbeitsgruppenPlaywrightSeeder extends Seeder
{
    public function run(): void
    {
        $membersTeam = Team::membersTeam();

        if (! $membersTeam) {
            return;
        }

        $leader = User::factory()->create([
            'name' => 'Martin Gobrecht',
            'email' => 'martin.gobrecht@example.com',
            'vorname' => 'Martin',
            'nachname' => 'Gobrecht',
            'current_team_id' => $membersTeam->id,
        ]);

        $membersTeam->users()->syncWithoutDetaching([
            $leader->id => ['role' => Role::Mitglied->value],
        ]);

        $team = Team::query()->create([
            'name' => 'AG Fanhoerbuecher',
            'user_id' => $leader->id,
            'personal_team' => false,
            'description' => 'EARDRAX: Die AG macht inszenierte Lesungen fuer YouTube zuganglich und sucht weitere Mitwirkende.',
            'meeting_schedule' => 'Nach Bedarf und Projektphase',
            'email' => 'ag-hoerbuecher@maddrax-fanclub.de',
        ]);

        $team->users()->syncWithoutDetaching([
            $leader->id => ['role' => Role::Mitwirkender->value],
        ]);
    }
}