<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArbeitsgruppenControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createMemberWithRole(string $role = 'Mitglied'): User
    {
        $memberTeam = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $memberTeam->id]);
        $memberTeam->users()->attach($user, ['role' => $role]);
        return $user;
    }

    public function test_ag_leader_can_update_own_team(): void
    {
        $leader = $this->createMemberWithRole();
        $ag = Team::factory()->create([
            'user_id' => $leader->id,
            'personal_team' => false,
            'name' => 'AG Test',
        ]);
        $ag->users()->attach($leader, ['role' => 'Mitglied']);

        $this->actingAs($leader);

        $response = $this->put(route('arbeitsgruppen.update', $ag), [
            'name' => 'AG Neu',
            'leader_id' => $leader->id,
            'description' => 'Desc',
            'email' => 'ag@example.com',
            'meeting_schedule' => 'montags',
        ]);

        $response->assertRedirect(route('arbeitsgruppen.index'));
        $this->assertDatabaseHas('teams', [
            'id' => $ag->id,
            'name' => 'AG Neu',
            'description' => 'Desc',
            'email' => 'ag@example.com',
            'meeting_schedule' => 'montags',
        ]);
    }

    public function test_ag_leader_cannot_edit_other_team(): void
    {
        $leader = $this->createMemberWithRole();
        $otherLeader = $this->createMemberWithRole();
        $ag = Team::factory()->create([
            'user_id' => $otherLeader->id,
            'personal_team' => false,
            'name' => 'Andere AG',
        ]);

        $this->actingAs($leader);

        $this->get(route('arbeitsgruppen.edit', $ag))->assertForbidden();
    }

    public function test_admin_can_update_any_team(): void
    {
        $admin = $this->createMemberWithRole('Admin');
        $leader = $this->createMemberWithRole();
        $ag = Team::factory()->create([
            'user_id' => $leader->id,
            'personal_team' => false,
            'name' => 'AG Test',
        ]);

        $this->actingAs($admin);

        $response = $this->put(route('arbeitsgruppen.update', $ag), [
            'name' => 'AG Admin',
            'leader_id' => $admin->id,
            'description' => null,
            'email' => null,
            'meeting_schedule' => null,
        ]);

        $response->assertRedirect(route('arbeitsgruppen.index'));
        $this->assertDatabaseHas('teams', [
            'id' => $ag->id,
            'name' => 'AG Admin',
            'user_id' => $admin->id,
        ]);
    }
}
