<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MaddraxikonContributionStatus;
use App\Enums\MaddraxikonRewardEventStatus;
use App\Models\BaxxEarningRule;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonRewardEvent;
use App\Models\MaddraxikonSyncState;
use App\Models\UserPoint;
use App\Services\Maddraxikon\MaddraxikonContributionImporter;
use App\Services\Maddraxikon\MaddraxikonRewardService;
use RuntimeException;
use Tests\TestCase;

use function array_sum;
use function sprintf;

pest()->extend(TestCase::class)->use(MaddraxikonPipelineMariaDbConcurrencyTestFixtures::class);

covers(MaddraxikonContributionImporter::class);

covers(MaddraxikonRewardService::class);

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

test('two evaluators award the same source exactly once', function (): void {
    $this->requireSafeMariaDbTestDatabase();
    [$team, $user, $link] = $this->linkedMember();
    MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'watermark_at' => now(), 'last_succeeded_at' => now()]);
    BaxxEarningRule::query()->updateOrCreate(['action_key' => MaddraxikonRewardEvent::ACTION_NEW_ARTICLE], ['label' => 'Maddraxikon-Artikel', 'description' => 'MariaDB-Paralleltest', 'points' => 5, 'every_count' => 1, 'is_active' => true]);
    $contribution = MaddraxikonContribution::factory()->newArticle()->create(['wiki_key' => $link->wiki_key, 'revision_id' => 91000001, 'rc_id' => 91020001, 'page_id' => 91010001, 'wiki_user_id' => $link->wiki_user_id, 'wiki_username' => $link->wiki_username, 'account_link_id' => $link->id, 'user_id' => $user->id, 'occurred_at' => now()->subDay(), 'eligible_after' => now()->subMinute(), 'old_size' => 0, 'new_size' => 1000, 'status' => MaddraxikonContributionStatus::Pending]);
    $this->commitFixturesForWorkers();
    $workers = [$this->startWorker('evaluate', $user->id, $contribution->id), $this->startWorker('evaluate', $user->id, $contribution->id)];
    $results = [];
    try {
        foreach ($workers as $worker) {
            $ready = $this->readWorkerResult($worker, 15);
            expect($ready['ok'] ?? false)->toBeTrue()
                ->and($ready['event'] ?? null)->toBe('evaluator_ready');
        }
        foreach ($workers as $worker) {
            $this->sendWorkerCommand($worker, 'EVALUATE');
        }
        foreach ($workers as $index => $worker) {
            $results[$index] = $this->readWorkerResult($worker, 20);
        }
        foreach ($workers as $index => &$worker) {
            $exitCode = $this->finishWorker($worker);
            expect($exitCode)->toBe(0);
        }
        unset($worker);
    } finally {
        foreach ($workers as &$worker) {
            $this->terminateWorker($worker);
        }
        unset($worker);
    }
    foreach ($results as $result) {
        expect($result['ok'] ?? false)->toBeTrue()
            ->and($result['event'] ?? null)->toBe('evaluation_finished');
    }
    expect(array_sum(array_map(static fn (array $result): int => (int) $result['written'], $results)))->toBe(1);
    $this->assertDatabaseCount('maddraxikon_reward_events', 1);
    $this->assertDatabaseHas('maddraxikon_reward_events', ['wiki_key' => 'maddraxikon-de', 'source_contribution_id' => $contribution->id, 'source_key' => 'new:'.$contribution->revision_id, 'status' => MaddraxikonRewardEventStatus::Awarded->value, 'awarded_points' => 5]);
    $this->assertDatabaseCount('user_points', 1);
    expect((int) UserPoint::query()->where('user_id', $user->id)->where('team_id', $team->id)->sum('points'))->toBe(5)
        ->and($contribution->fresh()->status)->toBe(MaddraxikonContributionStatus::Awarded);
});

test('two importers persist the same recent change exactly once', function (): void {
    $this->requireSafeMariaDbTestDatabase();
    [, $user, $link] = $this->linkedMember();
    MaddraxikonSyncState::factory()->create(['wiki_key' => 'maddraxikon-de', 'watermark_at' => now()->subHour(), 'initial_watermark_at' => now()->subDay(), 'last_succeeded_at' => now()->subHour(), 'run_sequence' => 0]);
    $revisionId = 92000001;
    $this->commitFixturesForWorkers();
    $workers = [$this->startWorker('import', $user->id, $revisionId), $this->startWorker('import', $user->id, $revisionId)];
    $results = [];
    try {
        foreach ($workers as $worker) {
            $ready = $this->readWorkerResult($worker, 15);
            expect($ready['ok'] ?? false)->toBeTrue()
                ->and($ready['event'] ?? null)->toBe('import_api_ready');
        }
        foreach ($workers as $worker) {
            $this->sendWorkerCommand($worker, 'PERSIST');
        }
        foreach ($workers as $index => $worker) {
            $results[$index] = $this->readWorkerResult($worker, 20);
        }
        foreach ($workers as $index => &$worker) {
            $exitCode = $this->finishWorker($worker);
            expect($exitCode)->toBe(0);
        }
        unset($worker);
    } finally {
        foreach ($workers as &$worker) {
            $this->terminateWorker($worker);
        }
        unset($worker);
    }
    foreach ($results as $result) {
        expect($result['ok'] ?? false)->toBeTrue()
            ->and($result['event'] ?? null)->toBe('import_finished');
    }
    expect(array_sum(array_map(static fn (array $result): int => (int) $result['written'], $results)))->toBe(1);
    $this->assertDatabaseCount('maddraxikon_contributions', 1);
    $this->assertDatabaseHas('maddraxikon_contributions', ['wiki_key' => 'maddraxikon-de', 'revision_id' => $revisionId, 'account_link_id' => $link->id, 'user_id' => $user->id, 'status' => MaddraxikonContributionStatus::Pending->value]);
    $state = MaddraxikonSyncState::query()->where('wiki_key', 'maddraxikon-de')->firstOrFail();
    expect($state->run_sequence)->toBe(2)
        ->and($state->last_seen_rc_id)->toBe($revisionId + 20000);
});
