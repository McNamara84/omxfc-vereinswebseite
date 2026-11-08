<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Providers\AuthServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

beforeEach(function (): void {
    // Ensure the gate definitions are registered
    $this->app->register(AuthServiceProvider::class);
});

test('owner can access dashboard', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    expect(Gate::forUser($user)->allows('access-dashboard'))->toBeTrue();
});

test('member with read permission can access dashboard', function (): void {
    $team = Team::factory()->create(['personal_team' => false]);
    $user = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($user, ['role' => Role::Mitglied->value]);
    expect(Gate::forUser($user)->allows('access-dashboard'))->toBeTrue();
});

test('member without read permission cannot access dashboard', function (): void {
    $team = Team::factory()->create(['personal_team' => false]);
    $user = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($user, ['role' => Role::Anwaerter->value]);
    expect(Gate::forUser($user)->allows('access-dashboard'))->toBeFalse();
});

test('user without team membership cannot access dashboard', function (): void {
    $team = Team::factory()->create(['personal_team' => false]);
    $user = User::factory()->create(['current_team_id' => $team->id]);
    expect(Gate::forUser($user)->allows('access-dashboard'))->toBeFalse();
});

test('user without current team cannot access dashboard without exception', function (): void {
    $user = User::factory()->create(['current_team_id' => Team::membersTeam()->id]);
    $user->setRelation('currentTeam', null);
    expect(Gate::forUser($user)->allows('access-dashboard'))->toBeFalse();
});
