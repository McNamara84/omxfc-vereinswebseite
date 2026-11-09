<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\MaddraxikonCrawlException;
use App\Services\Maddraxikon\MaddraxikonCategoryPaginator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxikonCategoryPaginatorTestFixtures::class);

beforeEach(function (): void {
    config(['maddraxikon.http.attempts' => 3, 'maddraxikon.http.retry_delay_ms' => 0, 'maddraxikon.http.retry_max_delay_ms' => 0, 'maddraxikon.crawler.max_pages' => 10]);
});

test('iteratively collects all pages and excludes navigation links', function (): void {
    $page2 = self::CATEGORY.'&pagefrom=201';
    $page3 = self::CATEGORY.'&pagefrom=401';
    Http::fake(fn (Request $request) => match ($request->url()) {
        self::CATEGORY => Http::response($this->categoryHtml(['/wiki/MX_1' => 'MX 1', '/wiki/MX_2' => 'MX 2', '******de.maddraxikon.com/wiki/MX_6' => 'ungültiger relativer Link', self::CATEGORY.'&pageuntil=1' => 'vorherige Seite', $page2 => 'nächste Seite'])),
        $page2 => Http::response($this->categoryHtml(['/wiki/MX_2' => 'MX 2 doppelt', 'https://de.maddraxikon.com/wiki/MX_3' => 'MX 3', $page3 => 'nächste Seite', $page3 => 'nächste Seite'])),
        $page3 => Http::response($this->categoryHtml(['/wiki/MX_4' => 'MX 4', '/index.php?title=MX_5' => 'MX 5', 'https://evil.example/wiki/MX_5' => 'fremd', 'https://user:password@de.maddraxikon.com/wiki/MX_6' => 'mit Zugangsdaten'])),
        default => Http::response('', 404),
    });
    $urls = app(MaddraxikonCategoryPaginator::class)->articleUrls(self::CATEGORY);
    expect($urls)->toBe(['https://de.maddraxikon.com/wiki/MX_1', 'https://de.maddraxikon.com/wiki/MX_2', 'https://de.maddraxikon.com/wiki/MX_3', 'https://de.maddraxikon.com/wiki/MX_4', 'https://de.maddraxikon.com/index.php?title=MX_5']);
    Http::assertSentCount(3);
});

test('resolves query only followup against the current category', function (): void {
    $page2 = 'https://de.maddraxikon.com/index.php?title=Kategorie%3AMaddrax-Heftromane&pagefrom=201';
    Http::fake([self::CATEGORY => Http::response($this->categoryHtml(['/wiki/MX_1' => 'MX 1', '?pagefrom=201' => 'nächste Seite'])), $page2 => Http::response($this->categoryHtml(['/wiki/MX_2' => 'MX 2']))]);
    expect(app(MaddraxikonCategoryPaginator::class)->articleUrls(self::CATEGORY))->toBe(['https://de.maddraxikon.com/wiki/MX_1', 'https://de.maddraxikon.com/wiki/MX_2']);
    Http::assertSentCount(2);
});

test('detects pagination loop', function (): void {
    $page2 = self::CATEGORY.'&pagefrom=201';
    Http::fake([self::CATEGORY => Http::response($this->categoryHtml(['/wiki/MX_1' => 'MX 1', $page2 => 'nächste Seite'])), $page2 => Http::response($this->categoryHtml(['/wiki/MX_2' => 'MX 2', self::CATEGORY => 'nächste Seite']))]);
    expect(fn () => app(MaddraxikonCategoryPaginator::class)->articleUrls(self::CATEGORY))->toThrow(MaddraxikonCrawlException::class, 'Paginierungsschleife');
});

test('missing followup page aborts after retries', function (): void {
    $page2 = self::CATEGORY.'&pagefrom=201';
    Http::fakeSequence()->push($this->categoryHtml(['/wiki/MX_1' => 'MX 1', $page2 => 'nächste Seite']))->push('maintenance', 503)->push('maintenance', 503)->push('maintenance', 503);
    try {
        app(MaddraxikonCategoryPaginator::class)->articleUrls(self::CATEGORY);
        $this->fail('Expected crawl exception.');
    } catch (MaddraxikonCrawlException $exception) {
        expect($exception->url)->toBe($page2);
    }
    Http::assertSentCount(4);
});

test('followup link on a different port is rejected without fetching it', function (): void {
    Http::fake([self::CATEGORY => Http::response($this->categoryHtml(['/wiki/MX_1' => 'MX 1', 'https://de.maddraxikon.com:8443/index.php?title=Kategorie:Maddrax-Heftromane&pagefrom=201' => 'nächste Seite']))]);
    $this->expectException(MaddraxikonCrawlException::class);
    $this->expectExceptionMessage('Ungültiger Folgeseiten-Link');
    try {
        app(MaddraxikonCategoryPaginator::class)->articleUrls(self::CATEGORY);
    } finally {
        Http::assertSentCount(1);
    }
});

test('nul query parameters are rejected before php query parsing', function (): void {
    $paginator = app(MaddraxikonCategoryPaginator::class);
    $resolve = new \ReflectionMethod($paginator, 'resolve');
    expect($resolve->invoke($paginator, self::CATEGORY, "?pagefrom=MX\x00_1"))->toBeNull()
        ->and($resolve->invoke($paginator, self::CATEGORY."\x00", '?pagefrom=MX_1'))->toBeNull();
    Http::assertNothingSent();
});
