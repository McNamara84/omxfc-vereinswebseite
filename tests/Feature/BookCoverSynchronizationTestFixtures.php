<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookType;
use App\Models\Book;
use App\Services\CoverRatings\BookCoverSynchronizer;
use App\Services\CoverRatings\CoverImageDownloader;
use App\Services\CoverRatings\CoverImageProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

trait BookCoverSynchronizationTestFixtures
{
    use RefreshDatabase;

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
        return Book::factory()->mapped(10000 + $number)->create(['type' => BookType::MaddraxDieDunkleZukunftDerErde, 'roman_number' => $number]);
    }

    /** @return array<string, mixed> */
    private function source(int $number, string $sha1): array
    {
        return ['exists' => true, 'page_id' => 10000 + $number, 'file_title' => "Datei:Cover-{$number}.jpg", 'url' => "https://wiki.example.test/images/cover-{$number}.jpg", 'description_url' => "https://wiki.example.test/wiki/Datei:Cover-{$number}.jpg", 'sha1' => $sha1, 'mime_type' => 'image/jpeg', 'width' => 720, 'height' => 1024, 'artist' => 'Test Artist', 'credit' => 'Test Credit', 'license' => 'CC BY-SA', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/'];
    }
}
