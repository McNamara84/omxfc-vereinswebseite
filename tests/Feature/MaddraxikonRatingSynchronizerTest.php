<?php

namespace Tests\Feature;

use App\Data\MaddraxikonRatingData;
use App\Data\MaddraxikonRatingLookup;
use App\Data\MaddraxikonRatingSyncResult;
use App\Enums\MaddraxikonAccountLinkStatus;
use App\Models\Book;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonRatingSyncState;
use App\Models\MaddraxikonReviewRating;
use App\Models\Review;
use App\Models\User;
use App\Services\Maddraxikon\MaddraxikonRatingSource;
use App\Services\Maddraxikon\MaddraxikonRatingSynchronizer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MaddraxikonRatingSynchronizerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'maddraxikon.features.ratings_enabled' => true,
            'maddraxikon.wiki_key' => 'test-wiki',
            'maddraxikon.consent_version' => 'ratings-consent-v2',
        ]);
    }

    public function test_sync_creates_a_consistent_local_snapshot_and_success_state(): void
    {
        [$review, $book, $link] = $this->createCandidate();
        $votedAt = CarbonImmutable::parse('2026-08-09 12:00:00', 'UTC');
        $source = Mockery::mock(MaddraxikonRatingSource::class);
        $source->expects('ratingsFor')
            ->once()
            ->withArgs(function (array $lookups) use ($book, $link): bool {
                return count($lookups) === 1
                    && $lookups[0] instanceof MaddraxikonRatingLookup
                    && $lookups[0]->wikiUserId === $link->wiki_user_id
                    && $lookups[0]->pageId === $book->maddraxikon_page_id;
            })
            ->andReturn([
                MaddraxikonRatingLookup::makeKey(
                    $link->wiki_user_id,
                    $book->maddraxikon_page_id,
                ) => new MaddraxikonRatingData(
                    wikiUserId: $link->wiki_user_id,
                    pageId: $book->maddraxikon_page_id,
                    rating: 4,
                    votedAt: $votedAt,
                ),
            ]);

        $result = (new MaddraxikonRatingSynchronizer($source))->sync();

        $this->assertSame(1, $result->candidates);
        $this->assertSame(1, $result->updated);
        $this->assertSame(0, $result->removed);
        $this->assertDatabaseHas('maddraxikon_review_ratings', [
            'review_id' => $review->id,
            'book_id' => $book->id,
            'user_id' => $review->user_id,
            'account_link_id' => $link->id,
            'maddraxikon_page_id' => $book->maddraxikon_page_id,
            'wiki_user_id' => $link->wiki_user_id,
            'rating' => 4,
        ]);
        $state = MaddraxikonRatingSyncState::query()->sole();
        $this->assertSame('test-wiki', $state->wiki_key);
        $this->assertNotNull($state->last_started_at);
        $this->assertNotNull($state->last_succeeded_at);
        $this->assertSame(0, $state->consecutive_failures);
        $this->assertSame(1, $state->last_candidate_count);
        $this->assertSame(1, $state->last_updated_count);
        $snapshot = MaddraxikonReviewRating::query()->sole();
        $this->assertTrue($snapshot->review->is($review));
        $this->assertTrue($snapshot->book->is($book));
        $this->assertTrue($snapshot->user->is($review->user));
        $this->assertTrue($snapshot->accountLink->is($link));
    }

    public function test_sync_updates_changed_votes_and_removes_deleted_votes(): void
    {
        [$review, $book, $link] = $this->createCandidate();
        $snapshot = $this->createSnapshot($review, $book, $link, rating: 2);
        $key = MaddraxikonRatingLookup::makeKey(
            $link->wiki_user_id,
            $book->maddraxikon_page_id,
        );
        $source = Mockery::mock(MaddraxikonRatingSource::class);
        $source->expects('ratingsFor')->once()->andReturn([
            $key => new MaddraxikonRatingData(
                wikiUserId: $link->wiki_user_id,
                pageId: $book->maddraxikon_page_id,
                rating: 5,
                votedAt: CarbonImmutable::now('UTC'),
            ),
        ]);

        $first = (new MaddraxikonRatingSynchronizer($source))->sync();

        $this->assertSame(1, $first->updated);
        $this->assertSame(5, $snapshot->refresh()->rating);

        $source = Mockery::mock(MaddraxikonRatingSource::class);
        $source->expects('ratingsFor')->once()->andReturn([]);

        $second = (new MaddraxikonRatingSynchronizer($source))->sync();

        $this->assertSame(1, $second->removed);
        $this->assertDatabaseMissing('maddraxikon_review_ratings', [
            'review_id' => $review->id,
        ]);
    }

    public function test_disconnect_removes_snapshot_without_querying_the_source(): void
    {
        [$review, $book, $link] = $this->createCandidate();
        $this->createSnapshot($review, $book, $link);
        $link->update([
            'status' => MaddraxikonAccountLinkStatus::Disconnected,
            'disconnected_at' => now(),
        ]);
        $source = Mockery::mock(MaddraxikonRatingSource::class);
        $source->shouldNotReceive('ratingsFor');

        $result = (new MaddraxikonRatingSynchronizer($source))->sync();

        $this->assertSame(0, $result->candidates);
        $this->assertSame(1, $result->removed);
        $this->assertDatabaseMissing('maddraxikon_review_ratings', [
            'review_id' => $review->id,
        ]);
    }

    public function test_force_sync_gates_candidates_and_cleanup_on_wiki_and_consent(): void
    {
        [$review, $book, $link] = $this->createCandidate();
        $this->createSnapshot($review, $book, $link);
        $link->update(['wiki_key' => 'another-wiki']);
        $source = Mockery::mock(MaddraxikonRatingSource::class);
        $source->shouldNotReceive('ratingsFor');

        $wrongWiki = (new MaddraxikonRatingSynchronizer($source))->sync(force: true);

        $this->assertSame(0, $wrongWiki->candidates);
        $this->assertSame(1, $wrongWiki->removed);
        $this->assertDatabaseMissing('maddraxikon_review_ratings', [
            'review_id' => $review->id,
        ]);

        $link->update([
            'wiki_key' => 'test-wiki',
            'consent_version' => 'legacy-consent',
        ]);
        $this->createSnapshot($review, $book, $link);
        $source = Mockery::mock(MaddraxikonRatingSource::class);
        $source->shouldNotReceive('ratingsFor');

        $outdatedConsent = (new MaddraxikonRatingSynchronizer($source))->sync(force: true);

        $this->assertSame(0, $outdatedConsent->candidates);
        $this->assertSame(1, $outdatedConsent->removed);
        $this->assertDatabaseMissing('maddraxikon_review_ratings', [
            'review_id' => $review->id,
        ]);
    }

    public function test_inconsistent_snapshot_is_removed_even_when_no_candidate_remains(): void
    {
        [$review, $book, $link] = $this->createCandidate();
        $snapshot = $this->createSnapshot($review, $book, $link);
        $snapshot->update(['wiki_user_id' => $link->wiki_user_id + 1]);
        $book->update([
            'maddraxikon_page_id' => null,
            'maddraxikon_page_verified_at' => null,
        ]);
        $source = Mockery::mock(MaddraxikonRatingSource::class);
        $source->shouldNotReceive('ratingsFor');

        $result = (new MaddraxikonRatingSynchronizer($source))->sync();

        $this->assertSame(1, $result->removed);
        $this->assertDatabaseMissing('maddraxikon_review_ratings', [
            'review_id' => $review->id,
        ]);
    }

    public function test_source_failure_preserves_snapshot_and_records_only_exception_category(): void
    {
        [$review, $book, $link] = $this->createCandidate();
        $snapshot = $this->createSnapshot($review, $book, $link, rating: 3);
        $source = Mockery::mock(MaddraxikonRatingSource::class);
        $source->expects('ratingsFor')
            ->once()
            ->andThrow(new RuntimeException('secret-database-host'));

        try {
            (new MaddraxikonRatingSynchronizer($source))->sync();
            $this->fail('Die Quellausnahme wurde nicht weitergereicht.');
        } catch (RuntimeException $exception) {
            $this->assertSame('secret-database-host', $exception->getMessage());
        }

        $this->assertSame(3, $snapshot->refresh()->rating);
        $state = MaddraxikonRatingSyncState::query()->sole();
        $this->assertSame(1, $state->consecutive_failures);
        $this->assertSame('RuntimeException', $state->last_error_category);
        $this->assertNotNull($state->last_error_at);
        $this->assertNull($state->last_succeeded_at);
        $this->assertStringNotContainsString(
            'secret-database-host',
            (string) $state->last_error_category,
        );
    }

    public function test_dry_run_reports_changes_without_mutating_snapshots_or_state(): void
    {
        [$review, $book, $link] = $this->createCandidate();
        $snapshot = $this->createSnapshot($review, $book, $link, rating: 2);
        $key = MaddraxikonRatingLookup::makeKey(
            $link->wiki_user_id,
            $book->maddraxikon_page_id,
        );
        $source = Mockery::mock(MaddraxikonRatingSource::class);
        $source->expects('ratingsFor')->once()->andReturn([
            $key => new MaddraxikonRatingData(
                wikiUserId: $link->wiki_user_id,
                pageId: $book->maddraxikon_page_id,
                rating: 5,
                votedAt: CarbonImmutable::now('UTC'),
            ),
        ]);

        $result = (new MaddraxikonRatingSynchronizer($source))->sync(dryRun: true);

        $this->assertTrue($result->dryRun);
        $this->assertSame(1, $result->updated);
        $this->assertSame(2, $snapshot->refresh()->rating);
        $this->assertDatabaseCount('maddraxikon_rating_sync_states', 0);
    }

    public function test_disabled_feature_retains_snapshots_unless_force_is_used(): void
    {
        config(['maddraxikon.features.ratings_enabled' => false]);
        [$review, $book, $link] = $this->createCandidate();
        $this->createSnapshot($review, $book, $link);
        $source = Mockery::mock(MaddraxikonRatingSource::class);
        $source->shouldNotReceive('ratingsFor');

        $disabled = (new MaddraxikonRatingSynchronizer($source))->sync();

        $this->assertTrue($disabled->disabled);
        $this->assertDatabaseCount('maddraxikon_review_ratings', 1);
        $this->assertDatabaseCount('maddraxikon_rating_sync_states', 0);

        $source = Mockery::mock(MaddraxikonRatingSource::class);
        $source->expects('ratingsFor')->once()->andReturn([]);
        $forced = (new MaddraxikonRatingSynchronizer($source))->sync(force: true);

        $this->assertFalse($forced->disabled);
        $this->assertSame(1, $forced->candidates);
        $this->assertDatabaseHas('maddraxikon_rating_sync_states', [
            'wiki_key' => 'test-wiki',
        ]);
        $this->assertDatabaseMissing('maddraxikon_review_ratings', [
            'review_id' => $review->id,
        ]);
    }

    public function test_sync_command_forwards_options_and_reports_dry_run(): void
    {
        $synchronizer = Mockery::mock(MaddraxikonRatingSynchronizer::class);
        $synchronizer->expects('sync')
            ->with(true, true)
            ->once()
            ->andReturn(new MaddraxikonRatingSyncResult(
                candidates: 3,
                updated: 2,
                removed: 1,
                skipped: 0,
                dryRun: true,
            ));
        $this->app->instance(MaddraxikonRatingSynchronizer::class, $synchronizer);

        $this->artisan('maddraxikon:sync-review-ratings', [
            '--dry-run' => true,
            '--force' => true,
        ])
            ->expectsOutput('Dry-Run: Lokale Snapshots und Sync-Status blieben unverändert.')
            ->assertSuccessful();
    }

    public function test_sync_command_handles_disabled_feature_and_sanitizes_failures(): void
    {
        $synchronizer = Mockery::mock(MaddraxikonRatingSynchronizer::class);
        $synchronizer->expects('sync')
            ->with(false, false)
            ->once()
            ->andReturn(new MaddraxikonRatingSyncResult(disabled: true));
        $this->app->instance(MaddraxikonRatingSynchronizer::class, $synchronizer);

        $this->artisan('maddraxikon:sync-review-ratings')
            ->expectsOutput('Maddraxikon-Bewertungen sind deaktiviert; kein Sync ausgeführt.')
            ->assertSuccessful();

        $synchronizer = Mockery::mock(MaddraxikonRatingSynchronizer::class);
        $synchronizer->expects('sync')
            ->with(false, false)
            ->once()
            ->andThrow(new RuntimeException('secret-database-host'));
        $this->app->instance(MaddraxikonRatingSynchronizer::class, $synchronizer);

        $this->assertSame(1, Artisan::call('maddraxikon:sync-review-ratings'));
        $this->assertStringContainsString(
            'Maddraxikon-Bewertungssync fehlgeschlagen (RuntimeException).',
            Artisan::output(),
        );
        $this->assertStringNotContainsString('secret-database-host', Artisan::output());
    }

    /**
     * @return array{Review, Book, MaddraxikonAccountLink}
     */
    private function createCandidate(): array
    {
        $user = User::factory()->create();
        $book = Book::factory()->mapped(100, 'MX 100 – Testroman')->create();
        $review = Review::factory()->for($user)->for($book)->create();
        $link = MaddraxikonAccountLink::factory()->for($user)->create([
            'wiki_key' => 'test-wiki',
            'wiki_user_id' => 42,
        ]);

        return [$review, $book, $link];
    }

    private function createSnapshot(
        Review $review,
        Book $book,
        MaddraxikonAccountLink $link,
        int $rating = 4,
    ): MaddraxikonReviewRating {
        return MaddraxikonReviewRating::query()->create([
            'review_id' => $review->id,
            'book_id' => $book->id,
            'user_id' => $review->user_id,
            'account_link_id' => $link->id,
            'maddraxikon_page_id' => $book->maddraxikon_page_id,
            'wiki_user_id' => $link->wiki_user_id,
            'rating' => $rating,
            'source_voted_at' => now()->subHour(),
            'synced_at' => now(),
        ]);
    }
}
