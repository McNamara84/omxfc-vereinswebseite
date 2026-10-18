<?php

namespace Tests\Feature;

use App\Jobs\SyncCoverRatingCovers;
use App\Models\Book;
use App\Models\BookCover;
use App\Models\BookCoverKeyword;
use App\Models\CoverRating;
use App\Services\BildersucheService;
use App\Services\CoverRatings\BookCoverKeywordSynchronizer;
use App\Services\CoverRatings\BookCoverSynchronizer;
use App\Services\CoverRatings\BookCoverSyncRunner;
use App\Services\CoverRatings\CoverImageDownloader;
use App\Services\CoverRatings\CoverImageProcessor;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\TestWith;
use ReflectionMethod;
use RuntimeException;
use Tests\Concerns\CreatesBildersucheCover;
use Tests\TestCase;

class BookCoverKeywordSyncTest extends TestCase
{
    use CreatesBildersucheCover;
    use RefreshDatabase;

    private function sync(BookCover $cover, ?array $keywords, bool $dryRun = false): string
    {
        return app(BookCoverKeywordSynchronizer::class)->sync($cover, $cover->source_file_title,
            ['keywords' => $keywords, 'error' => $keywords === null ? 'Timeout' : null], $dryRun);
    }

    public function test_updates_removals_deduplicates_and_recognizes_unchanged_keywords(): void
    {
        $cover = $this->searchCover(1, ['Aruula', 'Schwert']);
        $this->assertSame('keywords_updated', $this->sync($cover, ['Aruula', 'aruula', 'Drax']));
        $this->assertSame(['Aruula', 'Drax'], $cover->keywords()->pluck('keyword')->all());
        $this->assertSame('keywords_unchanged', $this->sync($cover->fresh(), ['Aruula', 'Drax']));
        $this->assertSame('keywords_empty', $this->sync($cover, []));
        $this->assertSame(0, $cover->keywords()->count());
        $this->assertNotNull($cover->fresh()->keywords_synced_at);
    }

    public function test_failure_preserves_keywords_and_last_successful_time(): void
    {
        $cover = $this->searchCover(1, ['Aruula']);
        $cover->update(['keywords_synced_at' => now()->subDay()]);
        $previous = $cover->keywords_synced_at;
        $this->assertSame('keywords_failed', $this->sync($cover, null));
        $this->assertSame(['Aruula'], $cover->keywords()->pluck('keyword')->all());
        $this->assertTrue($previous->equalTo($cover->fresh()->keywords_synced_at));
        $this->assertSame('Timeout', $cover->fresh()->keywords_sync_error);
        $this->assertNull($cover->fresh()->last_error);
        $this->assertSame('keywords_failed', $this->sync($cover, ['Drax', null]));
        $this->assertSame(['Aruula'], $cover->keywords()->pluck('keyword')->all());
        $this->sync($cover, ['Aruula']);
        $this->assertNull($cover->fresh()->keywords_sync_error);
    }

    public function test_dry_run_and_source_race_never_overwrite_persisted_keywords(): void
    {
        $cover = $this->searchCover(1, ['Aruula']);
        $original = $cover->fresh()->getAttributes();
        $this->assertSame('keywords_updated', $this->sync($cover, ['Drax'], true));
        $this->assertSame('keywords_failed', $this->sync($cover, null, true));
        $this->assertSame($original, $cover->fresh()->getAttributes());
        $cover->fresh()->update(['source_file_title' => 'Datei:New.jpg']);
        $this->assertSame('keywords_failed', $this->sync($cover, ['Drax']));
        $this->assertSame(['Aruula'], $cover->keywords()->pluck('keyword')->all());
    }

    public function test_database_error_rolls_back_the_whole_keyword_replacement(): void
    {
        $cover = $this->searchCover(1, ['Aruula']);
        BookCoverKeyword::creating(fn () => throw new RuntimeException('database error'));
        try {
            $this->sync($cover, ['Drax']);
            $this->fail('Expected failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('database error', $exception->getMessage());
            $this->assertSame(['Aruula'], $cover->keywords()->pluck('keyword')->all());
        } finally {
            BookCoverKeyword::flushEventListeners();
        }
    }

    public function test_keywords_belong_to_the_cover_and_are_deleted_with_it(): void
    {
        $cover = $this->searchCover(1, ['Aruula']);
        $keyword = $cover->keywords()->firstOrFail();
        $this->assertTrue($keyword->cover->is($cover));
        $cover->delete();
        $this->assertModelMissing($keyword);
    }

    public function test_new_source_stays_unsearchable_until_its_own_keywords_are_synced(): void
    {
        $cover = $this->searchCover(1, ['Aruula']);
        $cover->update(['source_file_title' => 'Datei:New.jpg']);
        $search = app(BildersucheService::class);
        $this->assertSame(0, $search->search('Aruula')->total());
        $this->assertSame('keywords_updated', $this->sync($cover, ['Drax']));
        $this->assertSame(0, $search->search('Aruula')->total());
        $this->assertSame(1, $search->search('Drax')->total());
    }

    public function test_runner_updates_keywords_for_unchanged_images_and_preserves_actual_source_on_change(): void
    {
        config(['bildersuche.sync_enabled' => true]);
        $cover = $this->searchCover(1, ['Aruula']);
        $cover->book->update(['maddraxikon_page_id' => 101]);
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->expects('coverImages')->with([101])->andReturn([101 => ['exists' => true, 'file_title' => 'Datei:Proposed.jpg']]);
        $api->expects('coverKeywords')->with([$cover->source_file_title])->andReturn([
            $cover->source_file_title => ['keywords' => ['Drax'], 'error' => null],
        ]);
        $images = Mockery::mock(BookCoverSynchronizer::class);
        $images->expects('sync')->andReturn('unchanged');
        $counts = (new BookCoverSyncRunner($api, $images))->run(Book::query());
        $this->assertSame(1, $counts['unchanged']);
        $this->assertSame(1, $counts['keywords_updated']);
        $this->assertSame(['Drax'], $cover->keywords()->pluck('keyword')->all());
    }

    public function test_failed_image_lookup_still_updates_existing_keyword_sources(): void
    {
        config(['bildersuche.sync_enabled' => true]);
        $cover = $this->searchCover(1, ['Aruula']);
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->expects('coverImages')->andThrow(new RuntimeException('API error'));
        $api->expects('coverKeywords')->andReturn([$cover->source_file_title => ['keywords' => ['Drax'], 'error' => null]]);
        $images = Mockery::mock(BookCoverSynchronizer::class);
        $images->shouldNotReceive('sync');
        $counts = (new BookCoverSyncRunner($api, $images))->run(Book::query());
        $this->assertSame(1, $counts['failed']);
        $this->assertSame(1, $counts['keywords_updated']);
    }

    #[TestWith([1, false])]
    #[TestWith([6, false])]
    #[TestWith([11, false])]
    #[TestWith([1, true])]
    #[TestWith([6, true])]
    #[TestWith([11, true])]
    public function test_runner_loads_keyword_covers_once_per_batch_instead_of_per_book(int $bookCount, bool $dryRun): void
    {
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
            $this->assertCount($batches + ($dryRun ? 0 : $bookCount), $coverQueries);
            $this->assertCount($batches, $coverQueries->filter(fn (string $sql) => str_contains($sql, ' in (')));
            $this->assertSame($bookCount, $counts['keywords_updated']);
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_runner_reads_new_and_replaced_covers_after_image_synchronization(): void
    {
        config(['bildersuche.sync_enabled' => true]);
        Storage::fake('private');
        $newBook = Book::factory()->mapped(101)->create(['roman_number' => 1]);
        $existingCover = $this->searchCover(2, ['Aruula']);
        $existingCover->book->update(['maddraxikon_page_id' => 102]);
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->expects('coverImages')->with([101, 102])->andReturn([
            101 => ['exists' => true, 'file_title' => 'Datei:New.jpg', 'url' => 'https://example.test/new.jpg'],
            102 => ['exists' => true, 'file_title' => 'Datei:Replacement.jpg', 'url' => 'https://example.test/replacement.jpg'],
        ]);
        $api->expects('coverKeywords')->with(['Datei:New.jpg', 'Datei:Replacement.jpg'])->andReturn([
            'Datei:New.jpg' => ['keywords' => ['Neues Motiv'], 'error' => null],
            'Datei:Replacement.jpg' => ['keywords' => ['Anderes Motiv'], 'error' => null],
        ]);
        $downloader = Mockery::mock(CoverImageDownloader::class);
        $downloader->expects('download')->twice()->andReturn(['body' => 'image', 'mime_type' => 'image/jpeg']);
        $processor = Mockery::mock(CoverImageProcessor::class);
        $processor->expects('process')->twice()->andReturnUsing(fn (Book $book): array => [
            'small_path' => "covers/{$book->id}-small.webp", 'large_path' => "covers/{$book->id}-large.webp",
            'width' => 720, 'height' => 1024, 'mime_type' => 'image/webp',
        ]);
        $images = new BookCoverSynchronizer($downloader, $processor);
        // Deliberately include stale/null preloads supplied by a caller.
        $counts = (new BookCoverSyncRunner($api, $images))->run(Book::query()->with('cover'));

        $this->assertSame(2, $counts['ready']);
        $this->assertSame(2, $counts['keywords_updated']);
        $this->assertSame(['Neues Motiv'], $newBook->cover->keywords()->pluck('keyword')->all());
        $this->assertSame(['Anderes Motiv'], $existingCover->keywords()->pluck('keyword')->all());
        $this->assertSame('Datei:Replacement.jpg', $existingCover->fresh()->keywords_source_file_title);
    }

    public function test_disabled_keyword_sync_does_not_load_covers_or_fetch_keywords(): void
    {
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
            $this->assertArrayNotHasKey('keywords_updated', $counts);
            $this->assertEmpty(collect(DB::getQueryLog())->filter(fn (array $entry) => str_contains($entry['query'], 'book_covers')));
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_runner_dry_run_checks_new_sources_without_creating_rows(): void
    {
        config(['bildersuche.sync_enabled' => true]);
        Book::factory()->mapped(101)->create();
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->expects('coverImages')->andReturn([101 => ['exists' => true, 'file_title' => 'Datei:New.jpg']]);
        $api->expects('coverKeywords')->with(['Datei:New.jpg'])->andReturn(['Datei:New.jpg' => ['keywords' => ['Drax'], 'error' => null]]);
        $images = Mockery::mock(BookCoverSynchronizer::class);
        $images->expects('sync')->andReturn('ready');
        $counts = (new BookCoverSyncRunner($api, $images))->run(Book::query(), dryRun: true);
        $this->assertSame(1, $counts['keywords_updated']);
        $this->assertDatabaseCount('book_covers', 0);
        $this->assertDatabaseCount('book_cover_keywords', 0);
    }

    public function test_dry_run_keeps_the_rated_cover_source_when_a_change_is_refused(): void
    {
        config(['bildersuche.sync_enabled' => true]);
        $cover = $this->searchCover(1, ['Aruula']);
        $cover->book->update(['maddraxikon_page_id' => 101]);
        CoverRating::factory()->for($cover, 'bookCover')->create()->delete();
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->expects('coverImages')->andReturn([101 => ['exists' => true, 'file_title' => 'Datei:New.jpg']]);
        $api->expects('coverKeywords')->with([$cover->source_file_title])->andReturn([
            $cover->source_file_title => ['keywords' => ['Aruula'], 'error' => null],
        ]);
        $images = Mockery::mock(BookCoverSynchronizer::class);
        $images->expects('sync')->andReturn('changed');
        $counts = (new BookCoverSyncRunner($api, $images))->run(Book::query(), dryRun: true);
        $this->assertSame(1, $counts['changed']);
        $this->assertSame(1, $counts['keywords_unchanged']);
        $this->assertSame($cover->source_file_title, $cover->fresh()->source_file_title);
    }

    public function test_expired_deadline_aborts_before_the_two_hour_lock_can_expire(): void
    {
        // Supply an elapsed monotonic timestamp without making the test wait.
        $deadline = new ReflectionMethod(BookCoverSyncRunner::class, 'checkDeadline');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Zeitlimit des Cover-Abgleichs erreicht.');
        $deadline->invoke(app(BookCoverSyncRunner::class), hrtime(true) - 7_199_000_000_000);
    }

    public function test_overlapping_manual_and_queue_runs_are_rejected(): void
    {
        $lock = Cache::lock('cover-ratings:sync-run', 7200);
        $lock->get();
        try {
            $this->expectException(RuntimeException::class);
            app(BookCoverSyncRunner::class)->run(Book::query());
        } finally {
            $lock->release();
        }
    }

    public function test_command_fails_when_only_keywords_failed(): void
    {
        config(['cover-ratings.sync_enabled' => true]);
        $this->mock(BookCoverSyncRunner::class)->expects('run')->andReturn(['failed' => 0, 'keywords_failed' => 1]);
        $this->artisan('cover-ratings:sync-covers')->assertFailed();
    }

    public function test_queue_reports_keyword_failures_as_failed_jobs(): void
    {
        config(['cover-ratings.sync_enabled' => true]);
        $runner = Mockery::mock(BookCoverSyncRunner::class);
        $runner->expects('run')->andReturn(['failed' => 0, 'keywords_failed' => 1]);
        $this->expectException(RuntimeException::class);
        (new SyncCoverRatingCovers)->handle($runner);
    }

    public function test_runner_releases_shared_lock_after_an_exception(): void
    {
        $this->searchCover(1, ['Aruula']);
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->expects('coverImages')->andReturn([]);
        $images = Mockery::mock(BookCoverSynchronizer::class);
        $images->expects('sync')->andThrow(new RuntimeException('interrupted'));
        try {
            (new BookCoverSyncRunner($api, $images))->run(Book::query());
            $this->fail('Expected interruption');
        } catch (RuntimeException $exception) {
            $this->assertSame('interrupted', $exception->getMessage());
            $lock = Cache::lock('cover-ratings:sync-run', 7200);
            $this->assertTrue($lock->get());
            $lock->release();
        }
    }
}
