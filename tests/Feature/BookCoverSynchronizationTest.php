<?php

namespace Tests\Feature;

use App\Enums\BookCoverStatus;
use App\Enums\BookType;
use App\Exceptions\CoverImageException;
use App\Models\Book;
use App\Models\BookCover;
use App\Models\CoverRating;
use App\Models\User;
use App\Services\CoverRatings\BookCoverSynchronizer;
use App\Services\CoverRatings\BookCoverSyncRunner;
use App\Services\CoverRatings\CoverImageDownloader;
use App\Services\CoverRatings\CoverImageProcessor;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class BookCoverSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        config([
            'cover-ratings.images.disk' => 'private',
            'cover-ratings.sync_enabled' => true,
        ]);
    }

    public function test_missing_source_is_recorded_without_replacing_an_existing_ready_cover(): void
    {
        $book = $this->mappedBook(1);
        $synchronizer = $this->synchronizer();

        $this->assertSame('missing', $synchronizer->sync($book, null));
        $this->assertDatabaseHas('book_covers', [
            'book_id' => $book->id,
            'status' => BookCoverStatus::Missing->value,
        ]);

        $cover = BookCover::factory()->for($this->mappedBook(2))->create();
        $this->assertSame('missing', $synchronizer->sync($cover->book, null));
        $this->assertSame(BookCoverStatus::Ready, $cover->fresh()->status);
    }

    public function test_unchanged_fingerprint_and_existing_files_skip_the_download(): void
    {
        $cover = BookCover::factory()->for($this->mappedBook(3))->create([
            'source_sha1' => str_repeat('a', 40),
        ]);
        Storage::disk('private')->put($cover->small_path, 'small');
        Storage::disk('private')->put($cover->large_path, 'large');
        $downloader = Mockery::mock(CoverImageDownloader::class);
        $downloader->shouldNotReceive('download');
        $processor = Mockery::mock(CoverImageProcessor::class);
        $processor->shouldNotReceive('process');

        $result = (new BookCoverSynchronizer($downloader, $processor))
            ->sync($cover->book, $this->source(3, str_repeat('a', 40)));

        $this->assertSame('unchanged', $result);
        $this->assertNull($cover->fresh()->last_error);
        Storage::disk('private')->assertExists($cover->small_path);
        Storage::disk('private')->assertExists($cover->large_path);
    }

    public function test_changed_file_with_historical_ratings_requires_force(): void
    {
        $cover = BookCover::factory()->for($this->mappedBook(4))->create([
            'source_file_title' => 'Datei:Alt.jpg',
        ]);
        CoverRating::factory()
            ->for(User::factory())
            ->for($cover, 'bookCover')
            ->create()
            ->delete();
        $downloader = Mockery::mock(CoverImageDownloader::class);
        $downloader->shouldNotReceive('download');
        $processor = Mockery::mock(CoverImageProcessor::class);
        $processor->shouldNotReceive('process');

        $result = (new BookCoverSynchronizer($downloader, $processor))
            ->sync($cover->book, $this->source(4, str_repeat('b', 40)));

        $this->assertSame('changed', $result);
        $this->assertSame('Datei:Alt.jpg', $cover->fresh()->source_file_title);
        $this->assertNotNull($cover->fresh()->last_error);
    }

    public function test_forced_sync_publishes_new_files_before_deleting_obsolete_variants(): void
    {
        $cover = BookCover::factory()->for($this->mappedBook(5))->create([
            'small_path' => 'cover-ratings/old-small.webp',
            'large_path' => 'cover-ratings/old-large.webp',
        ]);
        Storage::disk('private')->put($cover->small_path, 'old-small');
        Storage::disk('private')->put($cover->large_path, 'old-large');
        $downloader = Mockery::mock(CoverImageDownloader::class);
        $downloader->expects('download')->once()->andReturn([
            'body' => 'downloaded-image',
            'mime_type' => 'image/jpeg',
        ]);
        $processor = Mockery::mock(CoverImageProcessor::class);
        $processor->expects('process')->once()->andReturnUsing(function (Book $book): array {
            $small = "cover-ratings/{$book->id}/new-small.webp";
            $large = "cover-ratings/{$book->id}/new-large.webp";
            Storage::disk('private')->put($small, 'new-small');
            Storage::disk('private')->put($large, 'new-large');

            return [
                'small_path' => $small,
                'large_path' => $large,
                'width' => 720,
                'height' => 1024,
                'mime_type' => 'image/webp',
            ];
        });

        $result = (new BookCoverSynchronizer($downloader, $processor))
            ->sync($cover->book, $this->source(5, str_repeat('c', 40)), force: true);

        $cover->refresh();
        $this->assertSame('ready', $result);
        $this->assertSame(BookCoverStatus::Ready, $cover->status);
        Storage::disk('private')->assertMissing('cover-ratings/old-small.webp');
        Storage::disk('private')->assertMissing('cover-ratings/old-large.webp');
        Storage::disk('private')->assertExists($cover->small_path);
        Storage::disk('private')->assertExists($cover->large_path);
    }

    public function test_temporary_download_failure_preserves_a_working_cover_and_marks_a_new_one_failed(): void
    {
        $ready = BookCover::factory()->for($this->mappedBook(6))->create();
        $downloader = Mockery::mock(CoverImageDownloader::class);
        $downloader->expects('download')->twice()->andThrow(new CoverImageException('timeout'));
        $processor = Mockery::mock(CoverImageProcessor::class);
        $processor->shouldNotReceive('process');
        $synchronizer = new BookCoverSynchronizer($downloader, $processor);

        $this->assertSame(
            'failed',
            $synchronizer->sync($ready->book, $this->source(6, str_repeat('d', 40)), force: true),
        );
        $this->assertSame(BookCoverStatus::Ready, $ready->fresh()->status);
        $this->assertSame('timeout', $ready->fresh()->last_error);

        $newBook = $this->mappedBook(7);
        $this->assertSame(
            'failed',
            $synchronizer->sync($newBook, $this->source(7, str_repeat('e', 40))),
        );
        $this->assertSame(BookCoverStatus::Failed, $newBook->cover->status);
    }

    public function test_database_failure_removes_newly_published_variants(): void
    {
        $book = $this->mappedBook(70);
        $downloader = Mockery::mock(CoverImageDownloader::class);
        $downloader->expects('download')->once()->andReturn([
            'body' => 'downloaded-image',
            'mime_type' => 'image/jpeg',
        ]);
        $processor = Mockery::mock(CoverImageProcessor::class);
        $processor->expects('process')->once()->andReturnUsing(function (Book $processedBook): array {
            $small = "cover-ratings/{$processedBook->id}/new-small.webp";
            $large = "cover-ratings/{$processedBook->id}/new-large.webp";
            Storage::disk('private')->put($small, 'new-small');
            Storage::disk('private')->put($large, 'new-large');

            return [
                'small_path' => $small,
                'large_path' => $large,
                'width' => 720,
                'height' => 1024,
                'mime_type' => 'image/webp',
            ];
        });
        $savingAttempts = 0;
        BookCover::saving(function () use (&$savingAttempts): void {
            $savingAttempts++;

            if ($savingAttempts === 1) {
                throw new RuntimeException('simulated database failure');
            }
        });

        $result = (new BookCoverSynchronizer($downloader, $processor))
            ->sync($book, $this->source(70, str_repeat('7', 40)));

        $this->assertSame('failed', $result);
        $this->assertSame(2, $savingAttempts);
        $this->assertSame([], Storage::disk('private')->allFiles());
        $this->assertSame(BookCoverStatus::Failed, $book->cover->status);
    }

    public function test_dry_run_does_not_write_database_or_storage(): void
    {
        $book = $this->mappedBook(8);
        $synchronizer = $this->synchronizer();

        $this->assertSame('ready', $synchronizer->sync(
            $book,
            $this->source(8, str_repeat('f', 40)),
            dryRun: true,
        ));
        $this->assertDatabaseMissing('book_covers', ['book_id' => $book->id]);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_runner_batches_api_requests_and_reports_each_result(): void
    {
        config(['cover-ratings.sync.batch_size' => 2]);
        $first = $this->mappedBook(20);
        $second = $this->mappedBook(21);
        $third = $this->mappedBook(22);
        $api = Mockery::mock(MaddraxikonApiClient::class);
        $api->expects('coverImages')
            ->with([$first->maddraxikon_page_id, $second->maddraxikon_page_id])
            ->once()
            ->andReturn([
                $first->maddraxikon_page_id => $this->source(20, str_repeat('a', 40)),
                $second->maddraxikon_page_id => $this->source(21, str_repeat('b', 40)),
            ]);
        $api->expects('coverImages')
            ->with([$third->maddraxikon_page_id])
            ->once()
            ->andReturn([
                $third->maddraxikon_page_id => $this->source(22, str_repeat('c', 40)),
            ]);
        $synchronizer = Mockery::mock(BookCoverSynchronizer::class);
        $synchronizer->expects('sync')->withArgs(
            fn (Book $book, array $source, bool $force, bool $dryRun): bool => $book->is($first)
                && $source['page_id'] === $first->maddraxikon_page_id
                && $force
                && $dryRun,
        )->once()->andReturn('ready');
        $synchronizer->expects('sync')->withArgs(
            fn (Book $book): bool => $book->is($second),
        )->once()->andReturn('missing');
        $synchronizer->expects('sync')->withArgs(
            fn (Book $book): bool => $book->is($third),
        )->once()->andReturn('unchanged');
        $reported = [];

        $counts = (new BookCoverSyncRunner($api, $synchronizer))->run(
            Book::query(),
            force: true,
            dryRun: true,
            report: function (Book $book, string $result) use (&$reported): void {
                $reported[$book->id] = $result;
            },
        );

        $this->assertSame([
            'ready' => 1,
            'unchanged' => 1,
            'missing' => 1,
            'changed' => 0,
            'failed' => 0,
        ], $counts);
        $this->assertSame([
            $first->id => 'ready',
            $second->id => 'missing',
            $third->id => 'unchanged',
        ], $reported);
    }

    public function test_sync_command_fails_closed_when_disabled_and_validates_filters(): void
    {
        config(['cover-ratings.sync_enabled' => false]);
        $this->artisan('cover-ratings:sync-covers')
            ->expectsOutputToContain('deaktiviert')
            ->assertFailed();

        $this->artisan('cover-ratings:sync-covers', ['--dry-run' => true, '--book' => 'zero'])
            ->expectsOutputToContain('positive')
            ->assertExitCode(2);

        $this->artisan('cover-ratings:sync-covers', ['--dry-run' => true, '--series' => 'unknown'])
            ->expectsOutputToContain('Unbekannter Serien-Key')
            ->assertExitCode(2);
    }

    private function synchronizer(): BookCoverSynchronizer
    {
        $downloader = Mockery::mock(CoverImageDownloader::class);
        $downloader->shouldNotReceive('download');
        $processor = Mockery::mock(CoverImageProcessor::class);
        $processor->shouldNotReceive('process');

        return new BookCoverSynchronizer($downloader, $processor);
    }

    private function mappedBook(int $number): Book
    {
        return Book::factory()->mapped(10_000 + $number)->create([
            'type' => BookType::MaddraxDieDunkleZukunftDerErde,
            'roman_number' => $number,
        ]);
    }

    /** @return array<string, mixed> */
    private function source(int $number, string $sha1): array
    {
        return [
            'exists' => true,
            'page_id' => 10_000 + $number,
            'file_title' => "Datei:Cover-{$number}.jpg",
            'url' => "https://wiki.example.test/images/cover-{$number}.jpg",
            'description_url' => "https://wiki.example.test/wiki/Datei:Cover-{$number}.jpg",
            'sha1' => $sha1,
            'mime_type' => 'image/jpeg',
            'width' => 720,
            'height' => 1024,
            'artist' => 'Test Artist',
            'credit' => 'Test Credit',
            'license' => 'CC BY-SA',
            'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/',
        ];
    }
}
