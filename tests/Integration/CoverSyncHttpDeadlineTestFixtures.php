<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\CoverRatings\CoverImageDownloader;
use App\Services\CoverRatings\CoverSyncDeadline;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

trait CoverSyncHttpDeadlineTestFixtures
{
    private function request(string $operation, CoverSyncDeadline $deadline): void
    {
        match ($operation) {
            'keywords' => app(MaddraxikonApiClient::class)->coverKeywords(['Datei:A.jpg'], $deadline),
            'images' => app(MaddraxikonApiClient::class)->coverImages([101], $deadline),
            'download' => app(CoverImageDownloader::class)->download('https://wiki.example.test/a.jpg', $deadline),
        };
    }

    private function response(Request $request, bool $continuation = false): mixed
    {
        if (str_ends_with($request->url(), '/a.jpg')) {
            return Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']);
        }
        if ($request['action'] === 'ask') {
            return Http::response(['query' => ['results' => []], ...$continuation ? ['query-continue-offset' => 1] : []]);
        }
        if ($request['prop'] === 'pageimages') {
            return Http::response(['query' => ['pages' => [['pageid' => 101, 'pageimage' => 'A.jpg']]]]);
        }

        return Http::response(['query' => ['pages' => []]]);
    }
}
