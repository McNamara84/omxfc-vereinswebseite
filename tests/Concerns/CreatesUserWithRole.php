<?php

namespace Tests\Concerns;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;

trait CreatesUserWithRole
{
    protected function createUserWithRole(Role $role): User
    {
        $team = Team::membersTeam();

        if (! $team) {
            $team = Team::factory()->create(['name' => 'Mitglieder']);
        }

        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role->value]);

        return $user->refresh();
    }
}
