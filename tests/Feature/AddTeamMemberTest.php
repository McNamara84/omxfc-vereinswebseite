<?php

namespace Tests\Feature;

use App\Actions\Jetstream\AddTeamMember;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Events\AddingTeamMember;
use Laravel\Jetstream\Events\TeamMemberAdded;
use Tests\TestCase;

class AddTeamMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_members_can_be_added_to_team(): void
    {
        Event::fake([AddingTeamMember::class, TeamMemberAdded::class]);

        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);
        $otherUser = User::factory()->create();

        app(AddTeamMember::class)->add($user, $user->currentTeam, $otherUser->email, 'Admin');

        $this->assertTrue($user->currentTeam->fresh()->hasUser($otherUser));
        $this->assertTrue($otherUser->fresh()->hasTeamRole($user->currentTeam->fresh(), 'Admin'));

        Event::assertDispatched(AddingTeamMember::class);
        Event::assertDispatched(TeamMemberAdded::class);
    }

    public function test_only_team_owner_can_add_team_members(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $otherUser = User::factory()->create();
        $nonOwner = User::factory()->create();
        $this->actingAs($nonOwner);

        $this->expectException(AuthorizationException::class);

        app(AddTeamMember::class)->add($nonOwner, $owner->currentTeam, $otherUser->email, 'Admin');
    }

    public function test_email_must_exist_when_adding_team_member(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $this->expectException(ValidationException::class);

        app(AddTeamMember::class)->add($user, $user->currentTeam, 'missing@example.com', 'Admin');
    }

    public function test_user_must_not_already_be_on_team(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);
        $otherUser = User::factory()->create();

        // Add the user once
        $user->currentTeam->users()->attach($otherUser, ['role' => \App\Enums\Role::Admin->value]);

        $this->expectException(ValidationException::class);

        app(AddTeamMember::class)->add($user, $user->currentTeam, $otherUser->email, 'Admin');
    }

    public function test_role_must_be_valid_when_adding_team_member(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);
        $otherUser = User::factory()->create();

        $this->expectException(ValidationException::class);

        app(AddTeamMember::class)->add($user, $user->currentTeam, $otherUser->email, 'invalid-role');
    }
}
