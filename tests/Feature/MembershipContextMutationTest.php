<?php

declare(strict_types=1);

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
use RuntimeException;

pest()->extend(MembershipContextMutationTestFixtures::class);

test('pivot mutations invalidate roles and all observed relations', function (string $operation): void {
    $user = $this->createUserWithRole(Role::Admin);
    $copy = $user->fresh();
    $team = Team::membersTeam();
    $context = app(MembershipContext::class);
    expect($user->role())->toBe(Role::Admin)
        ->and($copy->role())->toBe(Role::Admin);
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
    expect($user->relationLoaded('teams'))->toBeFalse()
        ->and($copy->relationLoaded('teams'))->toBeFalse()
        ->and($team->relationLoaded('users'))->toBeFalse()
        ->and($user->role())->toBe($expected)
        ->and($copy->mitgliederTeamRole())->toBe($expected)
        ->and($context->navigationRoles($copy)['members'])->toBe($expected);
})->with([['update'], ['sync'], ['detach'], ['detach-all'], ['model']]);

test('switching team updates observed instances without changing members role', function (): void {
    $user = $this->createUserWithRole(Role::Admin);
    $copy = $user->fresh();
    expect($user->role())->toBe(Role::Admin)
        ->and($copy->role())->toBe(Role::Admin);
    $ag = Team::factory()->create();
    $ag->users()->attach($user, ['role' => Role::Mitwirkender->value]);
    $copy->switchTeam($ag);
    expect($user->role())->toBe(Role::Mitwirkender)
        ->and($user->mitgliederTeamRole())->toBe(Role::Admin);
});

test('renaming and replacing members team invalidates named checks', function (): void {
    $user = $this->createUserWithRole(Role::Admin);
    $team = Team::membersTeam();
    expect($user->isMemberOfTeam('Mitglieder'))->toBeTrue()
        ->and($user->isMemberOfTeam('Archiv'))->toBeFalse()
        ->and($user->mitgliederTeamRole())->toBe(Role::Admin);
    $team->update(['name' => 'Archiv']);
    expect($user->mitgliederTeamRole())->toBeNull()
        ->and($user->isMemberOfTeam('Mitglieder'))->toBeFalse()
        ->and($user->isMemberOfTeam('Archiv'))->toBeTrue();
    $replacement = Team::factory()->create(['name' => 'Mitglieder']);
    $replacement->users()->attach($user, ['role' => Role::Mitglied->value]);
    expect($user->mitgliederTeamRole())->toBe(Role::Mitglied);
    $replacement->delete();
    expect($user->mitgliederTeamRole())->toBeNull();
});

test('owner change updates observed team and ownership checks', function (): void {
    $user = $this->createUserWithRole(Role::Mitglied);
    $team = Team::factory()->create(['name' => 'AG Test', 'user_id' => $user->id]);
    $copy = $team->fresh();
    app(MembershipContext::class)->track($team);
    expect($user->isOwnerOfTeam('AG Test'))->toBeTrue();
    $copy->update(['user_id' => User::factory()->create()->id]);
    expect($user->isOwnerOfTeam('AG Test'))->toBeFalse()
        ->and($user->ownsTeam($team))->toBeFalse();
});

test('team creation invalidates a previously missing ownership lookup', function (): void {
    $user = $this->createUserWithRole(Role::Mitglied);
    expect($user->isOwnerOfTeam('New AG'))->toBeFalse();
    Team::factory()->create(['name' => 'New AG', 'user_id' => $user->id]);
    expect($user->isOwnerOfTeam('New AG'))->toBeTrue();
});

test('explicit members team reset also discards request role data', function (): void {
    $user = $this->createUserWithRole(Role::Admin);
    expect($user->mitgliederTeamRole())->toBe(Role::Admin);
    DB::table('team_user')->where('user_id', $user->id)->update(['role' => Role::Mitglied->value]);
    Team::clearMembersTeamCache();
    expect($user->mitgliederTeamRole())->toBe(Role::Mitglied);
});

test('transactions discard cached reads and never publish rolled back data', function (bool $commit): void {
    $user = $this->createUserWithRole(Role::Admin);
    $team = Team::membersTeam();
    expect($user->role())->toBe(Role::Admin);
    DB::beginTransaction();
    // Deliberately bypass model events to test the transaction lifecycle too.
    DB::table('team_user')->where('user_id', $user->id)->update(['role' => Role::Mitglied->value]);
    expect($user->role())->toBe(Role::Mitglied);
    DB::beginTransaction();
    $team->users()->updateExistingPivot($user->id, ['role' => Role::Anwaerter->value]);
    expect($user->role())->toBe(Role::Anwaerter);
    DB::rollBack();
    expect($user->role())->toBe(Role::Mitglied);
    $commit ? DB::commit() : DB::rollBack();
    expect($user->role())->toBe($commit ? Role::Mitglied : Role::Admin);
})->with([[true], [false]]);

test('transaction commit invalidates even without an intermediate context read', function (): void {
    $user = $this->createUserWithRole(Role::Admin);
    expect($user->role())->toBe(Role::Admin);
    DB::transaction(fn () => DB::table('team_user')->where('user_id', $user->id)->update(['role' => Role::Mitglied->value]));
    expect($user->role())->toBe(Role::Mitglied);
});

test('transaction retry does not reuse failed attempt data', function (): void {
    $user = $this->createUserWithRole(Role::Admin);
    $team = Team::membersTeam();
    expect($user->role())->toBe(Role::Admin);
    $attempts = 0;
    DB::transaction(function () use ($user, $team, &$attempts): void {
        expect($user->role())->toBe(Role::Admin);
        $team->users()->updateExistingPivot($user->id, ['role' => Role::Mitglied->value]);
        expect($user->role())->toBe(Role::Mitglied);
        if (++$attempts === 1) {
            throw new DeadlockException('Deadlock found when trying to get lock');
        }
    }, 2);
    expect($attempts)->toBe(2)
        ->and($user->role())->toBe(Role::Mitglied);
});

test('rollback of new team does not restore a nonexistent row', function (): void {
    $user = $this->createUserWithRole(Role::Admin);
    DB::beginTransaction();
    $team = Team::factory()->create();
    $team->update(['name' => 'Temporary team']);
    DB::rollBack();
    expect($user->role())->toBe(Role::Admin)
        ->and(Team::whereKey($team->id)->exists())->toBeFalse();
});

test('locked decision ignores previously cached role', function (): void {
    $user = $this->createUserWithRole(Role::Admin);
    expect($user->role())->toBe(Role::Admin);
    DB::table('team_user')->where('user_id', $user->id)->update(['role' => Role::Anwaerter->value]);
    app(MembersTeamMembershipLock::class)->run([$user->id], function ($members) use ($user): void {
        expect($members->role($user->id))->toBe(Role::Anwaerter)
            ->and($members->isActiveMember($user->id))->toBeFalse()
            ->and($user->role())->toBe(Role::Anwaerter);
    });
});

test('rolled back team switch and owner change restore observed models', function (): void {
    $user = $this->createUserWithRole(Role::Admin);
    $copy = $user->fresh();
    expect($user->role())->toBe(Role::Admin);
    $ag = Team::factory()->create(['user_id' => $user->id]);
    $ag->users()->attach($user, ['role' => Role::Mitwirkender->value]);
    $teamCopy = $ag->fresh();
    DB::beginTransaction();
    $copy->switchTeam($ag);
    $teamCopy->update(['user_id' => User::factory()->create()->id]);
    expect($user->role())->toBe(Role::Mitwirkender)
        ->and($user->ownsTeam($ag))->toBeFalse();
    DB::rollBack();
    expect($user->role())->toBe(Role::Admin)
        ->and($copy->role())->toBe(Role::Admin)
        ->and($user->ownsTeam($ag))->toBeTrue()
        ->and($user->isDirty('current_team_id'))->toBeFalse();
});

test('nested fresh scope restores read caching after exception', function (): void {
    $user = $this->createUserWithRole(Role::Admin);
    $context = app(MembershipContext::class);
    try {
        $context->runFresh(function () use ($context, $user): void {
            $context->runFresh(fn () => expect($user->role())->toBe(Role::Admin));
            DB::table('team_user')->where('user_id', $user->id)->update(['role' => Role::Mitglied->value]);
            expect($user->role())->toBe(Role::Mitglied);
            throw new RuntimeException('expected');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('expected');
    }
    $snapshot = $context->forUserInTeam($user, Team::membersTeam());
    expect($context->forUserInTeam($user, Team::membersTeam()))->toBe($snapshot)
        ->and($snapshot->role)->toBe(Role::Mitglied);
});

test('poll legacy fallback keeps its existing team scope', function (): void {
    $user = User::factory()->create();
    $ag = Team::factory()->create();
    $ag->users()->attach($user, ['role' => Role::Mitglied->value]);
    app(PollVotingService::class)->assertMemberEligible($user);
    expect($user->mitgliederTeamRole())->toBeNull();
});
