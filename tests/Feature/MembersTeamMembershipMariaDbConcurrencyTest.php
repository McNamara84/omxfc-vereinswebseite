<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MaddraxikonContributionStatus;
use App\Enums\MaddraxikonContributionType;
use App\Enums\MaddraxikonRewardEventStatus;
use App\Enums\Role;
use App\Models\BaxxEarningRule;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonRewardEvent;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\MembersTeamMembershipLock;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

use function sprintf;

pest()->extend(TestCase::class)->use(MembersTeamMembershipMariaDbConcurrencyTestFixtures::class);

covers(MembersTeamMembershipLock::class);

afterEach(function (): void {
    try {
        if ($this->committedTestDatabase && isset($this->app)) {
            $exitCode = $this->artisan('migrate:fresh')->execute();
            if ($exitCode !== 0) {
                throw new RuntimeException(sprintf('Die MariaDB-Testdatenbank konnte nicht bereinigt werden (Exit %d).', $exitCode));
            }
        }
    } finally {
    }
});

test('cached role cannot override demotion committed by an independent process', function (): void {
    $this->requireSafeMariaDbTestDatabase();
    $team = Team::membersTeam();
    $user = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($user, ['role' => Role::Mitglied->value]);
    DB::commit();
    $this->committedTestDatabase = true;
    expect($user->role())->toBe(Role::Mitglied);
    $holder = $this->startWorker('hold-demotion', $user->id, 0);
    try {
        $locked = $this->readWorkerResult($holder, 10);
        expect($locked['event'] ?? null)->toBe('demotion_locked');
        $this->sendWorkerCommand($holder, 'COMMIT');
        expect($this->finishWorker($holder))->toBe(0);
    } finally {
        $this->terminateWorker($holder);
    }
    app(MembersTeamMembershipLock::class)->run([$user->id], function ($members) use ($user): void {
        expect($members->role($user->id))->toBe(Role::Anwaerter)
            ->and($members->isActiveMember($user->id))->toBeFalse()
            ->and($user->role())->toBe(Role::Anwaerter);
    });
});

test('raw role adapter preserves mariadb collation', function (): void {
    $this->requireSafeMariaDbTestDatabase();
    $team = Team::membersTeam();
    $user = User::factory()->create(['current_team_id' => $team->id]);
    foreach (['admin', 'Admin ', 'Gast', Role::Admin->value] as $rawRole) {
        $team->users()->syncWithoutDetaching([$user->id => ['role' => $rawRole]]);
        foreach (['Admin', 'admin', 'Admin ', 'Gast', Role::Mitglied->value] as $expected) {
            $original = $team->users()->where('user_id', $user->id)->wherePivot('role', $expected)->exists();
            expect($team->hasUserWithRole($user, $expected))->toBe($original);
        }
    }
});

test('pending demotion serializes against maddraxikon reward decision', function (): void {
    $this->requireSafeMariaDbTestDatabase();
    $team = Team::membersTeam();
    expect($team)->toBeInstanceOf(Team::class);
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    $verifiedAt = now()->subDays(2);
    $link = MaddraxikonAccountLink::factory()->create(['user_id' => $member->id, 'verified_at' => $verifiedAt, 'first_verified_at' => $verifiedAt, 'consented_at' => $verifiedAt]);
    $occurredAt = now()->subDay();
    $contribution = MaddraxikonContribution::factory()->newArticle()->create(['wiki_key' => $link->wiki_key, 'wiki_user_id' => $link->wiki_user_id, 'wiki_username' => $link->wiki_username, 'account_link_id' => $link->id, 'user_id' => $member->id, 'namespace_id' => 0, 'bot' => false, 'anonymous' => false, 'redirect' => false, 'user_hidden' => false, 'old_size' => 0, 'new_size' => 1000, 'occurred_at' => $occurredAt, 'eligible_after' => now()->subMinute(), 'status' => MaddraxikonContributionStatus::Pending, 'type' => MaddraxikonContributionType::New]);
    BaxxEarningRule::query()->updateOrCreate(['action_key' => MaddraxikonRewardEvent::ACTION_NEW_ARTICLE], ['label' => 'Maddraxikon-Artikel', 'description' => 'Concurrency-Testregel', 'points' => 5, 'every_count' => 1, 'is_active' => true]);
    expect(DB::transactionLevel())->toBe(1);
    DB::connection()->commit();
    $this->committedTestDatabase = true;
    $holder = $this->startWorker('hold-demotion', $member->id, $contribution->id);
    $holderFinished = false;
    try {
        $locked = $this->readWorkerResult($holder, 10);
        expect($locked['ok'] ?? false)->toBeTrue()
            ->and($locked['event'] ?? null)->toBe('demotion_locked');
        $blockedEvaluator = $this->startWorker('evaluate', $member->id, $contribution->id);
        try {
            $blocked = $this->readWorkerResult($blockedEvaluator, 15);
            $blockedExitCode = $this->finishWorker($blockedEvaluator);
        } finally {
            $this->terminateWorker($blockedEvaluator);
        }
        expect($blockedExitCode)->toBe(0)
            ->and($blocked['ok'] ?? false)->toBeTrue()
            ->and($blocked['status'] ?? null)->toBe(MaddraxikonContributionStatus::Pending->value)
            ->and($blocked['written'] ?? null)->toBe(0)
            ->and((int) $blocked['evaluation_attempts'] ?? 0)->toBeGreaterThanOrEqual(1)
            ->and((string) $blocked['last_evaluation_error'] ?? '')->toMatch('/(?:DeadlockException|QueryException): SQLSTATE\[/');
        $this->assertDatabaseHas('team_user', ['team_id' => $team->id, 'user_id' => $member->id, 'role' => Role::Mitglied->value]);
        $this->assertDatabaseMissing('user_points', ['user_id' => $member->id, 'team_id' => $team->id]);
        $this->sendWorkerCommand($holder, 'COMMIT');
        $committed = $this->readWorkerResult($holder, 10);
        expect($committed['ok'] ?? false)->toBeTrue()
            ->and($committed['event'] ?? null)->toBe('demotion_committed')
            ->and($this->finishWorker($holder))->toBe(0);
        $holderFinished = true;
        $finalEvaluator = $this->startWorker('evaluate', $member->id, $contribution->id);
        try {
            $final = $this->readWorkerResult($finalEvaluator, 10);
            $finalExitCode = $this->finishWorker($finalEvaluator);
        } finally {
            $this->terminateWorker($finalEvaluator);
        }
        expect($finalExitCode)->toBe(0)
            ->and($final['ok'] ?? false)->toBeTrue()
            ->and($final['written'] ?? null)->toBe(1)
            ->and($final['status'] ?? null)->toBe(MaddraxikonContributionStatus::Rejected->value)
            ->and($final['status_reason'] ?? null)->toBe('membership_inactive');
        $this->assertDatabaseHas('team_user', ['team_id' => $team->id, 'user_id' => $member->id, 'role' => Role::Anwaerter->value]);
        $this->assertDatabaseHas('maddraxikon_reward_events', ['source_contribution_id' => $contribution->id, 'status' => MaddraxikonRewardEventStatus::Rejected->value, 'status_reason' => 'membership_inactive', 'awarded_points' => 0]);
        expect(UserPoint::query()->where('user_id', $member->id)->where('team_id', $team->id)->sum('points'))->toBe(0);
    } finally {
        if (! $holderFinished) {
            $this->sendWorkerCommand($holder, 'ROLLBACK');
            $this->terminateWorker($holder);
        }
    }
});
