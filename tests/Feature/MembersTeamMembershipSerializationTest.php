<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\MembersTeamMembershipLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('lock reads team users and pivots in one canonical order', function (): void {
    $team = Team::membersTeam();
    $admin = User::factory()->create(['current_team_id' => $team->id]);
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($admin, ['role' => Role::Admin->value]);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    DB::flushQueryLog();
    DB::enableQueryLog();
    app(MembersTeamMembershipLock::class)->run([$member->id, $admin->id, $member->id], function ($memberships) use ($admin, $member, $team): void {
        expect($memberships->team->is($team))->toBeTrue()
            ->and($memberships->hasRole($admin->id, Role::Admin))->toBeTrue()
            ->and($memberships->isActiveMember($member->id))->toBeTrue()
            ->and($memberships->user($member->id)->id)->toBe($member->id);
    });
    $queries = collect(DB::getQueryLog())->pluck('query')->map(static fn (string $query): string => strtolower($query))->values();
    DB::disableQueryLog();
    $teamQuery = $queries->search(static fn (string $query): bool => str_contains($query, 'from "teams"') && str_contains($query, '"name"'));
    $userQuery = $queries->search(static fn (string $query): bool => str_contains($query, 'from "users"') && str_contains($query, 'order by "id"'));
    $pivotQuery = $queries->search(static fn (string $query): bool => str_contains($query, 'from "team_user"') && str_contains($query, 'order by "user_id"'));
    expect($teamQuery)->toBeInt()
        ->and($userQuery)->toBeInt()
        ->and($pivotQuery)->toBeInt()
        ->and($teamQuery)->toBeLessThan($userQuery)
        ->and($userQuery)->toBeLessThan($pivotQuery);
});

test('lock selects the exact team name with case insensitive collation', function (): void {
    Team::membersTeam()?->delete();
    Team::clearMembersTeamCache();
    Team::factory()->create(['name' => 'mitglieder', 'personal_team' => false]);
    $membersTeam = Team::factory()->create(['name' => 'Mitglieder', 'personal_team' => false]);
    Team::clearMembersTeamCache();
    $member = User::factory()->create(['current_team_id' => $membersTeam->id]);
    $membersTeam->users()->attach($member, ['role' => Role::Mitglied->value]);
    app(MembersTeamMembershipLock::class)->run([$member->id], function ($memberships) use ($membersTeam): void {
        expect($memberships->team->is($membersTeam))->toBeTrue()
            ->and($memberships->team->name)->toBe('Mitglieder');
    });
});

test('applicant is not active inside the locked snapshot', function (): void {
    $team = Team::membersTeam();
    $applicant = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($applicant, ['role' => Role::Anwaerter->value]);
    app(MembersTeamMembershipLock::class)->run([$applicant->id], function ($memberships) use ($applicant): void {
        expect($memberships->role($applicant->id))->toBe(Role::Anwaerter)
            ->and($memberships->isActiveMember($applicant->id))->toBeFalse();
    });
});

test('regular member cannot approve or reject applicants', function (): void {
    $team = Team::membersTeam();
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $approveTarget = User::factory()->create(['current_team_id' => $team->id]);
    $rejectTarget = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    $team->users()->attach($approveTarget, ['role' => Role::Anwaerter->value]);
    $team->users()->attach($rejectTarget, ['role' => Role::Anwaerter->value]);
    $this->actingAs($member)->post(route('anwaerter.approve', $approveTarget))->assertForbidden();
    $this->actingAs($member)->post(route('anwaerter.reject', $rejectTarget))->assertForbidden();
    $this->assertDatabaseHas('team_user', ['team_id' => $team->id, 'user_id' => $approveTarget->id, 'role' => Role::Anwaerter->value]);
    $this->assertDatabaseHas('team_user', ['team_id' => $team->id, 'user_id' => $rejectTarget->id, 'role' => Role::Anwaerter->value]);
    $this->assertDatabaseHas('users', ['id' => $rejectTarget->id]);
});

test('stale applicant action cannot change an active member', function (): void {
    $team = Team::membersTeam();
    $admin = User::factory()->create(['current_team_id' => $team->id]);
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($admin, ['role' => Role::Admin->value]);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    $this->actingAs($admin)->from('/dashboard')->post(route('anwaerter.approve', $member))->assertRedirect('/dashboard')->assertSessionHas('error');
    $this->actingAs($admin)->from('/dashboard')->post(route('anwaerter.reject', $member))->assertRedirect('/dashboard')->assertSessionHas('error');
    $this->assertDatabaseHas('team_user', ['team_id' => $team->id, 'user_id' => $member->id, 'role' => Role::Mitglied->value]);
    $this->assertDatabaseHas('users', ['id' => $member->id]);
});
