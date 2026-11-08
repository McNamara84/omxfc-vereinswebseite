<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookCoverStatus;
use App\Exceptions\CoverImageException;
use App\Models\Book;
use App\Models\BookCover;
use App\Models\CoverRating;
use App\Models\User;
use App\Services\CoverRatings\BookCoverSynchronizer;
use App\Services\CoverRatings\BookCoverSyncRunner;
use App\Services\CoverRatings\CoverImageDownloader;
use App\Services\CoverRatings\CoverImageProcessor;
use App\Services\CoverRatings\CoverSyncDeadline;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(BookCoverSynchronizationTestFixtures::class);

beforeEach(function (): void {
    Storage::fake('private');
    config(['cover-ratings.images.disk' => 'private', 'cover-ratings.sync_enabled' => true]);
});

test('missing source is recorded without replacing an existing ready cover', function (): void {
    $book = $this->mappedBook(1);
    $synchronizer = $this->synchronizer();
    expect($synchronizer->sync($book, null))->toBe('missing');
    $this->assertDatabaseHas('book_covers', ['book_id' => $book->id, 'status' => BookCoverStatus::Missing->value]);
    $cover = BookCover::factory()->for($this->mappedBook(2))->create();
    expect($synchronizer->sync($cover->book, null))->toBe('missing')
        ->and($cover->fresh()->status)->toBe(BookCoverStatus::Ready);
});

test('unchanged fingerprint and existing files skip the download', function (): void {
    $cover = BookCover::factory()->for($this->mappedBook(3))->create(['source_sha1' => str_repeat('a', 40)]);
    Storage::disk('private')->put($cover->small_path, 'small');
    Storage::disk('private')->put($cover->large_path, 'large');
    $downloader = Mockery::mock(CoverImageDownloader::class);
    $downloader->shouldNotReceive('download');
    $processor = Mockery::mock(CoverImageProcessor::class);
    $processor->shouldNotReceive('process');
    $result = (new BookCoverSynchronizer($downloader, $processor))->sync($cover->book, $this->source(3, str_repeat('a', 40)));
    expect($result)->toBe('unchanged')
        ->and($cover->fresh()->last_error)->toBeNull();
    Storage::disk('private')->assertExists($cover->small_path);
    Storage::disk('private')->assertExists($cover->large_path);
});

test('changed file with historical ratings requires force', function (): void {
    $cover = BookCover::factory()->for($this->mappedBook(4))->create(['source_file_title' => 'Datei:Alt.jpg']);
    CoverRating::factory()->for(User::factory())->for($cover, 'bookCover')->create()->delete();
    $downloader = Mockery::mock(CoverImageDownloader::class);
    $downloader->shouldNotReceive('download');
    $processor = Mockery::mock(CoverImageProcessor::class);
    $processor->shouldNotReceive('process');
    $result = (new BookCoverSynchronizer($downloader, $processor))->sync($cover->book, $this->source(4, str_repeat('b', 40)));
    expect($result)->toBe('changed')
        ->and($cover->fresh()->source_file_title)->toBe('Datei:Alt.jpg')
        ->and($cover->fresh()->last_error)->not->toBeNull();
});

test('forced sync publishes new files before deleting obsolete variants', function (): void {
    $cover = BookCover::factory()->for($this->mappedBook(5))->create(['small_path' => 'cover-ratings/old-small.webp', 'large_path' => 'cover-ratings/old-large.webp']);
    Storage::disk('private')->put($cover->small_path, 'old-small');
    Storage::disk('private')->put($cover->large_path, 'old-large');
    $downloader = Mockery::mock(CoverImageDownloader::class);
    $downloader->expects('download')->once()->andReturn(['body' => 'downloaded-image', 'mime_type' => 'image/jpeg']);
    $processor = Mockery::mock(CoverImageProcessor::class);
    $processor->expects('process')->once()->andReturnUsing(function (Book $book): array {
        $small = "cover-ratings/{$book->id}/new-small.webp";
        $large = "cover-ratings/{$book->id}/new-large.webp";
        Storage::disk('private')->put($small, 'new-small');
        Storage::disk('private')->put($large, 'new-large');

        return ['small_path' => $small, 'large_path' => $large, 'width' => 720, 'height' => 1024, 'mime_type' => 'image/webp'];
    });
    $result = (new BookCoverSynchronizer($downloader, $processor))->sync($cover->book, $this->source(5, str_repeat('c', 40)), force: true);
    $cover->refresh();
    expect($result)->toBe('ready')
        ->and($cover->status)->toBe(BookCoverStatus::Ready);
    Storage::disk('private')->assertMissing('cover-ratings/old-small.webp');
    Storage::disk('private')->assertMissing('cover-ratings/old-large.webp');
    Storage::disk('private')->assertExists($cover->small_path);
    Storage::disk('private')->assertExists($cover->large_path);
});

test('temporary download failure preserves a working cover and marks a new one failed', function (): void {
    $ready = BookCover::factory()->for($this->mappedBook(6))->create();
    $downloader = Mockery::mock(CoverImageDownloader::class);
    $downloader->expects('download')->twice()->andThrow(new CoverImageException('timeout'));
    $processor = Mockery::mock(CoverImageProcessor::class);
    $processor->shouldNotReceive('process');
    $synchronizer = new BookCoverSynchronizer($downloader, $processor);
    expect($synchronizer->sync($ready->book, $this->source(6, str_repeat('d', 40)), force: true))->toBe('failed')
        ->and($ready->fresh()->status)->toBe(BookCoverStatus::Ready)
        ->and($ready->fresh()->last_error)->toBe('timeout');
    $newBook = $this->mappedBook(7);
    expect($synchronizer->sync($newBook, $this->source(7, str_repeat('e', 40))))->toBe('failed')
        ->and($newBook->cover->status)->toBe(BookCoverStatus::Failed);
});

test('database failure removes newly published variants', function (): void {
    $book = $this->mappedBook(70);
    $downloader = Mockery::mock(CoverImageDownloader::class);
    $downloader->expects('download')->once()->andReturn(['body' => 'downloaded-image', 'mime_type' => 'image/jpeg']);
    $processor = Mockery::mock(CoverImageProcessor::class);
    $processor->expects('process')->once()->andReturnUsing(function (Book $processedBook): array {
        $small = "cover-ratings/{$processedBook->id}/new-small.webp";
        $large = "cover-ratings/{$processedBook->id}/new-large.webp";
        Storage::disk('private')->put($small, 'new-small');
        Storage::disk('private')->put($large, 'new-large');

        return ['small_path' => $small, 'large_path' => $large, 'width' => 720, 'height' => 1024, 'mime_type' => 'image/webp'];
    });
    $savingAttempts = 0;
    BookCover::saving(function () use (&$savingAttempts): void {
        $savingAttempts++;
        if ($savingAttempts === 1) {
            throw new RuntimeException('simulated database failure');
        }
    });
    $result = (new BookCoverSynchronizer($downloader, $processor))->sync($book, $this->source(70, str_repeat('7', 40)));
    expect($result)->toBe('failed')
        ->and($savingAttempts)->toBe(2)
        ->and(Storage::disk('private')->allFiles())->toBe([])
        ->and($book->cover->status)->toBe(BookCoverStatus::Failed);
});

test('dry run does not write database or storage', function (): void {
    $book = $this->mappedBook(8);
    $synchronizer = $this->synchronizer();
    expect($synchronizer->sync($book, $this->source(8, str_repeat('f', 40)), dryRun: true))->toBe('ready');
    $this->assertDatabaseMissing('book_covers', ['book_id' => $book->id]);
    expect(Storage::disk('private')->allFiles())->toBe([]);
});

test('runner batches api requests and reports each result', function (): void {
    config(['cover-ratings.sync.batch_size' => 2]);
    $first = $this->mappedBook(20);
    $second = $this->mappedBook(21);
    $third = $this->mappedBook(22);
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->expects('coverImages')->with([$first->maddraxikon_page_id, $second->maddraxikon_page_id], Mockery::type(CoverSyncDeadline::class))->once()->andReturn([$first->maddraxikon_page_id => $this->source(20, str_repeat('a', 40)), $second->maddraxikon_page_id => $this->source(21, str_repeat('b', 40))]);
    $api->expects('coverImages')->with([$third->maddraxikon_page_id], Mockery::type(CoverSyncDeadline::class))->once()->andReturn([$third->maddraxikon_page_id => $this->source(22, str_repeat('c', 40))]);
    $synchronizer = Mockery::mock(BookCoverSynchronizer::class);
    $synchronizer->expects('sync')->withArgs(fn (Book $book, array $source, bool $force, bool $dryRun): bool => $book->is($first) && $source['page_id'] === $first->maddraxikon_page_id && $force && $dryRun)->once()->andReturn('ready');
    $synchronizer->expects('sync')->withArgs(fn (Book $book): bool => $book->is($second))->once()->andReturn('missing');
    $synchronizer->expects('sync')->withArgs(fn (Book $book): bool => $book->is($third))->once()->andReturn('unchanged');
    $reported = [];
    $counts = (new BookCoverSyncRunner($api, $synchronizer))->run(Book::query(), force: true, dryRun: true, report: function (Book $book, string $result) use (&$reported): void {
        $reported[$book->id] = $result;
    });
    expect($counts)->toBe(['ready' => 1, 'unchanged' => 1, 'missing' => 1, 'changed' => 0, 'failed' => 0])
        ->and($reported)->toBe([$first->id => 'ready', $second->id => 'missing', $third->id => 'unchanged']);
});

test('sync command fails closed when disabled and validates filters', function (): void {
    config(['cover-ratings.sync_enabled' => false]);
    $this->artisan('cover-ratings:sync-covers')->expectsOutputToContain('deaktiviert')->assertFailed();
    $this->artisan('cover-ratings:sync-covers', ['--dry-run' => true, '--book' => 'zero'])->expectsOutputToContain('positive')->assertExitCode(2);
    $this->artisan('cover-ratings:sync-covers', ['--dry-run' => true, '--series' => 'unknown'])->expectsOutputToContain('Unbekannter Serien-Key')->assertExitCode(2);
});
