<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\CoverSyncTimeoutException;
use App\Services\CoverRatings\CoverSyncDeadline;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CoverSyncHttpDeadlineTestFixtures::class);

beforeEach(function (): void {
    config(['maddraxikon.base_url' => 'https://wiki.example.test', 'maddraxikon.api_url' => 'https://wiki.example.test/api.php', 'maddraxikon.http.attempts' => 3, 'maddraxikon.http.connect_timeout' => 300, 'maddraxikon.http.timeout' => 300, 'cover-ratings.images.allowed_origins' => ['https://wiki.example.test'], 'cover-ratings.images.connect_timeout' => 300, 'cover-ratings.images.timeout' => 300]);
    Http::preventStrayRequests();
});

test('expired budget prevents any http request', function (string $operation): void {
    Http::fake();
    try {
        $this->request($operation, new CoverSyncDeadline(0));
        $this->fail('Expected deadline expiration.');
    } catch (CoverSyncTimeoutException) {
        Http::assertNothingSent();
    }
})->with([['keywords'], ['images'], ['download']]);

test('all http requests are capped to the remaining budget', function (string $operation): void {
    $calls = 0;
    Http::fake(function (Request $request, array $options) use (&$calls, $operation) {
        $calls++;
        expect($options['timeout'])->toBeGreaterThan(0)
            ->toBeLessThanOrEqual(5)
            ->and($options['connect_timeout'])->toBeGreaterThan(0)
            ->toBeLessThanOrEqual(5);

        return $this->response($request, continuation: $operation === 'keywords' && $calls === 1);
    });
    $this->request($operation, new CoverSyncDeadline(5));
    Http::assertSentCount($operation === 'download' ? 1 : 2);
})->with([['keywords'], ['images'], ['download']]);

test('expiry during http stops continuations and connection retries', function (string $operation, bool $connectionFailure): void {
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
        expect($calls)->toBe(1);
    }
})->with([['keywords', false], ['images', false], ['download', false], ['keywords', true], ['images', true], ['download', true]]);

test('retry backoff that exceeds the budget aborts without another attempt', function (string $failure): void {
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
        expect($calls)->toBe(1);
    }
})->with([['status'], ['maxlag'], ['connection']]);
