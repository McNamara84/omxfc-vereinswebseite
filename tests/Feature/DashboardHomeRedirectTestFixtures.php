<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait DashboardHomeRedirectTestFixtures
{
    use RefreshDatabase;

    private function member(Role $role, bool $verified = true): User
    {
        $team = Team::membersTeam();
        $factory = $verified ? User::factory() : User::factory()->unverified();
        $user = $factory->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }
}
