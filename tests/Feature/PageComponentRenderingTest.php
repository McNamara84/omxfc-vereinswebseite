<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('public page component renders with default classes', function (): void {
    $this->get('/chronik')->assertOk()->assertSee('max-w-6xl', false)->assertSee('bg-base-100', false);
});

test('member page component renders for dashboard', function (): void {
    $team = Team::membersTeam();
    $user = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($user, ['role' => Role::Mitglied->value]);
    $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('max-w-7xl', false);
});
