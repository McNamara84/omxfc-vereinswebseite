<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\MembersTeamMembershipLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MembersTeamMembershipSerializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_lock_reads_team_users_and_pivots_in_one_canonical_order(): void
    {
        $team = Team::membersTeam();
        $admin = User::factory()->create(['current_team_id' => $team->id]);
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($admin, ['role' => Role::Admin->value]);
        $team->users()->attach($member, ['role' => Role::Mitglied->value]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        app(MembersTeamMembershipLock::class)->run(
            [$member->id, $admin->id, $member->id],
            function ($memberships) use ($admin, $member, $team): void {
                $this->assertTrue($memberships->team->is($team));
                $this->assertTrue($memberships->hasRole(
                    $admin->id,
                    Role::Admin,
                ));
                $this->assertTrue($memberships->isActiveMember($member->id));
                $this->assertSame(
                    $member->id,
                    $memberships->user($member->id)->id,
                );
            },
        );

        $queries = collect(DB::getQueryLog())
            ->pluck('query')
            ->map(static fn (string $query): string => strtolower($query))
            ->values();
        DB::disableQueryLog();

        $teamQuery = $queries->search(
            static fn (string $query): bool => str_contains($query, 'from "teams"')
                && str_contains($query, '"name"'),
        );
        $userQuery = $queries->search(
            static fn (string $query): bool => str_contains($query, 'from "users"')
                && str_contains($query, 'order by "id"'),
        );
        $pivotQuery = $queries->search(
            static fn (string $query): bool => str_contains($query, 'from "team_user"')
                && str_contains($query, 'order by "user_id"'),
        );

        $this->assertIsInt($teamQuery);
        $this->assertIsInt($userQuery);
        $this->assertIsInt($pivotQuery);
        $this->assertLessThan($userQuery, $teamQuery);
        $this->assertLessThan($pivotQuery, $userQuery);
    }

    public function test_lock_selects_the_exact_team_name_with_case_insensitive_collation(): void
    {
        Team::membersTeam()?->delete();
        Team::clearMembersTeamCache();
        Team::factory()->create([
            'name' => 'mitglieder',
            'personal_team' => false,
        ]);
        $membersTeam = Team::factory()->create([
            'name' => 'Mitglieder',
            'personal_team' => false,
        ]);
        Team::clearMembersTeamCache();
        $member = User::factory()->create([
            'current_team_id' => $membersTeam->id,
        ]);
        $membersTeam->users()->attach($member, [
            'role' => Role::Mitglied->value,
        ]);

        app(MembersTeamMembershipLock::class)->run(
            [$member->id],
            function ($memberships) use ($membersTeam): void {
                $this->assertTrue($memberships->team->is($membersTeam));
                $this->assertSame('Mitglieder', $memberships->team->name);
            },
        );
    }

    public function test_applicant_is_not_active_inside_the_locked_snapshot(): void
    {
        $team = Team::membersTeam();
        $applicant = User::factory()->create([
            'current_team_id' => $team->id,
        ]);
        $team->users()->attach($applicant, [
            'role' => Role::Anwaerter->value,
        ]);

        app(MembersTeamMembershipLock::class)->run(
            [$applicant->id],
            function ($memberships) use ($applicant): void {
                $this->assertSame(
                    Role::Anwaerter,
                    $memberships->role($applicant->id),
                );
                $this->assertFalse(
                    $memberships->isActiveMember($applicant->id),
                );
            },
        );
    }

    public function test_regular_member_cannot_approve_or_reject_applicants(): void
    {
        $team = Team::membersTeam();
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $approveTarget = User::factory()->create([
            'current_team_id' => $team->id,
        ]);
        $rejectTarget = User::factory()->create([
            'current_team_id' => $team->id,
        ]);
        $team->users()->attach($member, ['role' => Role::Mitglied->value]);
        $team->users()->attach($approveTarget, [
            'role' => Role::Anwaerter->value,
        ]);
        $team->users()->attach($rejectTarget, [
            'role' => Role::Anwaerter->value,
        ]);

        $this->actingAs($member)
            ->post(route('anwaerter.approve', $approveTarget))
            ->assertForbidden();
        $this->actingAs($member)
            ->post(route('anwaerter.reject', $rejectTarget))
            ->assertForbidden();

        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $approveTarget->id,
            'role' => Role::Anwaerter->value,
        ]);
        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $rejectTarget->id,
            'role' => Role::Anwaerter->value,
        ]);
        $this->assertDatabaseHas('users', ['id' => $rejectTarget->id]);
    }

    public function test_stale_applicant_action_cannot_change_an_active_member(): void
    {
        $team = Team::membersTeam();
        $admin = User::factory()->create(['current_team_id' => $team->id]);
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($admin, ['role' => Role::Admin->value]);
        $team->users()->attach($member, ['role' => Role::Mitglied->value]);

        $this->actingAs($admin)
            ->from('/dashboard')
            ->post(route('anwaerter.approve', $member))
            ->assertRedirect('/dashboard')
            ->assertSessionHas('error');
        $this->actingAs($admin)
            ->from('/dashboard')
            ->post(route('anwaerter.reject', $member))
            ->assertRedirect('/dashboard')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('team_user', [
            'team_id' => $team->id,
            'user_id' => $member->id,
            'role' => Role::Mitglied->value,
        ]);
        $this->assertDatabaseHas('users', ['id' => $member->id]);
    }
}
