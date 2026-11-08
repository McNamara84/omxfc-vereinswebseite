<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\UserRoleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

covers(UserRoleService::class);

test('get role returns members role', function (): void {
    $team = Team::membersTeam();
    $user = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($user, ['role' => Role::Admin->value]);
    $service = new UserRoleService;
    expect($service->getRole($user, $team))->toBe(Role::Admin);
});

test('get role throws when membership missing', function (): void {
    $team = Team::membersTeam();
    $user = User::factory()->create(['current_team_id' => $team->id]);
    $service = new UserRoleService;
    expect(fn () => $service->getRole($user, $team))->toThrow(ModelNotFoundException::class);
});

test('get role throws on invalid role', function (): void {
    $team = Team::membersTeam();
    $user = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($user, ['role' => 'Gast']);
    $service = new UserRoleService;
    expect(fn () => $service->getRole($user, $team))->toThrow(ModelNotFoundException::class);
});
