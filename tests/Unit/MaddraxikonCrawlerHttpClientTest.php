<?php

namespace Tests\Unit;

use App\Exceptions\MaddraxikonCrawlException;
use App\Services\Maddraxikon\MaddraxikonCrawlerHttpClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MaddraxikonCrawlerHttpClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'maddraxikon.http.attempts' => 3,
            'maddraxikon.http.retry_delay_ms' => 0,
            'maddraxikon.http.retry_max_delay_ms' => 0,
        ]);
    }

    public function test_transient_http_errors_are_retried(): void
    {
        Http::fakeSequence()
            ->push('maintenance', 503)
            ->push('rate limited', 429)
            ->push('<html>ok</html>', 200);

        $body = app(MaddraxikonCrawlerHttpClient::class)
            ->get('https://de.maddraxikon.com/wiki/Test');

        $this->assertSame('<html>ok</html>', $body);
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('User-Agent')
            && $request->hasHeader('Accept')
        );
    }

    public function test_connection_errors_are_retried_and_then_fail_closed(): void
    {
        Http::fakeSequence()
            ->pushFailedConnection('timeout')
            ->pushFailedConnection('timeout')
            ->pushFailedConnection('timeout');

        $this->expectException(MaddraxikonCrawlException::class);
        $this->expectExceptionMessage('nach mehreren Versuchen');

        try {
            app(MaddraxikonCrawlerHttpClient::class)
                ->get('https://de.maddraxikon.com/wiki/Test');
        } finally {
            Http::assertSentCount(3);
        }
    }

    public function test_non_transient_status_is_not_retried(): void
    {
        Http::fake(['*' => Http::response('not found', 404)]);

        try {
            app(MaddraxikonCrawlerHttpClient::class)
                ->get('https://de.maddraxikon.com/wiki/Fehlt');
            $this->fail('Expected crawl exception.');
        } catch (MaddraxikonCrawlException $exception) {
            $this->assertSame(404, $exception->statusCode);
        }

        Http::assertSentCount(1);
    }

    public function test_external_and_insecure_urls_are_rejected_without_request(): void
    {
        Http::fake();
        $client = app(MaddraxikonCrawlerHttpClient::class);

        foreach ([
            'https://evil.example/wiki/Test',
            'http://de.maddraxikon.com/wiki/Test',
            'https://de.maddraxikon.com:8443/wiki/Test',
        ] as $url) {
            try {
                $client->get($url);
                $this->fail('Expected URL rejection.');
            } catch (MaddraxikonCrawlException $exception) {
                $this->assertStringContainsString('Nicht erlaubte', $exception->getMessage());
            }
        }

        Http::assertNothingSent();
    }

    public function test_configured_non_default_https_port_is_accepted(): void
    {
        config(['maddraxikon.base_url' => 'https://de.maddraxikon.com:8443']);
        Http::fake([
            'https://de.maddraxikon.com:8443/wiki/Test' => Http::response('<html>ok</html>'),
        ]);

        $body = app(MaddraxikonCrawlerHttpClient::class)
            ->get('https://de.maddraxikon.com:8443/wiki/Test');

        $this->assertSame('<html>ok</html>', $body);
        Http::assertSentCount(1);
    }

    public function test_https_user_info_is_rejected_without_request_or_credential_exposure(): void
    {
        Http::fake();

        try {
            app(MaddraxikonCrawlerHttpClient::class)
                ->get('https://user:password@de.maddraxikon.com/wiki/Test');
            $this->fail('Expected URL rejection.');
        } catch (MaddraxikonCrawlException $exception) {
            $this->assertSame('Nicht erlaubte Maddraxikon-URL.', $exception->getMessage());
            $this->assertNull($exception->url);
            $this->assertStringNotContainsString('password', $exception->getMessage());
        }

        Http::assertNothingSent();
    }
}
