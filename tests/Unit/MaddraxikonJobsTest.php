<?php

namespace Tests\Unit;

use App\Jobs\EvaluateMaddraxikonContributions;
use App\Jobs\SyncMaddraxikonContributions;
use App\Models\MaddraxikonSyncState;
use App\Services\Maddraxikon\MaddraxikonContributionImporter;
use App\Services\Maddraxikon\MaddraxikonRewardService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MaddraxikonJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_job_forwards_force_flag_and_has_wiki_scoped_uniqueness(): void
    {
        config(['maddraxikon.wiki_key' => 'test-wiki']);
        $importer = Mockery::mock(MaddraxikonContributionImporter::class);
        $importer->expects('sync')->with(true)->once()->andReturn(4);
        $job = new SyncMaddraxikonContributions(force: true);

        $job->handle($importer);

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('test-wiki', $job->uniqueId());
        $this->assertSame(900, $job->uniqueFor);
    }

    public function test_evaluation_job_forwards_force_flag_and_has_wiki_scoped_uniqueness(): void
    {
        config(['maddraxikon.wiki_key' => 'test-wiki']);
        $rewardService = Mockery::mock(MaddraxikonRewardService::class);
        $rewardService->expects('evaluate')->with(false, null)->once()->andReturn(2);
        $job = new EvaluateMaddraxikonContributions;

        $job->handle($rewardService);

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('test-wiki', $job->uniqueId());
        $this->assertSame(3600, $job->uniqueFor);
    }

    public function test_targeted_evaluation_job_forwards_contribution_and_has_own_lock(): void
    {
        config(['maddraxikon.wiki_key' => 'test-wiki']);
        $rewardService = Mockery::mock(MaddraxikonRewardService::class);
        $rewardService->expects('evaluate')->with(false, 42)->once()->andReturn(1);
        $job = new EvaluateMaddraxikonContributions(
            contributionId: 42,
        );

        $job->handle($rewardService);

        $this->assertSame('test-wiki:contribution:42', $job->uniqueId());
    }

    public function test_database_retry_window_exceeds_longest_job_timeout(): void
    {
        $this->assertGreaterThan(
            300,
            (int) config('queue.connections.database.retry_after')
        );
    }

    public function test_evaluation_job_is_a_controlled_noop_during_recovery(): void
    {
        MaddraxikonSyncState::factory()->create([
            'recovery_required_at' => now(),
            'recovery_from_at' => now()->subDays(31),
            'recovery_until_at' => now(),
        ]);
        $rewardService = Mockery::mock(MaddraxikonRewardService::class);
        $rewardService->shouldNotReceive('evaluate');

        (new EvaluateMaddraxikonContributions)->handle($rewardService);

        $this->addToAssertionCount(1);
    }

    public function test_jobs_log_final_failures_without_rethrowing(): void
    {
        config(['maddraxikon.wiki_key' => 'test-wiki']);
        Log::spy();
        $exception = new RuntimeException('API nicht erreichbar');

        (new SyncMaddraxikonContributions)->failed($exception);
        (new EvaluateMaddraxikonContributions)->failed($exception);

        Log::shouldHaveReceived('error')
            ->with('Maddraxikon-Sync-Job endgültig fehlgeschlagen.', [
                'wiki_key' => 'test-wiki',
                'exception' => 'API nicht erreichbar',
            ])
            ->once();
        Log::shouldHaveReceived('error')
            ->with('Maddraxikon-Auswertungsjob endgültig fehlgeschlagen.', [
                'wiki_key' => 'test-wiki',
                'exception' => 'API nicht erreichbar',
            ])
            ->once();
    }

    public function test_failed_hook_accepts_a_missing_exception(): void
    {
        Log::spy();

        (new SyncMaddraxikonContributions)->failed(null);

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(static fn (string $message, array $context): bool => (
                $message === 'Maddraxikon-Sync-Job endgültig fehlgeschlagen.'
                && $context['exception'] === null
            ));
    }
}
