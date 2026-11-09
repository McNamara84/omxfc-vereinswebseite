<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SyncCoverRatingCovers;
use App\Models\Book;
use App\Models\BookCoverKeyword;
use App\Models\CoverRating;
use App\Services\BildersucheService;
use App\Services\CoverRatings\BookCoverSynchronizer;
use App\Services\CoverRatings\BookCoverSyncRunner;
use App\Services\CoverRatings\CoverImageDownloader;
use App\Services\CoverRatings\CoverImageProcessor;
use App\Services\CoverRatings\CoverSyncDeadline;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(BookCoverKeywordSyncTestFixtures::class);

test('updates removals deduplicates and recognizes unchanged keywords', function (): void {
    $cover = $this->searchCover(1, ['Aruula', 'Schwert']);
    expect($this->sync($cover, ['Aruula', 'aruula', 'Drax']))->toBe('keywords_updated')
        ->and($cover->keywords()->pluck('keyword')->all())->toBe(['Aruula', 'Drax'])
        ->and($this->sync($cover->fresh(), ['Aruula', 'Drax']))->toBe('keywords_unchanged')
        ->and($this->sync($cover, []))->toBe('keywords_empty')
        ->and($cover->keywords()->count())->toBe(0)
        ->and($cover->fresh()->keywords_synced_at)->not->toBeNull();
});

test('failure preserves keywords and last successful time', function (): void {
    $cover = $this->searchCover(1, ['Aruula']);
    $cover->update(['keywords_synced_at' => now()->subDay()]);
    $previous = $cover->keywords_synced_at;
    expect($this->sync($cover, null))->toBe('keywords_failed')
        ->and($cover->keywords()->pluck('keyword')->all())->toBe(['Aruula'])
        ->and($previous->equalTo($cover->fresh()->keywords_synced_at))->toBeTrue()
        ->and($cover->fresh()->keywords_sync_error)->toBe('Timeout')
        ->and($cover->fresh()->last_error)->toBeNull()
        ->and($this->sync($cover, ['Drax', null]))->toBe('keywords_failed')
        ->and($cover->keywords()->pluck('keyword')->all())->toBe(['Aruula']);
    $this->sync($cover, ['Aruula']);
    expect($cover->fresh()->keywords_sync_error)->toBeNull();
});

test('dry run and source race never overwrite persisted keywords', function (): void {
    $cover = $this->searchCover(1, ['Aruula']);
    $original = $cover->fresh()->getAttributes();
    expect($this->sync($cover, ['Drax'], true))->toBe('keywords_updated')
        ->and($this->sync($cover, null, true))->toBe('keywords_failed')
        ->and($cover->fresh()->getAttributes())->toBe($original);
    $cover->fresh()->update(['source_file_title' => 'Datei:New.jpg']);
    expect($this->sync($cover, ['Drax']))->toBe('keywords_failed')
        ->and($cover->keywords()->pluck('keyword')->all())->toBe(['Aruula']);
});

test('database error rolls back the whole keyword replacement', function (): void {
    $cover = $this->searchCover(1, ['Aruula']);
    BookCoverKeyword::creating(fn () => throw new RuntimeException('database error'));
    try {
        $this->sync($cover, ['Drax']);
        $this->fail('Expected failure');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('database error')
            ->and($cover->keywords()->pluck('keyword')->all())->toBe(['Aruula']);
    } finally {
        BookCoverKeyword::flushEventListeners();
    }
});

test('keywords belong to the cover and are deleted with it', function (): void {
    $cover = $this->searchCover(1, ['Aruula']);
    $keyword = $cover->keywords()->firstOrFail();
    expect($keyword->cover->is($cover))->toBeTrue();
    $cover->delete();
    $this->assertModelMissing($keyword);
});

test('new source stays unsearchable until its own keywords are synced', function (): void {
    $cover = $this->searchCover(1, ['Aruula']);
    $cover->update(['source_file_title' => 'Datei:New.jpg']);
    $search = app(BildersucheService::class);
    expect($search->search('Aruula')->total())->toBe(0)
        ->and($this->sync($cover, ['Drax']))->toBe('keywords_updated')
        ->and($search->search('Aruula')->total())->toBe(0)
        ->and($search->search('Drax')->total())->toBe(1);
});

test('runner updates keywords for unchanged images and preserves actual source on change', function (): void {
    config(['bildersuche.sync_enabled' => true]);
    $cover = $this->searchCover(1, ['Aruula']);
    $cover->book->update(['maddraxikon_page_id' => 101]);
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->expects('coverImages')->with([101], Mockery::type(CoverSyncDeadline::class))->andReturn([101 => ['exists' => true, 'file_title' => 'Datei:Proposed.jpg']]);
    $api->expects('coverKeywords')->with([$cover->source_file_title], Mockery::type(CoverSyncDeadline::class))->andReturn([$cover->source_file_title => ['keywords' => ['Drax'], 'error' => null]]);
    $images = Mockery::mock(BookCoverSynchronizer::class);
    $images->expects('sync')->andReturn('unchanged');
    $counts = (new BookCoverSyncRunner($api, $images))->run(Book::query());
    expect($counts['unchanged'])->toBe(1)
        ->and($counts['keywords_updated'])->toBe(1)
        ->and($cover->keywords()->pluck('keyword')->all())->toBe(['Drax']);
});

test('failed image lookup still updates existing keyword sources', function (): void {
    config(['bildersuche.sync_enabled' => true]);
    $cover = $this->searchCover(1, ['Aruula']);
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->expects('coverImages')->andThrow(new RuntimeException('API error'));
    $api->expects('coverKeywords')->andReturn([$cover->source_file_title => ['keywords' => ['Drax'], 'error' => null]]);
    $images = Mockery::mock(BookCoverSynchronizer::class);
    $images->shouldNotReceive('sync');
    $counts = (new BookCoverSyncRunner($api, $images))->run(Book::query());
    expect($counts['failed'])->toBe(1)
        ->and($counts['keywords_updated'])->toBe(1);
});

test('runner loads keyword covers once per batch instead of per book', function (int $bookCount, bool $dryRun): void {
    config(['bildersuche.sync_enabled' => true, 'cover-ratings.sync.batch_size' => 5]);
    foreach (range(1, $bookCount) as $number) {
        $this->searchCover($number, ['Aruula']);
    }
    $batches = (int) ceil($bookCount / 5);
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->expects('coverImages')->times($batches)->andReturn([]);
    $api->expects('coverKeywords')->times($batches)->andReturnUsing(fn (array $titles): array => array_fill_keys($titles, ['keywords' => ['Drax'], 'error' => null]));
    $images = Mockery::mock(BookCoverSynchronizer::class);
    $images->expects('sync')->times($bookCount)->andReturn('unchanged');
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $counts = (new BookCoverSyncRunner($api, $images))->run(Book::query(), dryRun: $dryRun);
        $coverQueries = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql) => preg_match('/^select .* from [`"]book_covers[`"] /i', $sql));
        // The write path must retain one locked reread per cover for source-race
        // protection. All other cover reads belong to the batch preload.
        expect($coverQueries)->toHaveCount($batches + ($dryRun ? 0 : $bookCount));
        expect($coverQueries->filter(fn (string $sql) => str_contains($sql, ' in (')))->toHaveCount($batches)
            ->and($counts['keywords_updated'])->toBe($bookCount);
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
})->with([[1, false], [6, false], [11, false], [1, true], [6, true], [11, true]]);

test('runner reads new and replaced covers after image synchronization', function (): void {
    config(['bildersuche.sync_enabled' => true]);
    Storage::fake('private');
    $newBook = Book::factory()->mapped(101)->create(['roman_number' => 1]);
    $existingCover = $this->searchCover(2, ['Aruula']);
    $existingCover->book->update(['maddraxikon_page_id' => 102]);
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->expects('coverImages')->with([101, 102], Mockery::type(CoverSyncDeadline::class))->andReturn([101 => ['exists' => true, 'file_title' => 'Datei:New.jpg', 'url' => 'https://example.test/new.jpg'], 102 => ['exists' => true, 'file_title' => 'Datei:Replacement.jpg', 'url' => 'https://example.test/replacement.jpg']]);
    $api->expects('coverKeywords')->with(['Datei:New.jpg', 'Datei:Replacement.jpg'], Mockery::type(CoverSyncDeadline::class))->andReturn(['Datei:New.jpg' => ['keywords' => ['Neues Motiv'], 'error' => null], 'Datei:Replacement.jpg' => ['keywords' => ['Anderes Motiv'], 'error' => null]]);
    $downloader = Mockery::mock(CoverImageDownloader::class);
    $downloader->expects('download')->twice()->andReturn(['body' => 'image', 'mime_type' => 'image/jpeg']);
    $processor = Mockery::mock(CoverImageProcessor::class);
    $processor->expects('process')->twice()->andReturnUsing(fn (Book $book): array => ['small_path' => "covers/{$book->id}-small.webp", 'large_path' => "covers/{$book->id}-large.webp", 'width' => 720, 'height' => 1024, 'mime_type' => 'image/webp']);
    $images = new BookCoverSynchronizer($downloader, $processor);
    // Deliberately include stale/null preloads supplied by a caller.
    $counts = (new BookCoverSyncRunner($api, $images))->run(Book::query()->with('cover'));
    expect($counts['ready'])->toBe(2)
        ->and($counts['keywords_updated'])->toBe(2)
        ->and($newBook->cover->keywords()->pluck('keyword')->all())->toBe(['Neues Motiv'])
        ->and($existingCover->keywords()->pluck('keyword')->all())->toBe(['Anderes Motiv'])
        ->and($existingCover->fresh()->keywords_source_file_title)->toBe('Datei:Replacement.jpg');
});

test('disabled keyword sync does not load covers or fetch keywords', function (): void {
    config(['bildersuche.sync_enabled' => false]);
    $this->searchCover(1, ['Aruula']);
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->expects('coverImages')->andReturn([]);
    $api->shouldNotReceive('coverKeywords');
    $images = Mockery::mock(BookCoverSynchronizer::class);
    $images->expects('sync')->andReturn('unchanged');
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $counts = (new BookCoverSyncRunner($api, $images))->run(Book::query());
        expect($counts)->not->toHaveKey('keywords_updated')
            ->and(collect(DB::getQueryLog())->filter(fn (array $entry) => str_contains($entry['query'], 'book_covers')))->toBeEmpty();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
});

test('runner dry run checks new sources without creating rows', function (): void {
    config(['bildersuche.sync_enabled' => true]);
    Book::factory()->mapped(101)->create();
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->expects('coverImages')->andReturn([101 => ['exists' => true, 'file_title' => 'Datei:New.jpg']]);
    $api->expects('coverKeywords')->with(['Datei:New.jpg'], Mockery::type(CoverSyncDeadline::class))->andReturn(['Datei:New.jpg' => ['keywords' => ['Drax'], 'error' => null]]);
    $images = Mockery::mock(BookCoverSynchronizer::class);
    $images->expects('sync')->andReturn('ready');
    $counts = (new BookCoverSyncRunner($api, $images))->run(Book::query(), dryRun: true);
    expect($counts['keywords_updated'])->toBe(1);
    $this->assertDatabaseCount('book_covers', 0);
    $this->assertDatabaseCount('book_cover_keywords', 0);
});

test('dry run keeps the rated cover source when a change is refused', function (): void {
    config(['bildersuche.sync_enabled' => true]);
    $cover = $this->searchCover(1, ['Aruula']);
    $cover->book->update(['maddraxikon_page_id' => 101]);
    CoverRating::factory()->for($cover, 'bookCover')->create()->delete();
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->expects('coverImages')->andReturn([101 => ['exists' => true, 'file_title' => 'Datei:New.jpg']]);
    $api->expects('coverKeywords')->with([$cover->source_file_title], Mockery::type(CoverSyncDeadline::class))->andReturn([$cover->source_file_title => ['keywords' => ['Aruula'], 'error' => null]]);
    $images = Mockery::mock(BookCoverSynchronizer::class);
    $images->expects('sync')->andReturn('changed');
    $counts = (new BookCoverSyncRunner($api, $images))->run(Book::query(), dryRun: true);
    expect($counts['changed'])->toBe(1)
        ->and($counts['keywords_unchanged'])->toBe(1)
        ->and($cover->fresh()->source_file_title)->toBe($cover->source_file_title);
});

test('overlapping manual and queue runs are rejected', function (): void {
    $lock = Cache::lock('cover-ratings:sync-run', 7200);
    $lock->get();
    try {
        expect(fn () => app(BookCoverSyncRunner::class)->run(Book::query()))->toThrow(RuntimeException::class);
    } finally {
        $lock->release();
    }
});

test('command fails when only keywords failed', function (): void {
    config(['cover-ratings.sync_enabled' => true]);
    $this->mock(BookCoverSyncRunner::class)->expects('run')->andReturn(['failed' => 0, 'keywords_failed' => 1]);
    $this->artisan('cover-ratings:sync-covers')->assertFailed();
});

test('queue reports keyword failures as failed jobs', function (): void {
    config(['cover-ratings.sync_enabled' => true]);
    $runner = Mockery::mock(BookCoverSyncRunner::class);
    $runner->expects('run')->andReturn(['failed' => 0, 'keywords_failed' => 1]);
    expect(fn () => (new SyncCoverRatingCovers)->handle($runner))->toThrow(RuntimeException::class);
});

test('runner releases shared lock after an exception', function (): void {
    $this->searchCover(1, ['Aruula']);
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->expects('coverImages')->andReturn([]);
    $images = Mockery::mock(BookCoverSynchronizer::class);
    $images->expects('sync')->andThrow(new RuntimeException('interrupted'));
    try {
        (new BookCoverSyncRunner($api, $images))->run(Book::query());
        $this->fail('Expected interruption');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('interrupted');
        $lock = Cache::lock('cover-ratings:sync-run', 7200);
        expect($lock->get())->toBeTrue();
        $lock->release();
    }
});
