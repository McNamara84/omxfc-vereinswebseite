<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Database\QueryException;

class TeamInvitationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_invitation_can_be_created_with_mass_assignment(): void
    {
        $team = Team::factory()->create();

        $invitation = $team->teamInvitations()->create([
            'email' => 'invitee@example.com',
            'role' => \App\Enums\Role::Admin->value,
            'id' => 999,
        ]);

        $invitation->refresh();

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
            'team_id' => $team->id,
            'email' => 'invitee@example.com',
            'role' => \App\Enums\Role::Admin->value,
        ]);
        $this->assertNotEquals(999, $invitation->id);
    }

    public function test_role_can_be_nullable(): void
    {
        $team = Team::factory()->create();

        $invitation = $team->teamInvitations()->create([
            'email' => 'nullrole@example.com',
            'role' => null,
        ]);

        $this->assertNull($invitation->role);
        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
            'role' => null,
        ]);
    }

    public function test_team_invitation_belongs_to_team(): void
    {
        $team = Team::factory()->create();

        $invitation = $team->teamInvitations()->create([
            'email' => 'relation@example.com',
            'role' => 'Member',
        ]);

        $this->assertTrue($invitation->team->is($team));
    }

    public function test_email_must_be_unique_per_team(): void
    {
        $team = Team::factory()->create();

        $team->teamInvitations()->create([
            'email' => 'duplicate@example.com',
            'role' => \App\Enums\Role::Admin->value,
        ]);

        $this->expectException(QueryException::class);

        $team->teamInvitations()->create([
            'email' => 'duplicate@example.com',
            'role' => 'Editor',
        ]);
    }
}
