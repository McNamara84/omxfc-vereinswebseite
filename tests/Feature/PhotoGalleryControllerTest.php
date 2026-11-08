<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\PhotoGalleryController;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(PhotoGalleryControllerTestFixtures::class);

beforeEach(function (): void {
    config(['services.nextcloud.links' => ['2026' => 'https://cloud.maddrax-fanclub.de/s/fotos2026Token', '2025' => 'https://cloud.maddrax-fanclub.de/s/jnGa6sEecKa3fiX', '2024' => 'https://cloud.maddrax-fanclub.de/s/tztWY5ML5XMRWPw', '2023' => 'https://cloud.maddrax-fanclub.de/s/jjpfnJbgStE8LcQ']]);
});

test('index loads photos for years', function (): void {
    Http::fake(['https://cloud.maddrax-fanclub.de/public.php/dav/files/fotos2026Token/' => Http::response($this->propfindResponse('fotos2026Token', ['Foto10.jpg', 'Foto2.jpg', 'Foto1.jpg']), 207), 'https://cloud.maddrax-fanclub.de/public.php/dav/files/jnGa6sEecKa3fiX/' => Http::response($this->propfindResponse('jnGa6sEecKa3fiX', ['Foto1.jpg']), 207), 'https://cloud.maddrax-fanclub.de/public.php/dav/files/tztWY5ML5XMRWPw/' => Http::response($this->propfindResponse('tztWY5ML5XMRWPw', ['Foto1.jpg']), 207), 'https://cloud.maddrax-fanclub.de/public.php/dav/files/jjpfnJbgStE8LcQ/' => Http::response($this->propfindResponse('jjpfnJbgStE8LcQ', ['Foto1.jpg']), 207)]);
    $this->actingAs($this->actingMember());
    $response = $this->get('/fotogalerie');
    $response->assertOk();
    $response->assertViewHas('activeYear', '2026');
    $response->assertViewHas('years', ['2026', '2025', '2024', '2023']);
    $photos = $response->viewData('photos');
    expect($photos['2026'])->sequence(fn ($e) => $e->toEqual('https://cloud.maddrax-fanclub.de/public.php/dav/files/fotos2026Token/Foto1.jpg'), fn ($e) => $e->toEqual('https://cloud.maddrax-fanclub.de/public.php/dav/files/fotos2026Token/Foto2.jpg'), fn ($e) => $e->toEqual('https://cloud.maddrax-fanclub.de/public.php/dav/files/fotos2026Token/Foto10.jpg'))
        ->and($photos['2025'][0])->toEqual('https://cloud.maddrax-fanclub.de/public.php/dav/files/jnGa6sEecKa3fiX/Foto1.jpg')
        ->and($photos['2024'][0])->toEqual('https://cloud.maddrax-fanclub.de/public.php/dav/files/tztWY5ML5XMRWPw/Foto1.jpg')
        ->and($photos['2023'][0])->toEqual('https://cloud.maddrax-fanclub.de/public.php/dav/files/jjpfnJbgStE8LcQ/Foto1.jpg');
});

test('index uses fallback when no photos found', function (): void {
    Http::fake(['https://cloud.maddrax-fanclub.de/public.php/dav/files/*/' => Http::response($this->propfindResponse('leer', ['notizen.txt'], includeImages: false), 207)]);
    $this->actingAs($this->actingMember());
    $response = $this->get('/fotogalerie');
    $response->assertOk();
    $photos = $response->viewData('photos');
    expect($photos['2026'][0])->toStartWith('data:image/svg+xml;charset=UTF-8,')
        ->and($photos['2025'][0])->toStartWith('data:image/svg+xml;charset=UTF-8,')
        ->and($photos['2024'][0])->toStartWith('data:image/svg+xml;charset=UTF-8,')
        ->and($photos['2023'][0])->toStartWith('data:image/svg+xml;charset=UTF-8,')
        ->and(rawurldecode(explode(',', $photos['2026'][0], 2)[1]))->toContain('2026');
});

test('index filters years without configured links', function (): void {
    config(['services.nextcloud.links' => ['2026' => '', '2025' => 'https://cloud.maddrax-fanclub.de/s/jnGa6sEecKa3fiX', '2024' => '', '2023' => 'https://cloud.maddrax-fanclub.de/s/jjpfnJbgStE8LcQ']]);
    Http::fake(['https://cloud.maddrax-fanclub.de/public.php/dav/files/jnGa6sEecKa3fiX/' => Http::response($this->propfindResponse('jnGa6sEecKa3fiX', ['Foto1.jpg']), 207), 'https://cloud.maddrax-fanclub.de/public.php/dav/files/jjpfnJbgStE8LcQ/' => Http::response($this->propfindResponse('jjpfnJbgStE8LcQ', ['Foto1.jpg']), 207)]);
    $this->actingAs($this->actingMember());
    $response = $this->get('/fotogalerie');
    $response->assertOk();
    $response->assertViewHas('years', ['2025', '2023']);
    $response->assertViewHas('activeYear', '2025');
    $photos = $response->viewData('photos');
    expect(array_map(strval(...), array_keys($photos)))->toBe(['2025', '2023']);
});

test('proxy image returns remote file', function (): void {
    Http::fake(['https://cloud.maddrax-fanclub.de/public.php/dav/files/fotos2026Token/' => Http::response($this->propfindResponse('fotos2026Token', ['Foto1.webp']), 207), 'https://cloud.maddrax-fanclub.de/public.php/dav/files/fotos2026Token/Foto1.webp' => Http::response('img', 200, ['Content-Type' => 'image/webp; charset=binary'])]);
    $response = app(PhotoGalleryController::class)->proxyImage('2026', 1);
    expect($response->getStatusCode())->toEqual(200)
        ->and($response->headers->get('Content-Type'))->toEqual('image/webp')
        ->and($response->getContent())->toEqual('img');
});

test('proxy image falls back to extension based content type when remote type is invalid', function (): void {
    Http::fake(['https://cloud.maddrax-fanclub.de/public.php/dav/files/fotos2026Token/' => Http::response($this->propfindResponse('fotos2026Token', ['Foto1.png']), 207), 'https://cloud.maddrax-fanclub.de/public.php/dav/files/fotos2026Token/Foto1.png' => Http::response('img', 200, ['Content-Type' => 'application/octet-stream'])]);
    $response = app(PhotoGalleryController::class)->proxyImage('2026', 1);
    expect($response->getStatusCode())->toEqual(200)
        ->and($response->headers->get('Content-Type'))->toEqual('image/png')
        ->and($response->getContent())->toEqual('img');
});

test('proxy image does not forward non whitelisted image content types', function (): void {
    Http::fake(['https://cloud.maddrax-fanclub.de/public.php/dav/files/fotos2026Token/' => Http::response($this->propfindResponse('fotos2026Token', ['Foto1.jpg']), 207), 'https://cloud.maddrax-fanclub.de/public.php/dav/files/fotos2026Token/Foto1.jpg' => Http::response('img', 200, ['Content-Type' => 'image/svg+xml'])]);
    $response = app(PhotoGalleryController::class)->proxyImage('2026', 1);
    expect($response->getStatusCode())->toEqual(200)
        ->and($response->headers->get('Content-Type'))->toEqual('image/jpeg')
        ->and($response->getContent())->toEqual('img');
});

test('proxy image returns placeholder on failure', function (): void {
    Http::fake(['https://cloud.maddrax-fanclub.de/public.php/dav/files/fotos2026Token/' => Http::response($this->propfindResponse('fotos2026Token', ['Foto1.jpg']), 207), 'https://cloud.maddrax-fanclub.de/public.php/dav/files/fotos2026Token/Foto1.jpg' => Http::response('', 404)]);
    $response = app(PhotoGalleryController::class)->proxyImage('2026', 1);
    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toEqual(200)
        ->and($response->headers->get('Content-Type'))->toEqual('image/svg+xml')
        ->and($response->getContent())->toContain('2026');
});
