<?php

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
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\CreatesBildersucheCover;
use Tests\TestCase;

class BookCoverSyncDeadlineTest extends TestCase
{
    use CreatesBildersucheCover;
    use RefreshDatabase;

    public function test_expired_budget_releases_the_lock_and_allows_another_run(): void
    {
        Http::fake();
        $runner = app(BookCoverSyncRunner::class);
        try {
            $runner->run(Book::query(), deadline: new CoverSyncDeadline(0));
            $this->fail('Expected deadline expiration.');
        } catch (CoverSyncTimeoutException $exception) {
            $this->assertStringContainsString('Zeitlimit des Cover-Abgleichs', $exception->getMessage());
        }

        Http::assertNothingSent();
        $this->assertSame(0, $runner->run(Book::query())['failed']);
    }

    #[TestWith(['sources'])]
    #[TestWith(['images'])]
    #[TestWith(['keywords'])]
    public function test_timeout_in_each_phase_releases_the_lock_and_preserves_keywords(string $phase): void
    {
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
                $api->expects('coverKeywords')->with([$cover->source_file_title], $deadline)
                    ->andThrow(new CoverSyncTimeoutException);
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
            $this->assertTrue($lock->get());
            $lock->release();
        }
        $this->assertSame(['Aruula'], $cover->fresh()->keywords->pluck('keyword')->all());
    }

    public function test_deadline_after_the_last_image_is_not_reported_as_success(): void
    {
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

        $this->expectException(CoverSyncTimeoutException::class);
        (new BookCoverSyncRunner($api, $images))->run(Book::query(), deadline: $deadline);
    }

    public function test_download_timeout_preserves_cover_state_and_does_not_process_images(): void
    {
        Storage::fake('private');
        $cover = $this->searchCover(1, ['Aruula']);
        $before = $cover->fresh()->getAttributes();
        $deadline = new CoverSyncDeadline(270);
        $downloader = Mockery::mock(CoverImageDownloader::class);
        $downloader->expects('download')->with('https://wiki.example.test/new.jpg', $deadline)
            ->andThrow(new CoverSyncTimeoutException);
        $processor = Mockery::mock(CoverImageProcessor::class);
        $processor->shouldNotReceive('process');

        try {
            (new BookCoverSynchronizer($downloader, $processor))->sync($cover->book, [
                'exists' => true, 'file_title' => 'Datei:New.jpg', 'url' => 'https://wiki.example.test/new.jpg',
            ], deadline: $deadline);
            $this->fail('Expected deadline expiration.');
        } catch (CoverSyncTimeoutException) {
            $this->assertSame($before, $cover->fresh()->getAttributes());
        }
    }
}
