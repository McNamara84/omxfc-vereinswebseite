<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait ArbeitsgruppenLeaderIndexTestFixtures
{
    use RefreshDatabase;

    private function member(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);

        return $user;
    }

    private function groupWithLeader(): array
    {
        $leader = $this->member();
        $ag = Team::factory()->create(['name' => 'AG Test', 'user_id' => $leader->id, 'personal_team' => false]);
        $ag->users()->attach($leader, ['role' => Role::Mitglied->value]);

        return [$leader, $ag];
    }
}
