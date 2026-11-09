<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(PhotoGalleryPageTestFixtures::class);

beforeEach(function (): void {
    config(['services.nextcloud.links' => ['2026' => 'https://cloud.maddrax-fanclub.de/s/fotos2026Token', '2025' => 'https://cloud.maddrax-fanclub.de/s/jnGa6sEecKa3fiX', '2024' => 'https://cloud.maddrax-fanclub.de/s/tztWY5ML5XMRWPw', '2023' => 'https://cloud.maddrax-fanclub.de/s/jjpfnJbgStE8LcQ']]);
});

test('photo gallery page shows context and year overview', function (): void {
    Http::fake(['https://cloud.maddrax-fanclub.de/public.php/dav/files/fotos2026Token/' => Http::response($this->propfindResponse('fotos2026Token', ['Foto1.jpg', 'Foto2.jpg']), 207), 'https://cloud.maddrax-fanclub.de/public.php/dav/files/jnGa6sEecKa3fiX/' => Http::response($this->propfindResponse('jnGa6sEecKa3fiX', ['Foto1.jpg']), 207), 'https://cloud.maddrax-fanclub.de/public.php/dav/files/tztWY5ML5XMRWPw/' => Http::response($this->propfindResponse('tztWY5ML5XMRWPw', ['Foto1.jpg']), 207), 'https://cloud.maddrax-fanclub.de/public.php/dav/files/jjpfnJbgStE8LcQ/' => Http::response($this->propfindResponse('jjpfnJbgStE8LcQ', ['Foto1.jpg']), 207)]);
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
});
