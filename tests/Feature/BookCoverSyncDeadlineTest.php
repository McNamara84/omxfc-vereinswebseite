<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\CoverSyncTimeoutException;
use App\Models\Book;
use App\Services\CoverRatings\BookCoverSynchronizer;
use App\Services\CoverRatings\BookCoverSyncRunner;
use App\Services\CoverRatings\CoverImageDownloader;
use App\Services\CoverRatings\CoverImageProcessor;
use App\Services\CoverRatings\CoverSyncDeadline;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Concerns\CreatesBildersucheCover;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesBildersucheCover::class, RefreshDatabase::class);

test('expired budget releases the lock and allows another run', function (): void {
    Http::fake();
    $runner = app(BookCoverSyncRunner::class);
    try {
        $runner->run(Book::query(), deadline: new CoverSyncDeadline(0));
        $this->fail('Expected deadline expiration.');
    } catch (CoverSyncTimeoutException $exception) {
        expect($exception->getMessage())->toContain('Zeitlimit des Cover-Abgleichs');
    }
    Http::assertNothingSent();
    expect($runner->run(Book::query())['failed'])->toBe(0);
});

test('timeout in each phase releases the lock and preserves keywords', function (string $phase): void {
    config(['bildersuche.sync_enabled' => true]);
    $cover = $this->searchCover(1, ['Aruula']);
    $cover->book->update(['maddraxikon_page_id' => 101]);
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $images = Mockery::mock(BookCoverSynchronizer::class);
    $deadline = new CoverSyncDeadline(270);
    $sources = $api->expects('coverImages')->with([101], $deadline);
    if ($phase === 'sources') {
        $sources->andThrow(new CoverSyncTimeoutException);
        $images->shouldNotReceive('sync');
    } else {
        $sources->andReturn([]);
        $image = $images->expects('sync')->with(Mockery::type(Book::class), null, false, false, $deadline);
        if ($phase === 'images') {
            $image->andThrow(new CoverSyncTimeoutException);
        } else {
            $image->andReturn('unchanged');
            $api->expects('coverKeywords')->with([$cover->source_file_title], $deadline)->andThrow(new CoverSyncTimeoutException);
        }
    }
    if ($phase !== 'keywords') {
        $api->shouldNotReceive('coverKeywords');
    }
    try {
        (new BookCoverSyncRunner($api, $images))->run(Book::query(), deadline: $deadline);
        $this->fail('Expected deadline expiration.');
    } catch (CoverSyncTimeoutException) {
        $lock = Cache::lock('cover-ratings:sync-run', 7200);
        expect($lock->get())->toBeTrue();
        $lock->release();
    }
    expect($cover->fresh()->keywords->pluck('keyword')->all())->toBe(['Aruula']);
})->with([['sources'], ['images'], ['keywords']]);

test('deadline after the last image is not reported as success', function (): void {
    config(['bildersuche.sync_enabled' => false]);
    $this->searchCover(1, []);
    $expired = false;
    $deadline = Mockery::mock(CoverSyncDeadline::class)->makePartial();
    $deadline->shouldReceive('remainingSeconds')->andReturnUsing(function () use (&$expired): float {
        return $expired ? 0 : 270;
    });
    $api = Mockery::mock(MaddraxikonApiClient::class);
    $api->expects('coverImages')->andReturn([]);
    $images = Mockery::mock(BookCoverSynchronizer::class);
    $images->expects('sync')->andReturnUsing(function () use (&$expired): string {
        $expired = true;

        return 'unchanged';
    });
    expect(fn () => (new BookCoverSyncRunner($api, $images))->run(Book::query(), deadline: $deadline))->toThrow(CoverSyncTimeoutException::class);
});

test('download timeout preserves cover state and does not process images', function (): void {
    Storage::fake('private');
    $cover = $this->searchCover(1, ['Aruula']);
    $before = $cover->fresh()->getAttributes();
    $deadline = new CoverSyncDeadline(270);
    $downloader = Mockery::mock(CoverImageDownloader::class);
    $downloader->expects('download')->with('https://wiki.example.test/new.jpg', $deadline)->andThrow(new CoverSyncTimeoutException);
    $processor = Mockery::mock(CoverImageProcessor::class);
    $processor->shouldNotReceive('process');
    try {
        (new BookCoverSynchronizer($downloader, $processor))->sync($cover->book, ['exists' => true, 'file_title' => 'Datei:New.jpg', 'url' => 'https://wiki.example.test/new.jpg'], deadline: $deadline);
        $this->fail('Expected deadline expiration.');
    } catch (CoverSyncTimeoutException) {
        expect($cover->fresh()->getAttributes())->toBe($before);
    }
});
