<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait BookSwapProcessTestFixtures
{
    use RefreshDatabase;

    private function createMember(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);

        return $user;
    }

    private function createManager(): User
    {
        $manager = $this->createMember();
        Team::membersTeam()->users()->updateExistingPivot($manager->id, ['role' => Role::Vorstand->value]);

        return $manager;
    }
}
