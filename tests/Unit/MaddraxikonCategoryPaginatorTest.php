<?php

namespace Tests\Unit;

use App\Exceptions\MaddraxikonCrawlException;
use App\Services\Maddraxikon\MaddraxikonCategoryPaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MaddraxikonCategoryPaginatorTest extends TestCase
{
    use RefreshDatabase;

    private const CATEGORY = 'https://de.maddraxikon.com/index.php?title=Kategorie:Maddrax-Heftromane';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'maddraxikon.http.attempts' => 3,
            'maddraxikon.http.retry_delay_ms' => 0,
            'maddraxikon.http.retry_max_delay_ms' => 0,
            'maddraxikon.crawler.max_pages' => 10,
        ]);
    }

    public function test_iteratively_collects_all_pages_and_excludes_navigation_links(): void
    {
        $page2 = self::CATEGORY.'&pagefrom=201';
        $page3 = self::CATEGORY.'&pagefrom=401';
        Http::fake(function (Request $request) use ($page2, $page3) {
            return match ($request->url()) {
                self::CATEGORY => Http::response($this->categoryHtml([
                    '/wiki/MX_1' => 'MX 1',
                    '/wiki/MX_2' => 'MX 2',
                    self::CATEGORY.'&pageuntil=1' => 'vorherige Seite',
                    $page2 => 'nächste Seite',
                ])),
                $page2 => Http::response($this->categoryHtml([
                    '/wiki/MX_2' => 'MX 2 doppelt',
                    'https://de.maddraxikon.com/wiki/MX_3' => 'MX 3',
                    $page3 => 'nächste Seite',
                    $page3 => 'nächste Seite',
                ])),
                $page3 => Http::response($this->categoryHtml([
                    '/wiki/MX_4' => 'MX 4',
                    'https://evil.example/wiki/MX_5' => 'fremd',
                ])),
                default => Http::response('', 404),
            };
        });

        $urls = app(MaddraxikonCategoryPaginator::class)->articleUrls(self::CATEGORY);

        $this->assertSame([
            'https://de.maddraxikon.com/wiki/MX_1',
            'https://de.maddraxikon.com/wiki/MX_2',
            'https://de.maddraxikon.com/wiki/MX_3',
            'https://de.maddraxikon.com/wiki/MX_4',
        ], $urls);
        Http::assertSentCount(3);
    }

    public function test_detects_pagination_loop(): void
    {
        $page2 = self::CATEGORY.'&pagefrom=201';
        Http::fake([
            self::CATEGORY => Http::response($this->categoryHtml([
                '/wiki/MX_1' => 'MX 1',
                $page2 => 'nächste Seite',
            ])),
            $page2 => Http::response($this->categoryHtml([
                '/wiki/MX_2' => 'MX 2',
                self::CATEGORY => 'nächste Seite',
            ])),
        ]);

        $this->expectException(MaddraxikonCrawlException::class);
        $this->expectExceptionMessage('Paginierungsschleife');

        app(MaddraxikonCategoryPaginator::class)->articleUrls(self::CATEGORY);
    }

    public function test_missing_followup_page_aborts_after_retries(): void
    {
        $page2 = self::CATEGORY.'&pagefrom=201';
        Http::fakeSequence()
            ->push($this->categoryHtml([
                '/wiki/MX_1' => 'MX 1',
                $page2 => 'nächste Seite',
            ]))
            ->push('maintenance', 503)
            ->push('maintenance', 503)
            ->push('maintenance', 503);

        try {
            app(MaddraxikonCategoryPaginator::class)->articleUrls(self::CATEGORY);
            $this->fail('Expected crawl exception.');
        } catch (MaddraxikonCrawlException $exception) {
            $this->assertSame($page2, $exception->url);
        }

        Http::assertSentCount(4);
    }

    public function test_followup_link_on_a_different_port_is_rejected_without_fetching_it(): void
    {
        Http::fake([
            self::CATEGORY => Http::response($this->categoryHtml([
                '/wiki/MX_1' => 'MX 1',
                'https://de.maddraxikon.com:8443/index.php?title=Kategorie:Maddrax-Heftromane&pagefrom=201' => 'nächste Seite',
            ])),
        ]);

        $this->expectException(MaddraxikonCrawlException::class);
        $this->expectExceptionMessage('Ungültiger Folgeseiten-Link');

        try {
            app(MaddraxikonCategoryPaginator::class)->articleUrls(self::CATEGORY);
        } finally {
            Http::assertSentCount(1);
        }
    }

    /** @param array<string, string> $links */
    private function categoryHtml(array $links): string
    {
        $anchors = '';

        foreach ($links as $href => $label) {
            $anchors .= '<a href="'.htmlspecialchars($href, ENT_QUOTES).'">'.$label.'</a>';
        }

        return '<html><head><meta charset="UTF-8"></head><body><div id="mw-pages">'.$anchors.'</div></body></html>';
    }
}
