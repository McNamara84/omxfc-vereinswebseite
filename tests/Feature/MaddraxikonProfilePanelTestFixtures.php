<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait MaddraxikonProfilePanelTestFixtures
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createMember(Role $role = Role::Mitglied, array $attributes = []): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(array_merge(['current_team_id' => $team->id], $attributes));
        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }
}
