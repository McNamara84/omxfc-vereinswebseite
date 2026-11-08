<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('team invitation can be created with mass assignment', function (): void {
    $team = Team::factory()->create();
    $invitation = $team->teamInvitations()->create(['email' => 'invitee@example.com', 'role' => Role::Admin->value, 'id' => 999]);
    $invitation->refresh();
    $this->assertDatabaseHas('team_invitations', ['id' => $invitation->id, 'team_id' => $team->id, 'email' => 'invitee@example.com', 'role' => Role::Admin->value]);
    expect($invitation->id)->not->toEqual(999);
});

test('role can be nullable', function (): void {
    $team = Team::factory()->create();
    $invitation = $team->teamInvitations()->create(['email' => 'nullrole@example.com', 'role' => null]);
    expect($invitation->role)->toBeNull();
    $this->assertDatabaseHas('team_invitations', ['id' => $invitation->id, 'role' => null]);
});

test('team invitation belongs to team', function (): void {
    $team = Team::factory()->create();
    $invitation = $team->teamInvitations()->create(['email' => 'relation@example.com', 'role' => 'Member']);
    expect($invitation->team->is($team))->toBeTrue();
});

test('email must be unique per team', function (): void {
    $team = Team::factory()->create();
    $team->teamInvitations()->create(['email' => 'duplicate@example.com', 'role' => Role::Admin->value]);
    expect(fn () => $team->teamInvitations()->create(['email' => 'duplicate@example.com', 'role' => 'Editor']))->toThrow(QueryException::class);
});
