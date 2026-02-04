<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArbeitsgruppenLeaderIndexTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => \App\Enums\Role::Mitglied->value]);

        return $user;
    }

    private function groupWithLeader(): array
    {
        $leader = $this->member();
        $ag = Team::factory()->create([
            'name' => 'AG Test',
            'user_id' => $leader->id,
            'personal_team' => false,
        ]);
        $ag->users()->attach($leader, ['role' => \App\Enums\Role::Mitglied->value]);

        return [$leader, $ag];
    }

    public function test_leader_can_view_own_groups(): void
    {
        [$leader] = $this->groupWithLeader();

        $this->actingAs($leader)
            ->get('/ag')
            ->assertOk()
            ->assertSee('AG Test');
    }

    public function test_non_leader_gets_forbidden(): void
    {
        [$leader] = $this->groupWithLeader();

        $other = $this->member();

        $this->actingAs($other)
            ->get('/ag')
            ->assertForbidden();
    }
}
