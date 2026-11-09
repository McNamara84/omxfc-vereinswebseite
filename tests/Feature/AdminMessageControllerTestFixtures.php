<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait AdminMessageControllerTestFixtures
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Admin->value]);

        return $user;
    }

    private function actingMember(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);

        return $user;
    }
}
