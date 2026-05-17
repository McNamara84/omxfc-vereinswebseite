<?php

namespace Tests\Feature;

use App\Http\Controllers\PhotoGalleryController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class PhotoGalleryControllerTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.nextcloud.links' => [
            '2026' => 'https://cloud.maddrax-fanclub.de/s/fotos2026Token',
            '2025' => 'https://cloud.maddrax-fanclub.de/s/jnGa6sEecKa3fiX',
            '2024' => 'https://cloud.maddrax-fanclub.de/s/tztWY5ML5XMRWPw',
            '2023' => 'https://cloud.maddrax-fanclub.de/s/jjpfnJbgStE8LcQ',
        ]]);
    }

    public function test_index_loads_photos_for_years(): void
    {
        Http::fake([
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/fotos2026Token/' => Http::response($this->propfindResponse('fotos2026Token', ['Foto10.jpg', 'Foto2.jpg', 'Foto1.jpg']), 207),
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/jnGa6sEecKa3fiX/' => Http::response($this->propfindResponse('jnGa6sEecKa3fiX', ['Foto1.jpg']), 207),
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/tztWY5ML5XMRWPw/' => Http::response($this->propfindResponse('tztWY5ML5XMRWPw', ['Foto1.jpg']), 207),
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/jjpfnJbgStE8LcQ/' => Http::response($this->propfindResponse('jjpfnJbgStE8LcQ', ['Foto1.jpg']), 207),
        ]);

        $this->actingAs($this->actingMember());

        $response = $this->get('/fotogalerie');

        $response->assertOk();
        $response->assertViewHas('activeYear', '2026');
        $response->assertViewHas('years', ['2026', '2025', '2024', '2023']);

        $photos = $response->viewData('photos');
        $this->assertEquals('https://cloud.maddrax-fanclub.de/public.php/dav/files/fotos2026Token/Foto1.jpg', $photos['2026'][0]);
        $this->assertEquals('https://cloud.maddrax-fanclub.de/public.php/dav/files/fotos2026Token/Foto2.jpg', $photos['2026'][1]);
        $this->assertEquals('https://cloud.maddrax-fanclub.de/public.php/dav/files/fotos2026Token/Foto10.jpg', $photos['2026'][2]);
        $this->assertEquals('https://cloud.maddrax-fanclub.de/public.php/dav/files/jnGa6sEecKa3fiX/Foto1.jpg', $photos['2025'][0]);
        $this->assertEquals('https://cloud.maddrax-fanclub.de/public.php/dav/files/tztWY5ML5XMRWPw/Foto1.jpg', $photos['2024'][0]);
        $this->assertEquals('https://cloud.maddrax-fanclub.de/public.php/dav/files/jjpfnJbgStE8LcQ/Foto1.jpg', $photos['2023'][0]);
    }

    public function test_index_uses_fallback_when_no_photos_found(): void
    {
        Http::fake([
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/*/' => Http::response($this->propfindResponse('leer', ['notizen.txt'], includeImages: false), 207),
        ]);

        $this->actingAs($this->actingMember());

        $response = $this->get('/fotogalerie');

        $response->assertOk();

        $photos = $response->viewData('photos');
        $this->assertStringStartsWith('data:image/svg+xml;charset=UTF-8,', $photos['2026'][0]);
        $this->assertStringStartsWith('data:image/svg+xml;charset=UTF-8,', $photos['2025'][0]);
        $this->assertStringStartsWith('data:image/svg+xml;charset=UTF-8,', $photos['2024'][0]);
        $this->assertStringStartsWith('data:image/svg+xml;charset=UTF-8,', $photos['2023'][0]);
        $this->assertStringContainsString('2026', rawurldecode(explode(',', $photos['2026'][0], 2)[1]));
    }

    public function test_index_filters_years_without_configured_links(): void
    {
        config(['services.nextcloud.links' => [
            '2026' => '',
            '2025' => 'https://cloud.maddrax-fanclub.de/s/jnGa6sEecKa3fiX',
            '2024' => '',
            '2023' => 'https://cloud.maddrax-fanclub.de/s/jjpfnJbgStE8LcQ',
        ]]);

        Http::fake([
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/jnGa6sEecKa3fiX/' => Http::response($this->propfindResponse('jnGa6sEecKa3fiX', ['Foto1.jpg']), 207),
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/jjpfnJbgStE8LcQ/' => Http::response($this->propfindResponse('jjpfnJbgStE8LcQ', ['Foto1.jpg']), 207),
        ]);

        $this->actingAs($this->actingMember());

        $response = $this->get('/fotogalerie');

        $response->assertOk();
        $response->assertViewHas('years', ['2025', '2023']);
        $response->assertViewHas('activeYear', '2025');

        $photos = $response->viewData('photos');
        $this->assertSame(['2025', '2023'], array_map('strval', array_keys($photos)));
    }

    public function test_proxy_image_returns_remote_file(): void
    {
        Http::fake([
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/fotos2026Token/' => Http::response($this->propfindResponse('fotos2026Token', ['Foto1.jpg']), 207),
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/fotos2026Token/Foto1.jpg' => Http::response('img', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $response = app(PhotoGalleryController::class)->proxyImage('2026', 1);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertEquals('img', $response->getContent());
    }

    public function test_proxy_image_returns_placeholder_on_failure(): void
    {
        Http::fake([
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/fotos2026Token/' => Http::response($this->propfindResponse('fotos2026Token', ['Foto1.jpg']), 207),
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/fotos2026Token/Foto1.jpg' => Http::response('', 404),
        ]);

        $response = app(PhotoGalleryController::class)->proxyImage('2026', 1);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/svg+xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('2026', $response->getContent());
    }

    private function propfindResponse(string $token, array $files, bool $includeImages = true): string
    {
        $responses = [<<<XML
<d:response>
    <d:href>/public.php/dav/files/{$token}/</d:href>
    <d:propstat>
        <d:prop>
            <d:resourcetype><d:collection/></d:resourcetype>
        </d:prop>
        <d:status>HTTP/1.1 200 OK</d:status>
    </d:propstat>
</d:response>
XML];

        foreach ($files as $file) {
            $contentType = $includeImages ? 'image/jpeg' : 'text/plain';

            $responses[] = <<<XML
<d:response>
    <d:href>/public.php/dav/files/{$token}/{$file}</d:href>
    <d:propstat>
        <d:prop>
            <d:getcontenttype>{$contentType}</d:getcontenttype>
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
