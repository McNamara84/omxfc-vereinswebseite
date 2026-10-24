<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use App\Services\Memberships\MembershipContext;
use App\Services\MembersTeamMembershipLock;
use App\Services\Polls\PollVotingService;
use Illuminate\Database\DeadlockException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\Concerns\CreatesUserWithRole;
use Tests\Concerns\UsesCommittedMembershipDatabase;
use Tests\TestCase;

class MembershipContextMutationTest extends TestCase
{
    use CreatesUserWithRole;
    use UsesCommittedMembershipDatabase;

    #[TestWith(['update'])]
    #[TestWith(['sync'])]
    #[TestWith(['detach'])]
    #[TestWith(['detach-all'])]
    #[TestWith(['model'])]
    public function test_pivot_mutations_invalidate_roles_and_all_observed_relations(string $operation): void
    {
        $user = $this->createUserWithRole(Role::Admin);
        $copy = $user->fresh();
        $team = Team::membersTeam();
        $context = app(MembershipContext::class);
        $this->assertSame(Role::Admin, $user->role());
        $this->assertSame(Role::Admin, $copy->role());
        $context->navigationRoles($user);
        $copy->load('teams');
        $team->load('users');
        match ($operation) {
            'update' => $team->users()->updateExistingPivot($user->id, ['role' => Role::Mitglied->value]),
            'sync' => $team->users()->syncWithoutDetaching([$user->id => ['role' => Role::Mitglied->value]]),
            'detach' => $team->users()->detach($user),
            'detach-all' => $team->users()->detach(),
            'model' => Membership::where('user_id', $user->id)->firstOrFail()->update(['role' => Role::Mitglied->value]),
        };
        $expected = str_starts_with($operation, 'detach') ? null : Role::Mitglied;
        $this->assertFalse($user->relationLoaded('teams'));
        $this->assertFalse($copy->relationLoaded('teams'));
        $this->assertFalse($team->relationLoaded('users'));
        $this->assertSame($expected, $user->role());
        $this->assertSame($expected, $copy->mitgliederTeamRole());
        $this->assertSame($expected, $context->navigationRoles($copy)['members']);
    }

    public function test_switching_team_updates_observed_instances_without_changing_members_role(): void
    {
        $user = $this->createUserWithRole(Role::Admin);
        $copy = $user->fresh();
        $this->assertSame(Role::Admin, $user->role());
        $this->assertSame(Role::Admin, $copy->role());
        $ag = Team::factory()->create();
        $ag->users()->attach($user, ['role' => Role::Mitwirkender->value]);
        $copy->switchTeam($ag);
        $this->assertSame(Role::Mitwirkender, $user->role());
        $this->assertSame(Role::Admin, $user->mitgliederTeamRole());
    }

    public function test_renaming_and_replacing_members_team_invalidates_named_checks(): void
    {
        $user = $this->createUserWithRole(Role::Admin);
        $team = Team::membersTeam();
        $this->assertTrue($user->isMemberOfTeam('Mitglieder'));
        $this->assertFalse($user->isMemberOfTeam('Archiv'));
        $this->assertSame(Role::Admin, $user->mitgliederTeamRole());
        $team->update(['name' => 'Archiv']);
        $this->assertNull($user->mitgliederTeamRole());
        $this->assertFalse($user->isMemberOfTeam('Mitglieder'));
        $this->assertTrue($user->isMemberOfTeam('Archiv'));
        $replacement = Team::factory()->create(['name' => 'Mitglieder']);
        $replacement->users()->attach($user, ['role' => Role::Mitglied->value]);
        $this->assertSame(Role::Mitglied, $user->mitgliederTeamRole());
        $replacement->delete();
        $this->assertNull($user->mitgliederTeamRole());
    }

    public function test_owner_change_updates_observed_team_and_ownership_checks(): void
    {
        $user = $this->createUserWithRole(Role::Mitglied);
        $team = Team::factory()->create(['name' => 'AG Test', 'user_id' => $user->id]);
        $copy = $team->fresh();
        app(MembershipContext::class)->track($team);
        $this->assertTrue($user->isOwnerOfTeam('AG Test'));
        $copy->update(['user_id' => User::factory()->create()->id]);
        $this->assertFalse($user->isOwnerOfTeam('AG Test'));
        $this->assertFalse($user->ownsTeam($team));
    }

    public function test_team_creation_invalidates_a_previously_missing_ownership_lookup(): void
    {
        $user = $this->createUserWithRole(Role::Mitglied);
        $this->assertFalse($user->isOwnerOfTeam('New AG'));
        Team::factory()->create(['name' => 'New AG', 'user_id' => $user->id]);
        $this->assertTrue($user->isOwnerOfTeam('New AG'));
    }

    public function test_explicit_members_team_reset_also_discards_request_role_data(): void
    {
        $user = $this->createUserWithRole(Role::Admin);
        $this->assertSame(Role::Admin, $user->mitgliederTeamRole());
        DB::table('team_user')->where('user_id', $user->id)->update(['role' => Role::Mitglied->value]);
        Team::clearMembersTeamCache();
        $this->assertSame(Role::Mitglied, $user->mitgliederTeamRole());
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_transactions_discard_cached_reads_and_never_publish_rolled_back_data(bool $commit): void
    {
        $user = $this->createUserWithRole(Role::Admin);
        $team = Team::membersTeam();
        $this->assertSame(Role::Admin, $user->role());
        DB::beginTransaction();
        // Deliberately bypass model events to test the transaction lifecycle too.
        DB::table('team_user')->where('user_id', $user->id)->update(['role' => Role::Mitglied->value]);
        $this->assertSame(Role::Mitglied, $user->role());
        DB::beginTransaction();
        $team->users()->updateExistingPivot($user->id, ['role' => Role::Anwaerter->value]);
        $this->assertSame(Role::Anwaerter, $user->role());
        DB::rollBack();
        $this->assertSame(Role::Mitglied, $user->role());
        $commit ? DB::commit() : DB::rollBack();
        $this->assertSame($commit ? Role::Mitglied : Role::Admin, $user->role());
    }

    public function test_transaction_commit_invalidates_even_without_an_intermediate_context_read(): void
    {
        $user = $this->createUserWithRole(Role::Admin);
        $this->assertSame(Role::Admin, $user->role());
        DB::transaction(fn () => DB::table('team_user')->where('user_id', $user->id)->update(['role' => Role::Mitglied->value]));
        $this->assertSame(Role::Mitglied, $user->role());
    }

    public function test_transaction_retry_does_not_reuse_failed_attempt_data(): void
    {
        $user = $this->createUserWithRole(Role::Admin);
        $team = Team::membersTeam();
        $this->assertSame(Role::Admin, $user->role());
        $attempts = 0;
        DB::transaction(function () use ($user, $team, &$attempts): void {
            $this->assertSame(Role::Admin, $user->role());
            $team->users()->updateExistingPivot($user->id, ['role' => Role::Mitglied->value]);
            $this->assertSame(Role::Mitglied, $user->role());
            if (++$attempts === 1) {
                throw new DeadlockException('Deadlock found when trying to get lock');
            }
        }, 2);
        $this->assertSame(2, $attempts);
        $this->assertSame(Role::Mitglied, $user->role());
    }

    public function test_rollback_of_new_team_does_not_restore_a_nonexistent_row(): void
    {
        $user = $this->createUserWithRole(Role::Admin);
        DB::beginTransaction();
        $team = Team::factory()->create();
        $team->update(['name' => 'Temporary team']);
        DB::rollBack();
        $this->assertSame(Role::Admin, $user->role());
        $this->assertFalse(Team::whereKey($team->id)->exists());
    }

    public function test_locked_decision_ignores_previously_cached_role(): void
    {
        $user = $this->createUserWithRole(Role::Admin);
        $this->assertSame(Role::Admin, $user->role());
        DB::table('team_user')->where('user_id', $user->id)->update(['role' => Role::Anwaerter->value]);
        app(MembersTeamMembershipLock::class)->run([$user->id], function ($members) use ($user): void {
            $this->assertSame(Role::Anwaerter, $members->role($user->id));
            $this->assertFalse($members->isActiveMember($user->id));
            $this->assertSame(Role::Anwaerter, $user->role());
        });
    }

    public function test_rolled_back_team_switch_and_owner_change_restore_observed_models(): void
    {
        $user = $this->createUserWithRole(Role::Admin);
        $copy = $user->fresh();
        $this->assertSame(Role::Admin, $user->role());
        $ag = Team::factory()->create(['user_id' => $user->id]);
        $ag->users()->attach($user, ['role' => Role::Mitwirkender->value]);
        $teamCopy = $ag->fresh();
        DB::beginTransaction();
        $copy->switchTeam($ag);
        $teamCopy->update(['user_id' => User::factory()->create()->id]);
        $this->assertSame(Role::Mitwirkender, $user->role());
        $this->assertFalse($user->ownsTeam($ag));
        DB::rollBack();
        $this->assertSame(Role::Admin, $user->role());
        $this->assertSame(Role::Admin, $copy->role());
        $this->assertTrue($user->ownsTeam($ag));
        $this->assertFalse($user->isDirty('current_team_id'));
    }

    public function test_nested_fresh_scope_restores_read_caching_after_exception(): void
    {
        $user = $this->createUserWithRole(Role::Admin);
        $context = app(MembershipContext::class);
        try {
            $context->runFresh(function () use ($context, $user): void {
                $context->runFresh(fn () => $this->assertSame(Role::Admin, $user->role()));
                DB::table('team_user')->where('user_id', $user->id)->update(['role' => Role::Mitglied->value]);
                $this->assertSame(Role::Mitglied, $user->role());
                throw new RuntimeException('expected');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('expected', $exception->getMessage());
        }
        $snapshot = $context->forUserInTeam($user, Team::membersTeam());
        $this->assertSame($snapshot, $context->forUserInTeam($user, Team::membersTeam()));
        $this->assertSame(Role::Mitglied, $snapshot->role);
    }

    public function test_poll_legacy_fallback_keeps_its_existing_team_scope(): void
    {
        $user = User::factory()->create();
        $ag = Team::factory()->create();
        $ag->users()->attach($user, ['role' => Role::Mitglied->value]);
        app(PollVotingService::class)->assertMemberEligible($user);
        $this->assertNull($user->mitgliederTeamRole());
    }
}
