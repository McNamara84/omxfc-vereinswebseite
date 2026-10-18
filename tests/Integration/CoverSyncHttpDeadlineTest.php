<?php

namespace Tests\Integration;

use App\Exceptions\CoverSyncTimeoutException;
use App\Services\CoverRatings\CoverImageDownloader;
use App\Services\CoverRatings\CoverSyncDeadline;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CoverSyncHttpDeadlineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'maddraxikon.base_url' => 'https://wiki.example.test',
            'maddraxikon.api_url' => 'https://wiki.example.test/api.php',
            'maddraxikon.http.attempts' => 3,
            'maddraxikon.http.connect_timeout' => 300,
            'maddraxikon.http.timeout' => 300,
            'cover-ratings.images.allowed_origins' => ['https://wiki.example.test'],
            'cover-ratings.images.connect_timeout' => 300,
            'cover-ratings.images.timeout' => 300,
        ]);
        Http::preventStrayRequests();
    }

    #[TestWith(['keywords'])]
    #[TestWith(['images'])]
    #[TestWith(['download'])]
    public function test_expired_budget_prevents_any_http_request(string $operation): void
    {
        Http::fake();
        try {
            $this->request($operation, new CoverSyncDeadline(0));
            $this->fail('Expected deadline expiration.');
        } catch (CoverSyncTimeoutException) {
            Http::assertNothingSent();
        }
    }

    #[TestWith(['keywords'])]
    #[TestWith(['images'])]
    #[TestWith(['download'])]
    public function test_all_http_requests_are_capped_to_the_remaining_budget(string $operation): void
    {
        $calls = 0;
        Http::fake(function (Request $request, array $options) use (&$calls, $operation) {
            $calls++;
            $this->assertGreaterThan(0, $options['timeout']);
            $this->assertLessThanOrEqual(5, $options['timeout']);
            $this->assertGreaterThan(0, $options['connect_timeout']);
            $this->assertLessThanOrEqual(5, $options['connect_timeout']);

            return $this->response($request, continuation: $operation === 'keywords' && $calls === 1);
        });

        $this->request($operation, new CoverSyncDeadline(5));
        Http::assertSentCount($operation === 'download' ? 1 : 2);
    }

    #[TestWith(['keywords', false])]
    #[TestWith(['images', false])]
    #[TestWith(['download', false])]
    #[TestWith(['keywords', true])]
    #[TestWith(['images', true])]
    #[TestWith(['download', true])]
    public function test_expiry_during_http_stops_continuations_and_connection_retries(string $operation, bool $connectionFailure): void
    {
        $expired = false;
        $deadline = Mockery::mock(CoverSyncDeadline::class)->makePartial();
        $deadline->shouldReceive('remainingSeconds')->andReturnUsing(function () use (&$expired): float {
            return $expired ? 0 : 5;
        });
        $calls = 0;
        Http::fake(function (Request $request) use (&$expired, &$calls, $connectionFailure) {
            $calls++;
            $expired = true;

            return $connectionFailure ? Http::failedConnection() : $this->response($request, continuation: true);
        });

        try {
            $this->request($operation, $deadline);
            $this->fail('Expected deadline expiration.');
        } catch (CoverSyncTimeoutException) {
            $this->assertSame(1, $calls);
        }
    }

    #[TestWith(['status'])]
    #[TestWith(['maxlag'])]
    #[TestWith(['connection'])]
    public function test_retry_backoff_that_exceeds_the_budget_aborts_without_another_attempt(string $failure): void
    {
        config(['maddraxikon.http.retry_delay_ms' => 5000, 'maddraxikon.http.retry_max_delay_ms' => 5000]);
        $calls = 0;
        Http::fake(function () use (&$calls, $failure) {
            $calls++;

            return match ($failure) {
                'status' => Http::response([], 503, ['Retry-After' => '30']),
                'maxlag' => Http::response(['error' => ['code' => 'maxlag', 'info' => 'Try later']]),
                'connection' => Http::failedConnection(),
            };
        });

        try {
            $this->request('keywords', new CoverSyncDeadline(1));
            $this->fail('Expected deadline expiration.');
        } catch (CoverSyncTimeoutException) {
            $this->assertSame(1, $calls);
        }
    }

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
            return Http::response(['query' => ['results' => []], ...($continuation ? ['query-continue-offset' => 1] : [])]);
        }
        if ($request['prop'] === 'pageimages') {
            return Http::response(['query' => ['pages' => [['pageid' => 101, 'pageimage' => 'A.jpg']]]]);
        }

        return Http::response(['query' => ['pages' => []]]);
    }
}
