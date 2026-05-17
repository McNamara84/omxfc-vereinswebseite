<?php

namespace Tests\Unit\Services;

use App\Services\NextcloudGalleryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(NextcloudGalleryService::class)]
class NextcloudGalleryServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function photo_urls_resolve_share_links_and_sort_images_naturally(): void
    {
        Http::fake([
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/' => Http::response(
                $this->propfindResponse('shareToken', [
                    'Foto10.jpg',
                    'Foto2.jpg',
                    'Foto1.jpg',
                    'Hinweis.txt',
                ]),
                207,
            ),
        ]);

        $photos = app(NextcloudGalleryService::class)->photoUrls('https://cloud.maddrax-fanclub.de/s/shareToken');

        $this->assertSame([
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto1.jpg',
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto2.jpg',
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto10.jpg',
        ], $photos);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'PROPFIND'
                && $request->url() === 'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/'
                && in_array('1', $request->header('Depth'), true)
                && str_contains($request->header('Content-Type')[0] ?? '', 'application/xml')
                && str_contains($request->body(), '<d:resourcetype/>')
                && str_contains($request->body(), '<d:getcontenttype/>');
        });
    }

    #[Test]
    public function photo_urls_support_legacy_dav_prefix_links(): void
    {
        Http::fake([
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/' => Http::response(
                $this->propfindResponse('shareToken', [
                    'Banner1.jpg',
                    'Foto2.jpg',
                    'Foto1.jpg',
                ]),
                207,
            ),
        ]);

        $photos = app(NextcloudGalleryService::class)->photoUrls('https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto');

        $this->assertSame([
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto1.jpg',
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto2.jpg',
        ], $photos);
    }

    #[Test]
    public function photo_urls_cache_the_resolved_listing(): void
    {
        Http::fake([
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/' => Http::response(
                $this->propfindResponse('shareToken', ['Foto1.jpg']),
                207,
            ),
        ]);

        $service = app(NextcloudGalleryService::class);

        $service->photoUrls('https://cloud.maddrax-fanclub.de/s/shareToken');
        $service->photoUrls('https://cloud.maddrax-fanclub.de/s/shareToken');

        Http::assertSentCount(1);
    }

    #[Test]
    public function photo_urls_do_not_cache_transient_failures(): void
    {
        Http::fake([
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/' => Http::sequence()
                ->push('', 503)
                ->push($this->propfindResponse('shareToken', ['Foto1.jpg']), 207),
        ]);

        $service = app(NextcloudGalleryService::class);

        $this->assertSame([], $service->photoUrls('https://cloud.maddrax-fanclub.de/s/shareToken'));
        $this->assertSame([
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto1.jpg',
        ], $service->photoUrls('https://cloud.maddrax-fanclub.de/s/shareToken'));

        Http::assertSentCount(2);
    }

    #[Test]
    public function photo_urls_cache_empty_listings_only_briefly(): void
    {
        Http::fake([
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/' => Http::sequence()
                ->push($this->propfindResponse('shareToken', ['Hinweis.txt']), 207)
                ->push($this->propfindResponse('shareToken', ['Foto1.jpg']), 207),
        ]);

        $service = app(NextcloudGalleryService::class);

        $this->assertSame([], $service->photoUrls('https://cloud.maddrax-fanclub.de/s/shareToken'));
        $this->assertSame([], $service->photoUrls('https://cloud.maddrax-fanclub.de/s/shareToken'));

        Http::assertSentCount(1);

        $this->travel(121)->seconds();

        $this->assertSame([
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto1.jpg',
        ], $service->photoUrls('https://cloud.maddrax-fanclub.de/s/shareToken'));

        Http::assertSentCount(2);
    }

    #[Test]
    public function photo_urls_trim_accidental_quotes_from_links(): void
    {
        Http::fake([
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/' => Http::response(
                $this->propfindResponse('shareToken', ['Foto1.jpg']),
                207,
            ),
        ]);

        $photos = app(NextcloudGalleryService::class)->photoUrls("'https://cloud.maddrax-fanclub.de/s/shareToken'");

        $this->assertSame([
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto1.jpg',
        ], $photos);
    }

    #[Test]
    public function photo_urls_ignore_external_absolute_hrefs(): void
    {
        Http::fake([
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/' => Http::response(
                $this->propfindResponseWithEntries('/public.php/dav/files/shareToken/', [
                    [
                        'href' => 'https://evil.example/steal.jpg',
                        'contentType' => 'image/jpeg',
                    ],
                    [
                        'href' => 'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto1.jpg',
                        'contentType' => 'image/jpeg',
                    ],
                ]),
                207,
            ),
        ]);

        $photos = app(NextcloudGalleryService::class)->photoUrls('https://cloud.maddrax-fanclub.de/s/shareToken');

        $this->assertSame([
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto1.jpg',
        ], $photos);
    }

    private function propfindResponse(string $token, array $files): string
    {
        $entries = array_map(
            fn (string $file): array => [
                'href' => '/public.php/dav/files/'.$token.'/'.$file,
                'contentType' => str_ends_with(strtolower($file), '.txt') ? 'text/plain' : 'image/jpeg',
            ],
            $files,
        );

        return $this->propfindResponseWithEntries('/public.php/dav/files/'.$token.'/', $entries);
    }

    /**
     * @param  array<int, array{href: string, contentType: string}>  $entries
     */
    private function propfindResponseWithEntries(string $collectionHref, array $entries): string
    {
        $responses = [<<<XML
<d:response>
    <d:href>{$collectionHref}</d:href>
    <d:propstat>
        <d:prop>
            <d:resourcetype><d:collection/></d:resourcetype>
        </d:prop>
        <d:status>HTTP/1.1 200 OK</d:status>
    </d:propstat>
</d:response>
XML];

        foreach ($entries as $entry) {
            $responses[] = <<<XML
<d:response>
    <d:href>{$entry['href']}</d:href>
    <d:propstat>
        <d:prop>
            <d:getcontenttype>{$entry['contentType']}</d:getcontenttype>
        </d:prop>
        <d:status>HTTP/1.1 200 OK</d:status>
    </d:propstat>
</d:response>
XML;
        }

        $items = implode('', $responses);

        return <<<XML
<?xml version="1.0"?>
<d:multistatus xmlns:d="DAV:">
    {$items}
</d:multistatus>
XML;
    }
}
