<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait MaddraxikonProfileHardeningTestFixtures
{
    use RefreshDatabase;

    private function createMember(Role $role = Role::Mitglied): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id, 'lat' => 48.0, 'lon' => 11.0]);
        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }
}
