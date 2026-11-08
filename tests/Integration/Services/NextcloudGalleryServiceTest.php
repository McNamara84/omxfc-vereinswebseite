<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Services\NextcloudGalleryService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(NextcloudGalleryServiceTestFixtures::class);

covers(NextcloudGalleryService::class);

test('photo urls resolve share links and sort images naturally', function (): void {
    Http::fake(['https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/' => Http::response($this->propfindResponse('shareToken', ['Foto10.jpg', 'Foto2.jpg', 'Foto1.jpg', 'Hinweis.txt']), 207)]);
    $photos = app(NextcloudGalleryService::class)->photoUrls('https://cloud.maddrax-fanclub.de/s/shareToken');
    expect($photos)->toBe(['https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto1.jpg', 'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto2.jpg', 'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto10.jpg']);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PROPFIND' && $request->url() === 'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/' && in_array('1', $request->header('Depth'), true) && str_contains($request->header('Content-Type')[0] ?? '', 'application/xml') && str_contains($request->body(), '<d:resourcetype/>') && str_contains($request->body(), '<d:getcontenttype/>'));
});

test('photo urls support legacy dav prefix links', function (): void {
    Http::fake(['https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto/' => Http::response('', 404), 'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/' => Http::response($this->propfindResponse('shareToken', ['Banner1.jpg', 'Foto2.jpg', 'Foto1.jpg']), 207)]);
    $photos = app(NextcloudGalleryService::class)->photoUrls('https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto');
    expect($photos)->toBe(['https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto1.jpg', 'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto2.jpg']);
    Http::assertSentCount(2);
});

test('photo urls treat suffix without trailing slash as directory before prefix fallback', function (): void {
    Http::fake(['https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/some-folder/' => Http::response($this->propfindResponseWithEntries('/public.php/dav/files/shareToken/some-folder/', [['href' => '/public.php/dav/files/shareToken/some-folder/Foto1.jpg', 'contentType' => 'image/jpeg']]), 207), 'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/' => Http::response($this->propfindResponse('shareToken', ['Foto9.jpg']), 207)]);
    $photos = app(NextcloudGalleryService::class)->photoUrls('https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/some-folder');
    expect($photos)->toBe(['https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/some-folder/Foto1.jpg']);
    Http::assertSentCount(1);
});

test('photo urls ignore malformed gallery links without http requests', function (): void {
    Http::fake();
    $photos = app(NextcloudGalleryService::class)->photoUrls('http://example.com:bad/path');
    expect($photos)->toBe([]);
    Http::assertNothingSent();
});

test('photo urls cache the resolved listing', function (): void {
    Http::fake(['https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/' => Http::response($this->propfindResponse('shareToken', ['Foto1.jpg']), 207)]);
    $service = app(NextcloudGalleryService::class);
    $service->photoUrls('https://cloud.maddrax-fanclub.de/s/shareToken');
    $service->photoUrls('https://cloud.maddrax-fanclub.de/s/shareToken');
    Http::assertSentCount(1);
});

test('photo urls do not cache transient failures', function (): void {
    Http::fake(['https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/' => Http::sequence()->push('', 503)->push($this->propfindResponse('shareToken', ['Foto1.jpg']), 207)]);
    $service = app(NextcloudGalleryService::class);
    expect($service->photoUrls('https://cloud.maddrax-fanclub.de/s/shareToken'))->toBe([])
        ->and($service->photoUrls('https://cloud.maddrax-fanclub.de/s/shareToken'))->toBe(['https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto1.jpg']);
    Http::assertSentCount(2);
});

test('photo urls cache empty listings only briefly', function (): void {
    Http::fake(['https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/' => Http::sequence()->push($this->propfindResponse('shareToken', ['Hinweis.txt']), 207)->push($this->propfindResponse('shareToken', ['Foto1.jpg']), 207)]);
    $service = app(NextcloudGalleryService::class);
    expect($service->photoUrls('https://cloud.maddrax-fanclub.de/s/shareToken'))->toBe([])
        ->and($service->photoUrls('https://cloud.maddrax-fanclub.de/s/shareToken'))->toBe([]);
    Http::assertSentCount(1);
    $this->travel(121)->seconds();
    expect($service->photoUrls('https://cloud.maddrax-fanclub.de/s/shareToken'))->toBe(['https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto1.jpg']);
    Http::assertSentCount(2);
});

test('photo urls trim accidental quotes from links', function (): void {
    Http::fake(['https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/' => Http::response($this->propfindResponse('shareToken', ['Foto1.jpg']), 207)]);
    $photos = app(NextcloudGalleryService::class)->photoUrls("'https://cloud.maddrax-fanclub.de/s/shareToken'");
    expect($photos)->toBe(['https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto1.jpg']);
});

test('photo urls ignore external absolute hrefs', function (): void {
    Http::fake(['https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/' => Http::response($this->propfindResponseWithEntries('/public.php/dav/files/shareToken/', [['href' => 'https://evil.example/steal.jpg', 'contentType' => 'image/jpeg'], ['href' => 'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto1.jpg', 'contentType' => 'image/jpeg']]), 207)]);
    $photos = app(NextcloudGalleryService::class)->photoUrls('https://cloud.maddrax-fanclub.de/s/shareToken');
    expect($photos)->toBe(['https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto1.jpg']);
});

test('photo urls ignore non whitelisted image content types', function (): void {
    Http::fake(['https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/' => Http::response($this->propfindResponseWithEntries('/public.php/dav/files/shareToken/', [['href' => '/public.php/dav/files/shareToken/vector.svg', 'contentType' => 'image/svg+xml; charset=utf-8'], ['href' => '/public.php/dav/files/shareToken/Foto1.webp', 'contentType' => 'image/webp; charset=binary']]), 207)]);
    $photos = app(NextcloudGalleryService::class)->photoUrls('https://cloud.maddrax-fanclub.de/s/shareToken');
    expect($photos)->toBe(['https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto1.webp']);
});

test('photo urls ignore traversal segments in dav hrefs', function (): void {
    Http::fake(['https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/' => Http::response($this->propfindResponseWithEntries('/public.php/dav/files/shareToken/', [['href' => '/public.php/dav/files/shareToken/../other-token/Foto1.jpg', 'contentType' => 'image/jpeg'], ['href' => 'https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/%2E%2E/other-token/Foto2.jpg', 'contentType' => 'image/jpeg'], ['href' => '/public.php/dav/files/shareToken/Foto3.jpg', 'contentType' => 'image/jpeg']]), 207)]);
    $photos = app(NextcloudGalleryService::class)->photoUrls('https://cloud.maddrax-fanclub.de/s/shareToken');
    expect($photos)->toBe(['https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto3.jpg']);
});

test('photo urls do not append trailing question mark for empty query parts', function (): void {
    Http::fake(['https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/' => Http::response($this->propfindResponseWithEntries('/public.php/dav/files/shareToken/', [['href' => '/public.php/dav/files/shareToken/Foto1.jpg?#fragment', 'contentType' => 'image/jpeg']]), 207)]);
    $photos = app(NextcloudGalleryService::class)->photoUrls('https://cloud.maddrax-fanclub.de/s/shareToken');
    expect($photos)->toBe(['https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto1.jpg']);
});

test('photo urls reject doctype responses and do not cache them', function (): void {
    Http::fake(['https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/' => Http::sequence()->push($this->propfindResponseWithDoctype('shareToken', ['Foto1.jpg']), 207)->push($this->propfindResponse('shareToken', ['Foto1.jpg']), 207)]);
    $service = app(NextcloudGalleryService::class);
    expect($service->photoUrls('https://cloud.maddrax-fanclub.de/s/shareToken'))->toBe([])
        ->and($service->photoUrls('https://cloud.maddrax-fanclub.de/s/shareToken'))->toBe(['https://cloud.maddrax-fanclub.de/public.php/dav/files/shareToken/Foto1.jpg']);
    Http::assertSentCount(2);
});
