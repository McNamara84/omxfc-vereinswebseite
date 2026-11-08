<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Data\MaddraxikonRatingData;
use App\Data\MaddraxikonRatingLookup;
use App\Data\MaddraxikonRatingSyncResult;
use App\Enums\MaddraxikonAccountLinkStatus;
use App\Models\MaddraxikonRatingSyncState;
use App\Models\MaddraxikonReviewRating;
use App\Services\Maddraxikon\MaddraxikonRatingSource;
use App\Services\Maddraxikon\MaddraxikonRatingSynchronizer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxikonRatingSynchronizerTestFixtures::class);

beforeEach(function (): void {
    config(['maddraxikon.features.ratings_enabled' => true, 'maddraxikon.wiki_key' => 'test-wiki', 'maddraxikon.consent_version' => 'ratings-consent-v2']);
});

test('sync creates a consistent local snapshot and success state', function (): void {
    [$review, $book, $link] = $this->createCandidate();
    $votedAt = CarbonImmutable::parse('2026-08-09 12:00:00', 'UTC');
    $source = Mockery::mock(MaddraxikonRatingSource::class);
    $source->expects('ratingsFor')->once()->withArgs(fn (array $lookups): bool => count($lookups) === 1 && $lookups[0] instanceof MaddraxikonRatingLookup && $lookups[0]->wikiUserId === $link->wiki_user_id && $lookups[0]->pageId === $book->maddraxikon_page_id)->andReturn([MaddraxikonRatingLookup::makeKey($link->wiki_user_id, $book->maddraxikon_page_id) => new MaddraxikonRatingData(wikiUserId: $link->wiki_user_id, pageId: $book->maddraxikon_page_id, rating: 4, votedAt: $votedAt)]);
    $result = (new MaddraxikonRatingSynchronizer($source))->sync();
    expect($result->candidates)->toBe(1)
        ->and($result->updated)->toBe(1)
        ->and($result->removed)->toBe(0);
    $this->assertDatabaseHas('maddraxikon_review_ratings', ['review_id' => $review->id, 'book_id' => $book->id, 'user_id' => $review->user_id, 'account_link_id' => $link->id, 'maddraxikon_page_id' => $book->maddraxikon_page_id, 'wiki_user_id' => $link->wiki_user_id, 'rating' => 4]);
    $state = MaddraxikonRatingSyncState::query()->sole();
    expect($state->wiki_key)->toBe('test-wiki')
        ->and($state->last_started_at)->not->toBeNull()
        ->and($state->last_succeeded_at)->not->toBeNull()
        ->and($state->consecutive_failures)->toBe(0)
        ->and($state->last_candidate_count)->toBe(1)
        ->and($state->last_updated_count)->toBe(1);
    $snapshot = MaddraxikonReviewRating::query()->sole();
    expect($snapshot->review->is($review))->toBeTrue()
        ->and($snapshot->book->is($book))->toBeTrue()
        ->and($snapshot->user->is($review->user))->toBeTrue()
        ->and($snapshot->accountLink->is($link))->toBeTrue();
});

test('sync updates changed votes and removes deleted votes', function (): void {
    [$review, $book, $link] = $this->createCandidate();
    $snapshot = $this->createSnapshot($review, $book, $link, rating: 2);
    $key = MaddraxikonRatingLookup::makeKey($link->wiki_user_id, $book->maddraxikon_page_id);
    $source = Mockery::mock(MaddraxikonRatingSource::class);
    $source->expects('ratingsFor')->once()->andReturn([$key => new MaddraxikonRatingData(wikiUserId: $link->wiki_user_id, pageId: $book->maddraxikon_page_id, rating: 5, votedAt: CarbonImmutable::now('UTC'))]);
    $first = (new MaddraxikonRatingSynchronizer($source))->sync();
    expect($first->updated)->toBe(1)
        ->and($snapshot->refresh()->rating)->toBe(5);
    $source = Mockery::mock(MaddraxikonRatingSource::class);
    $source->expects('ratingsFor')->once()->andReturn([]);
    $second = (new MaddraxikonRatingSynchronizer($source))->sync();
    expect($second->removed)->toBe(1);
    $this->assertDatabaseMissing('maddraxikon_review_ratings', ['review_id' => $review->id]);
});

test('disconnect removes snapshot without querying the source', function (): void {
    [$review, $book, $link] = $this->createCandidate();
    $this->createSnapshot($review, $book, $link);
    $link->update(['status' => MaddraxikonAccountLinkStatus::Disconnected, 'disconnected_at' => now()]);
    $source = Mockery::mock(MaddraxikonRatingSource::class);
    $source->shouldNotReceive('ratingsFor');
    $result = (new MaddraxikonRatingSynchronizer($source))->sync();
    expect($result->candidates)->toBe(0)
        ->and($result->removed)->toBe(1);
    $this->assertDatabaseMissing('maddraxikon_review_ratings', ['review_id' => $review->id]);
});

test('force sync gates candidates and cleanup on wiki and consent', function (): void {
    [$review, $book, $link] = $this->createCandidate();
    $this->createSnapshot($review, $book, $link);
    $link->update(['wiki_key' => 'another-wiki']);
    $source = Mockery::mock(MaddraxikonRatingSource::class);
    $source->shouldNotReceive('ratingsFor');
    $wrongWiki = (new MaddraxikonRatingSynchronizer($source))->sync(force: true);
    expect($wrongWiki->candidates)->toBe(0)
        ->and($wrongWiki->removed)->toBe(1);
    $this->assertDatabaseMissing('maddraxikon_review_ratings', ['review_id' => $review->id]);
    $link->update(['wiki_key' => 'test-wiki', 'consent_version' => 'legacy-consent']);
    $this->createSnapshot($review, $book, $link);
    $source = Mockery::mock(MaddraxikonRatingSource::class);
    $source->shouldNotReceive('ratingsFor');
    $outdatedConsent = (new MaddraxikonRatingSynchronizer($source))->sync(force: true);
    expect($outdatedConsent->candidates)->toBe(0)
        ->and($outdatedConsent->removed)->toBe(1);
    $this->assertDatabaseMissing('maddraxikon_review_ratings', ['review_id' => $review->id]);
});

test('inconsistent snapshot is removed even when no candidate remains', function (): void {
    [$review, $book, $link] = $this->createCandidate();
    $snapshot = $this->createSnapshot($review, $book, $link);
    $snapshot->update(['wiki_user_id' => $link->wiki_user_id + 1]);
    $book->update(['maddraxikon_page_id' => null, 'maddraxikon_page_verified_at' => null]);
    $source = Mockery::mock(MaddraxikonRatingSource::class);
    $source->shouldNotReceive('ratingsFor');
    $result = (new MaddraxikonRatingSynchronizer($source))->sync();
    expect($result->removed)->toBe(1);
    $this->assertDatabaseMissing('maddraxikon_review_ratings', ['review_id' => $review->id]);
});

test('source failure preserves snapshot and records only exception category', function (): void {
    [$review, $book, $link] = $this->createCandidate();
    $snapshot = $this->createSnapshot($review, $book, $link, rating: 3);
    $source = Mockery::mock(MaddraxikonRatingSource::class);
    $source->expects('ratingsFor')->once()->andThrow(new RuntimeException('secret-database-host'));
    try {
        (new MaddraxikonRatingSynchronizer($source))->sync();
        $this->fail('Die Quellausnahme wurde nicht weitergereicht.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('secret-database-host');
    }
    expect($snapshot->refresh()->rating)->toBe(3);
    $state = MaddraxikonRatingSyncState::query()->sole();
    expect($state->consecutive_failures)->toBe(1)
        ->and($state->last_error_category)->toBe('RuntimeException')
        ->and($state->last_error_at)->not->toBeNull()
        ->and($state->last_succeeded_at)->toBeNull()
        ->and((string) $state->last_error_category)->not->toContain('secret-database-host');
});

test('dry run reports changes without mutating snapshots or state', function (): void {
    [$review, $book, $link] = $this->createCandidate();
    $snapshot = $this->createSnapshot($review, $book, $link, rating: 2);
    $key = MaddraxikonRatingLookup::makeKey($link->wiki_user_id, $book->maddraxikon_page_id);
    $source = Mockery::mock(MaddraxikonRatingSource::class);
    $source->expects('ratingsFor')->once()->andReturn([$key => new MaddraxikonRatingData(wikiUserId: $link->wiki_user_id, pageId: $book->maddraxikon_page_id, rating: 5, votedAt: CarbonImmutable::now('UTC'))]);
    $result = (new MaddraxikonRatingSynchronizer($source))->sync(dryRun: true);
    expect($result->dryRun)->toBeTrue()
        ->and($result->updated)->toBe(1)
        ->and($snapshot->refresh()->rating)->toBe(2);
    $this->assertDatabaseCount('maddraxikon_rating_sync_states', 0);
});

test('disabled feature retains snapshots unless force is used', function (): void {
    config(['maddraxikon.features.ratings_enabled' => false]);
    [$review, $book, $link] = $this->createCandidate();
    $this->createSnapshot($review, $book, $link);
    $source = Mockery::mock(MaddraxikonRatingSource::class);
    $source->shouldNotReceive('ratingsFor');
    $disabled = (new MaddraxikonRatingSynchronizer($source))->sync();
    expect($disabled->disabled)->toBeTrue();
    $this->assertDatabaseCount('maddraxikon_review_ratings', 1);
    $this->assertDatabaseCount('maddraxikon_rating_sync_states', 0);
    $source = Mockery::mock(MaddraxikonRatingSource::class);
    $source->expects('ratingsFor')->once()->andReturn([]);
    $forced = (new MaddraxikonRatingSynchronizer($source))->sync(force: true);
    expect($forced->disabled)->toBeFalse()
        ->and($forced->candidates)->toBe(1);
    $this->assertDatabaseHas('maddraxikon_rating_sync_states', ['wiki_key' => 'test-wiki']);
    $this->assertDatabaseMissing('maddraxikon_review_ratings', ['review_id' => $review->id]);
});

test('sync command forwards options and reports dry run', function (): void {
    $synchronizer = Mockery::mock(MaddraxikonRatingSynchronizer::class);
    $synchronizer->expects('sync')->with(true, true)->once()->andReturn(new MaddraxikonRatingSyncResult(candidates: 3, updated: 2, removed: 1, skipped: 0, dryRun: true));
    $this->app->instance(MaddraxikonRatingSynchronizer::class, $synchronizer);
    $this->artisan('maddraxikon:sync-review-ratings', ['--dry-run' => true, '--force' => true])->expectsOutput('Dry-Run: Lokale Snapshots und Sync-Status blieben unverändert.')->assertSuccessful();
});

test('sync command handles disabled feature and sanitizes failures', function (): void {
    $synchronizer = Mockery::mock(MaddraxikonRatingSynchronizer::class);
    $synchronizer->expects('sync')->with(false, false)->once()->andReturn(new MaddraxikonRatingSyncResult(disabled: true));
    $this->app->instance(MaddraxikonRatingSynchronizer::class, $synchronizer);
    $this->artisan('maddraxikon:sync-review-ratings')->expectsOutput('Maddraxikon-Bewertungen sind deaktiviert; kein Sync ausgeführt.')->assertSuccessful();
    $synchronizer = Mockery::mock(MaddraxikonRatingSynchronizer::class);
    $synchronizer->expects('sync')->with(false, false)->once()->andThrow(new RuntimeException('secret-database-host'));
    $this->app->instance(MaddraxikonRatingSynchronizer::class, $synchronizer);
    expect(Artisan::call('maddraxikon:sync-review-ratings'))->toBe(1)
        ->and(Artisan::output())->toContain('Maddraxikon-Bewertungssync fehlgeschlagen (RuntimeException).')
        ->and(Artisan::output())->not->toContain('secret-database-host');
});
