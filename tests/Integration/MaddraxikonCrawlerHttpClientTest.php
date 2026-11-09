<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\MaddraxikonCrawlException;
use App\Services\Maddraxikon\MaddraxikonCrawlDeadline;
use App\Services\Maddraxikon\MaddraxikonCrawlerHttpClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

beforeEach(function (): void {
    config(['maddraxikon.http.attempts' => 3, 'maddraxikon.http.retry_delay_ms' => 0, 'maddraxikon.http.retry_max_delay_ms' => 0]);
});

test('transient http errors are retried', function (): void {
    Http::fakeSequence()->push('maintenance', 503)->push('rate limited', 429)->push('<html>ok</html>', 200);
    $body = app(MaddraxikonCrawlerHttpClient::class)->get('https://de.maddraxikon.com/wiki/Test');
    expect($body)->toBe('<html>ok</html>');
    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('User-Agent') && $request->hasHeader('Accept'));
});

test('oversized numeric retry after is bounded before conversion', function (): void {
    config(['maddraxikon.http.retry_max_delay_ms' => 1]);
    Http::fakeSequence()->push('rate limited', 429, ['Retry-After' => str_repeat('9', 100)])->push('<html>ok</html>', 200);
    $body = app(MaddraxikonCrawlerHttpClient::class)->get('https://de.maddraxikon.com/wiki/Test');
    expect($body)->toBe('<html>ok</html>');
    Http::assertSentCount(2);
});

test('connection errors are retried and then fail closed', function (): void {
    Http::fakeSequence()->pushFailedConnection('timeout')->pushFailedConnection('timeout')->pushFailedConnection('timeout');
    $this->expectException(MaddraxikonCrawlException::class);
    $this->expectExceptionMessage('nach mehreren Versuchen');
    try {
        app(MaddraxikonCrawlerHttpClient::class)->get('https://de.maddraxikon.com/wiki/Test');
    } finally {
        Http::assertSentCount(3);
    }
});

test('non transient status is not retried', function (): void {
    Http::fake(['*' => Http::response('not found', 404)]);
    try {
        app(MaddraxikonCrawlerHttpClient::class)->get('https://de.maddraxikon.com/wiki/Fehlt');
        $this->fail('Expected crawl exception.');
    } catch (MaddraxikonCrawlException $exception) {
        expect($exception->statusCode)->toBe(404);
    }
    Http::assertSentCount(1);
});

test('external and insecure urls are rejected without request', function (): void {
    Http::fake();
    $client = app(MaddraxikonCrawlerHttpClient::class);
    foreach (['https://evil.example/wiki/Test', 'http://de.maddraxikon.com/wiki/Test', 'https://de.maddraxikon.com:8443/wiki/Test'] as $url) {
        try {
            $client->get($url);
            $this->fail('Expected URL rejection.');
        } catch (MaddraxikonCrawlException $exception) {
            expect($exception->getMessage())->toContain('Nicht erlaubte');
        }
    }
    Http::assertNothingSent();
});

test('configured non default https port is accepted', function (): void {
    config(['maddraxikon.base_url' => 'https://de.maddraxikon.com:8443']);
    Http::fake(['https://de.maddraxikon.com:8443/wiki/Test' => Http::response('<html>ok</html>')]);
    $body = app(MaddraxikonCrawlerHttpClient::class)->get('https://de.maddraxikon.com:8443/wiki/Test');
    expect($body)->toBe('<html>ok</html>');
    Http::assertSentCount(1);
});

test('https user info is rejected without request or credential exposure', function (): void {
    Http::fake();
    try {
        app(MaddraxikonCrawlerHttpClient::class)->get('https://user:password@de.maddraxikon.com/wiki/Test');
        $this->fail('Expected URL rejection.');
    } catch (MaddraxikonCrawlException $exception) {
        expect($exception->getMessage())->toBe('Nicht erlaubte Maddraxikon-URL.')
            ->and($exception->url)->toBeNull()
            ->and($exception->getMessage())->not->toContain('password');
    }
    Http::assertNothingSent();
});

test('expired crawl deadline aborts before sending a request', function (): void {
    Http::fake();
    try {
        app(MaddraxikonCrawlerHttpClient::class)->get('https://de.maddraxikon.com/wiki/Test', MaddraxikonCrawlDeadline::afterSeconds(0));
        $this->fail('Expected crawl deadline exception.');
    } catch (MaddraxikonCrawlException $exception) {
        expect($exception->getMessage())->toContain('Laufzeit')
            ->and($exception->url)->toBe('https://de.maddraxikon.com/wiki/Test');
    }
    Http::assertNothingSent();
});
