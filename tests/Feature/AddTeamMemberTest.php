<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Jetstream\AddTeamMember;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Events\AddingTeamMember;
use Laravel\Jetstream\Events\TeamMemberAdded;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('team members can be added to team', function (): void {
    Event::fake([AddingTeamMember::class, TeamMemberAdded::class]);
    $user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($user);
    $otherUser = User::factory()->create();
    app(AddTeamMember::class)->add($user, $user->currentTeam, $otherUser->email, 'Admin');
    expect($user->currentTeam->fresh()->hasUser($otherUser))->toBeTrue()
        ->and($otherUser->fresh()->hasTeamRole($user->currentTeam->fresh(), 'Admin'))->toBeTrue();
    Event::assertDispatched(AddingTeamMember::class);
    Event::assertDispatched(TeamMemberAdded::class);
});

test('only team owner can add team members', function (): void {
    $owner = User::factory()->withPersonalTeam()->create();
    $otherUser = User::factory()->create();
    $nonOwner = User::factory()->create();
    $this->actingAs($nonOwner);
    expect(fn () => app(AddTeamMember::class)->add($nonOwner, $owner->currentTeam, $otherUser->email, 'Admin'))->toThrow(AuthorizationException::class);
});

test('email must exist when adding team member', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($user);
    expect(fn () => app(AddTeamMember::class)->add($user, $user->currentTeam, 'missing@example.com', 'Admin'))->toThrow(ValidationException::class);
});

test('user must not already be on team', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($user);
    $otherUser = User::factory()->create();
    // Add the user once
    $user->currentTeam->users()->attach($otherUser, ['role' => Role::Admin->value]);
    expect(fn () => app(AddTeamMember::class)->add($user, $user->currentTeam, $otherUser->email, 'Admin'))->toThrow(ValidationException::class);
});

test('role must be valid when adding team member', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    $this->actingAs($user);
    $otherUser = User::factory()->create();
    expect(fn () => app(AddTeamMember::class)->add($user, $user->currentTeam, $otherUser->email, 'invalid-role'))->toThrow(ValidationException::class);
});
