<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Data\MaddraxikonRatingSyncResult;
use App\Jobs\EvaluateMaddraxikonContributions;
use App\Jobs\SyncCoverRatingCovers;
use App\Jobs\SyncMaddraxikonContributions;
use App\Jobs\SyncMaddraxikonReviewRatings;
use App\Models\MaddraxikonSyncState;
use App\Services\CoverRatings\BookCoverSyncRunner;
use App\Services\CoverRatings\CoverSyncDeadline;
use App\Services\Maddraxikon\MaddraxikonContributionImporter;
use App\Services\Maddraxikon\MaddraxikonRatingSynchronizer;
use App\Services\Maddraxikon\MaddraxikonRewardService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('sync job forwards force flag and has wiki scoped uniqueness', function (): void {
    config(['maddraxikon.wiki_key' => 'test-wiki']);
    $importer = Mockery::mock(MaddraxikonContributionImporter::class);
    $importer->expects('sync')->with(true)->once()->andReturn(4);
    $job = new SyncMaddraxikonContributions(force: true);
    $job->handle($importer);
    expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe('test-wiki')
        ->and($job->uniqueFor)->toBe(900);
});

test('evaluation job forwards force flag and has wiki scoped uniqueness', function (): void {
    config(['maddraxikon.wiki_key' => 'test-wiki']);
    $rewardService = Mockery::mock(MaddraxikonRewardService::class);
    $rewardService->expects('evaluate')->with(false, null)->once()->andReturn(2);
    $job = new EvaluateMaddraxikonContributions;
    $job->handle($rewardService);
    expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe('test-wiki')
        ->and($job->uniqueFor)->toBe(3600);
});

test('rating job runs the synchronizer and has its own wiki scoped lock', function (): void {
    config(['maddraxikon.wiki_key' => 'test-wiki']);
    $synchronizer = Mockery::mock(MaddraxikonRatingSynchronizer::class);
    $synchronizer->expects('sync')->with()->once()->andReturn(new MaddraxikonRatingSyncResult);
    $job = new SyncMaddraxikonReviewRatings;
    $job->handle($synchronizer);
    expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe('test-wiki:review-ratings')
        ->and($job->uniqueFor)->toBe(900);
});

test('cover job is unique and honors the sync feature flag', function (): void {
    $runner = Mockery::mock(BookCoverSyncRunner::class);
    $runner->shouldNotReceive('run');
    $job = new SyncCoverRatingCovers;
    config(['cover-ratings.sync_enabled' => false]);
    $job->handle($runner);
    expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe('cover-ratings:sync-covers')
        ->and($job->uniqueFor)->toBe(7200);
    config(['cover-ratings.sync_enabled' => true]);
    $runner = Mockery::mock(BookCoverSyncRunner::class);
    $runner->expects('run')->withArgs(function (Builder $query, bool $force, bool $dryRun, ?callable $report, CoverSyncDeadline $deadline) use ($job): bool {
        expect($force)->toBeFalse()
            ->and($dryRun)->toBeFalse()
            ->and($report)->toBeNull()
            ->and($deadline->remainingSeconds())->toBeGreaterThan(269)
            ->and($deadline->remainingSeconds())->toBeLessThanOrEqual($job->timeout - 60);

        return true;
    })->once()->andReturn(['ready' => 0, 'unchanged' => 0, 'missing' => 0, 'changed' => 0, 'failed' => 0]);
    $job->handle($runner);
});

test('targeted evaluation job forwards contribution and has own lock', function (): void {
    config(['maddraxikon.wiki_key' => 'test-wiki']);
    $rewardService = Mockery::mock(MaddraxikonRewardService::class);
    $rewardService->expects('evaluate')->with(false, 42)->once()->andReturn(1);
    $job = new EvaluateMaddraxikonContributions(contributionId: 42);
    $job->handle($rewardService);
    expect($job->uniqueId())->toBe('test-wiki:contribution:42');
});

test('database retry window exceeds longest job timeout', function (): void {
    expect((int) config('queue.connections.database.retry_after'))->toBeGreaterThan((new SyncCoverRatingCovers)->timeout);
});

test('evaluation job is a controlled noop during recovery', function (): void {
    MaddraxikonSyncState::factory()->create(['recovery_required_at' => now(), 'recovery_from_at' => now()->subDays(31), 'recovery_until_at' => now()]);
    $rewardService = Mockery::mock(MaddraxikonRewardService::class);
    $rewardService->shouldNotReceive('evaluate');
    (new EvaluateMaddraxikonContributions)->handle($rewardService);
    $this->addToAssertionCount(1);
});

test('jobs log final failures without rethrowing', function (): void {
    config(['maddraxikon.wiki_key' => 'test-wiki']);
    Log::spy();
    $exception = new RuntimeException('API nicht erreichbar');
    (new SyncMaddraxikonContributions)->failed($exception);
    (new EvaluateMaddraxikonContributions)->failed($exception);
    Log::shouldHaveReceived('error')->with('Maddraxikon-Sync-Job endgültig fehlgeschlagen.', ['wiki_key' => 'test-wiki', 'exception' => 'API nicht erreichbar'])->once();
    Log::shouldHaveReceived('error')->with('Maddraxikon-Auswertungsjob endgültig fehlgeschlagen.', ['wiki_key' => 'test-wiki', 'exception' => 'API nicht erreichbar'])->once();
});

test('failed hook accepts a missing exception', function (): void {
    Log::spy();
    (new SyncMaddraxikonContributions)->failed(null);
    Log::shouldHaveReceived('error')->once()->withArgs(static fn (string $message, array $context): bool => $message === 'Maddraxikon-Sync-Job endgültig fehlgeschlagen.' && $context['exception'] === null);
});

test('rating job logs only the final exception category', function (): void {
    config(['maddraxikon.wiki_key' => 'test-wiki']);
    Log::spy();
    (new SyncMaddraxikonReviewRatings)->failed(new RuntimeException('secret-database-host'));
    Log::shouldHaveReceived('error')->once()->with('Maddraxikon-Bewertungssync-Job endgültig fehlgeschlagen.', ['wiki_key' => 'test-wiki', 'exception_category' => 'RuntimeException']);
});
