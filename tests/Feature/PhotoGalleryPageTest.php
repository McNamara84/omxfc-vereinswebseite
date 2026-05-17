<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class PhotoGalleryPageTest extends TestCase
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

    public function test_photo_gallery_page_shows_context_and_year_overview(): void
    {
        Http::fake([
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/fotos2026Token/' => Http::response($this->propfindResponse('fotos2026Token', ['Foto1.jpg', 'Foto2.jpg']), 207),
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/jnGa6sEecKa3fiX/' => Http::response($this->propfindResponse('jnGa6sEecKa3fiX', ['Foto1.jpg']), 207),
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/tztWY5ML5XMRWPw/' => Http::response($this->propfindResponse('tztWY5ML5XMRWPw', ['Foto1.jpg']), 207),
            'https://cloud.maddrax-fanclub.de/public.php/dav/files/jjpfnJbgStE8LcQ/' => Http::response($this->propfindResponse('jjpfnJbgStE8LcQ', ['Foto1.jpg']), 207),
        ]);

        $this->actingAs($this->actingMember());

        $response = $this->withoutVite()->get('/fotogalerie');

        $response->assertOk();
        $response->assertSeeText('Fotogalerie');
        $response->assertSeeText('Galerieansicht');
        $response->assertSeeText('Jahre im Überblick');
        $response->assertSeeText('Hinweise zur Galerie');
        $response->assertSeeText('Fotos 2026');
        $response->assertSeeText('Fotos 2025');
        $response->assertSeeText('5 Bilder');
    }

    private function propfindResponse(string $token, array $files): string
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
            $responses[] = <<<XML
<d:response>
    <d:href>/public.php/dav/files/{$token}/{$file}</d:href>
    <d:propstat>
        <d:prop>
            <d:getcontenttype>image/jpeg</d:getcontenttype>
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
