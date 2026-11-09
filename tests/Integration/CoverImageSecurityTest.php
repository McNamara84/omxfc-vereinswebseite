<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\BookType;
use App\Exceptions\CoverImageException;
use App\Models\Book;
use App\Services\CoverRatings\CoverImageDownloader;
use App\Services\CoverRatings\CoverImageProcessor;
use App\Services\CoverRatings\CoverImageUrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);
dataset('CoverImageSecurityTest.unsafeUrlProvider', static fn (): array => ['plain HTTP' => ['http://wiki.example.test/cover.jpg'], 'different host' => ['https://evil.example.test/cover.jpg'], 'host suffix trick' => ['https://wiki.example.test.evil.test/cover.jpg'], 'credentials' => ['https://user:secret@wiki.example.test/cover.jpg'], 'different port' => ['https://wiki.example.test:8443/cover.jpg'], 'fragment' => ['https://wiki.example.test/cover.jpg#fragment'], 'relative URL' => ['/cover.jpg']]);
dataset('CoverImageSecurityTest.invalidDownloadProvider', static fn (): array => ['redirect' => [302, 'moved', ['Content-Type' => 'image/jpeg', 'Location' => 'https://evil.test/x']], 'declared oversize' => [200, 'tiny', ['Content-Type' => 'image/jpeg', 'Content-Length' => '17']], 'actual oversize' => [200, '12345678901234567', ['Content-Type' => 'image/jpeg']], 'empty' => [200, '', ['Content-Type' => 'image/jpeg']], 'wrong MIME' => [200, '<svg/>', ['Content-Type' => 'image/svg+xml']]]);
beforeEach(function (): void {
    config(['cover-ratings.images.allowed_origins' => ['https://wiki.example.test'], 'cover-ratings.images.allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'], 'cover-ratings.images.max_bytes' => 16, 'cover-ratings.images.disk' => 'private', 'cover-ratings.images.directory' => 'cover-ratings', 'cover-ratings.images.small_width' => 32, 'cover-ratings.images.large_width' => 64, 'cover-ratings.images.max_pixels' => 1000000]);
});
test('url guard accepts only https urls from an exact configured origin', function (): void {
    app(CoverImageUrlGuard::class)->assertAllowed('https://wiki.example.test/images/a/ab/Cover.jpg?width=720');
    $this->addToAssertionCount(1);
});
test('url guard rejects unsafe or untrusted urls', function (string $url): void {
    expect(fn () => app(CoverImageUrlGuard::class)->assertAllowed($url))->toThrow(CoverImageException::class);
})->with('CoverImageSecurityTest.unsafeUrlProvider');
test('downloader accepts an allowed image and sets a bounded user agent', function (): void {
    config(['cover-ratings.images.max_bytes' => 100, 'maddraxikon.http.user_agent' => 'OMXFC-Cover-Test/1.0']);
    Http::fake(['https://wiki.example.test/*' => Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg; charset=binary', 'Content-Length' => '11'])]);
    $download = app(CoverImageDownloader::class)->download('https://wiki.example.test/images/cover.jpg');
    expect($download['body'])->toBe('image-bytes')
        ->and($download['mime_type'])->toBe('image/jpeg');
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('User-Agent', 'OMXFC-Cover-Test/1.0'));
});
test('downloader rejects redirects oversized empty and wrong type responses', function (int $status, string $body, array $headers): void {
    Http::fake(['https://wiki.example.test/*' => Http::response($body, $status, $headers)]);
    expect(fn () => app(CoverImageDownloader::class)->download('https://wiki.example.test/images/cover.jpg'))->toThrow(CoverImageException::class);
})->with('CoverImageSecurityTest.invalidDownloadProvider');
test('processor decodes limits and atomically stores both webp variants', function (): void {
    Storage::fake('private');
    config(['cover-ratings.images.max_bytes' => 15 * 1024 * 1024]);
    $book = Book::factory()->create(['type' => BookType::MaddraxDieDunkleZukunftDerErde]);
    $binary = file_get_contents(public_path('images/brina-rating.webp'));
    expect($binary)->toBeString();
    $processed = app(CoverImageProcessor::class)->process($book, $binary, str_repeat('a', 40));
    $secondPass = app(CoverImageProcessor::class)->process($book, $binary, str_repeat('a', 40));
    expect($processed['mime_type'])->toBe('image/webp')
        ->and($processed['width'])->toBe(128)
        ->and($processed['height'])->toBe(128);
    Storage::disk('private')->assertExists($processed['small_path']);
    Storage::disk('private')->assertExists($processed['large_path']);
    expect(getimagesizefromstring(Storage::disk('private')->get($processed['small_path']))[0])->toBe(32)
        ->and(getimagesizefromstring(Storage::disk('private')->get($processed['large_path']))[0])->toBe(64)
        ->and($processed['small_path'])->toEndWith('-small-32.webp')
        ->and($processed['large_path'])->toEndWith('-large-64.webp')
        ->and($secondPass['small_path'])->not->toBe($processed['small_path'])
        ->and($secondPass['large_path'])->not->toBe($processed['large_path'])
        ->and(Storage::disk('private')->allFiles())->toHaveCount(4);
    config(['cover-ratings.images.small_width' => 32, 'cover-ratings.images.large_width' => 32]);
    $equalWidthVariants = app(CoverImageProcessor::class)->process($book, $binary, str_repeat('b', 40));
    expect($equalWidthVariants['large_path'])->not->toBe($equalWidthVariants['small_path'])
        ->and($equalWidthVariants['small_path'])->toEndWith('-small-32.webp')
        ->and($equalWidthVariants['large_path'])->toEndWith('-large-32.webp');
    Storage::disk('private')->assertExists($equalWidthVariants['small_path']);
    Storage::disk('private')->assertExists($equalWidthVariants['large_path']);
    config(['cover-ratings.images.max_pixels' => 100]);
    expect(fn () => app(CoverImageProcessor::class)->process($book, $binary, str_repeat('c', 40)))->toThrow(CoverImageException::class);
});
test('processor rejects oversized declared dimensions before decoding', function (): void {
    Storage::fake('private');
    config(['cover-ratings.images.max_pixels' => 1000000]);
    $book = Book::factory()->create();
    $oversizedPngHeader = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.pack('NNCCCCC', 50000, 50000, 8, 2, 0, 0, 0).pack('N', 0);
    try {
        app(CoverImageProcessor::class)->process($book, $oversizedPngHeader, str_repeat('d', 40));
        $this->fail('An image with oversized declared dimensions was accepted.');
    } catch (CoverImageException $exception) {
        expect($exception->getMessage())->toBe('Das Coverbild besitzt unzulässige Abmessungen.')
            ->and(Storage::disk('private')->allFiles())->toBe([]);
    }
});
test('processor preserves transparency in each independent variant', function (): void {
    Storage::fake('private');
    config(['cover-ratings.images.max_bytes' => 100000]);
    $source = imagecreatetruecolor(128, 64);
    imagealphablending($source, false);
    imagesavealpha($source, true);
    imagefill($source, 0, 0, imagecolorallocatealpha($source, 0, 0, 0, 127));
    imagefilledrectangle($source, 32, 16, 96, 48, imagecolorallocatealpha($source, 255, 0, 0, 0));
    ob_start();
    imagepng($source);
    $binary = ob_get_clean();
    $processed = app(CoverImageProcessor::class)->process(Book::factory()->create(), $binary, str_repeat('e', 40));
    foreach (['small_path' => 32, 'large_path' => 64] as $key => $width) {
        $variant = imagecreatefromstring(Storage::disk('private')->get($processed[$key]));
        expect(imagesx($variant))->toBe($width)
            ->and(imagecolorsforindex($variant, imagecolorat($variant, 0, 0))['alpha'])->toBe(127);
    }
});
test('processor applies exif orientation before scaling each variant', function (): void {
    Storage::fake('private');
    config(['cover-ratings.images.max_bytes' => 100000]);
    $source = imagecreatetruecolor(128, 64);
    ob_start();
    imagejpeg($source);
    $jpeg = ob_get_clean();
    // Little-endian TIFF with one orientation entry (6 = rotate 90 degrees).
    $exif = "Exif\x00\x00II".pack('vV', 42, 8).pack('v', 1).pack('vvVv', 0x112, 3, 1, 6)."\x00\x00".pack('V', 0);
    $binary = "\xff\xd8\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
    $processed = app(CoverImageProcessor::class)->process(Book::factory()->create(), $binary, str_repeat('f', 40));
    expect($processed['width'])->toBe(64)
        ->and($processed['height'])->toBe(128)
        ->and(array_slice(getimagesizefromstring(Storage::disk('private')->get($processed['small_path'])), 0, 2))->toBe([32, 64])
        ->and(array_slice(getimagesizefromstring(Storage::disk('private')->get($processed['large_path'])), 0, 2))->toBe([64, 128]);
});
test('processor rejects undecodable content without leaving files', function (): void {
    Storage::fake('private');
    $book = Book::factory()->create();
    try {
        app(CoverImageProcessor::class)->process($book, 'not-an-image', 'unsafe');
        $this->fail('Undecodable content was accepted.');
    } catch (CoverImageException) {
        expect(Storage::disk('private')->allFiles())->toBe([]);
    }
});
