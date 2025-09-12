<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Policies\TeamPolicy;
use App\Models\Team;

class TeamPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissions_depend_on_team_ownership(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->ownedTeams()->first();
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => 'Member']);
        $outsider = User::factory()->create();

        $adminTeam = Team::membersTeam();
        $admin = User::factory()->create(['current_team_id' => $adminTeam->id]);
        $adminTeam->users()->attach($admin, ['role' => 'Admin']);

        $policy = new TeamPolicy();

        $this->assertTrue($policy->viewAny($member));
        $this->assertTrue($policy->view($member, $team));
        $this->assertFalse($policy->view($outsider, $team));

        $this->assertTrue($policy->update($owner, $team));
        $this->assertFalse($policy->update($member, $team));

        $this->assertTrue($policy->addTeamMember($owner, $team));
        $this->assertFalse($policy->addTeamMember($member, $team));
        $this->assertTrue($policy->addTeamMember($admin, $team));

        $this->assertTrue($policy->updateTeamMember($owner, $team));
        $this->assertFalse($policy->updateTeamMember($member, $team));

        $this->assertTrue($policy->removeTeamMember($owner, $team));
        $this->assertFalse($policy->removeTeamMember($member, $team));

        $this->assertTrue($policy->delete($owner, $team));
        $this->assertFalse($policy->delete($member, $team));
    }
}
