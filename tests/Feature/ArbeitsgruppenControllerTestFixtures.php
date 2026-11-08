<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait ArbeitsgruppenControllerTestFixtures
{
    use RefreshDatabase;

    private function createMemberWithRole(string $role = 'Mitglied'): User
    {
        $memberTeam = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $memberTeam->id]);
        $memberTeam->users()->attach($user, ['role' => $role]);

        return $user;
    }
}
