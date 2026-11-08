<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait VeranstaltungVerwaltungTestFixtures
{
    use RefreshDatabase;

    protected function createUserWithRole(Role $role): User
    {
        $team = Team::membersTeam() ?? Team::factory()->create(['name' => 'Mitglieder', 'personal_team' => false]);
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $user->teams()->attach($team->id, ['role' => $role->value, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    protected function createUserWithRoleAndDifferentCurrentTeam(Role $role): User
    {
        $managementTeam = Team::membersTeam() ?? Team::factory()->create(['name' => 'Mitglieder', 'personal_team' => false]);
        $user = User::factory()->create(['current_team_id' => $managementTeam->id]);
        $managementTeam->users()->attach($user->id, ['role' => $role->value, 'created_at' => now(), 'updated_at' => now()]);
        $otherTeam = Team::factory()->create(['user_id' => $user->id, 'name' => 'Nebenverein', 'personal_team' => false]);
        $otherTeam->users()->attach($user->id, ['role' => Role::Mitglied->value, 'created_at' => now(), 'updated_at' => now()]);
        $user->forceFill(['current_team_id' => $otherTeam->id])->save();

        return $user->refresh();
    }
}
