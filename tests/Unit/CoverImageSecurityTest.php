<?php

namespace Tests\Unit;

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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CoverImageSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cover-ratings.images.allowed_origins' => ['https://wiki.example.test'],
            'cover-ratings.images.allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
            'cover-ratings.images.max_bytes' => 16,
            'cover-ratings.images.disk' => 'private',
            'cover-ratings.images.directory' => 'cover-ratings',
            'cover-ratings.images.small_width' => 32,
            'cover-ratings.images.large_width' => 64,
            'cover-ratings.images.max_pixels' => 1_000_000,
        ]);
    }

    public function test_url_guard_accepts_only_https_urls_from_an_exact_configured_origin(): void
    {
        app(CoverImageUrlGuard::class)->assertAllowed(
            'https://wiki.example.test/images/a/ab/Cover.jpg?width=720',
        );

        $this->addToAssertionCount(1);
    }

    #[DataProvider('unsafeUrlProvider')]
    public function test_url_guard_rejects_unsafe_or_untrusted_urls(string $url): void
    {
        $this->expectException(CoverImageException::class);

        app(CoverImageUrlGuard::class)->assertAllowed($url);
    }

    public static function unsafeUrlProvider(): array
    {
        return [
            'plain HTTP' => ['http://wiki.example.test/cover.jpg'],
            'different host' => ['https://evil.example.test/cover.jpg'],
            'host suffix trick' => ['https://wiki.example.test.evil.test/cover.jpg'],
            'credentials' => ['https://user:secret@wiki.example.test/cover.jpg'],
            'different port' => ['https://wiki.example.test:8443/cover.jpg'],
            'fragment' => ['https://wiki.example.test/cover.jpg#fragment'],
            'relative URL' => ['/cover.jpg'],
        ];
    }

    public function test_downloader_accepts_an_allowed_image_and_sets_a_bounded_user_agent(): void
    {
        config([
            'cover-ratings.images.max_bytes' => 100,
            'maddraxikon.http.user_agent' => 'OMXFC-Cover-Test/1.0',
        ]);
        Http::fake([
            'https://wiki.example.test/*' => Http::response('image-bytes', 200, [
                'Content-Type' => 'image/jpeg; charset=binary',
                'Content-Length' => '11',
            ]),
        ]);

        $download = app(CoverImageDownloader::class)
            ->download('https://wiki.example.test/images/cover.jpg');

        $this->assertSame('image-bytes', $download['body']);
        $this->assertSame('image/jpeg', $download['mime_type']);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader(
            'User-Agent',
            'OMXFC-Cover-Test/1.0',
        ));
    }

    #[DataProvider('invalidDownloadProvider')]
    public function test_downloader_rejects_redirects_oversized_empty_and_wrong_type_responses(
        int $status,
        string $body,
        array $headers,
    ): void {
        Http::fake([
            'https://wiki.example.test/*' => Http::response($body, $status, $headers),
        ]);

        $this->expectException(CoverImageException::class);

        app(CoverImageDownloader::class)
            ->download('https://wiki.example.test/images/cover.jpg');
    }

    public static function invalidDownloadProvider(): array
    {
        return [
            'redirect' => [302, 'moved', ['Content-Type' => 'image/jpeg', 'Location' => 'https://evil.test/x']],
            'declared oversize' => [200, 'tiny', ['Content-Type' => 'image/jpeg', 'Content-Length' => '17']],
            'actual oversize' => [200, '12345678901234567', ['Content-Type' => 'image/jpeg']],
            'empty' => [200, '', ['Content-Type' => 'image/jpeg']],
            'wrong MIME' => [200, '<svg/>', ['Content-Type' => 'image/svg+xml']],
        ];
    }

    public function test_processor_decodes_limits_and_atomically_stores_both_webp_variants(): void
    {
        Storage::fake('private');
        config(['cover-ratings.images.max_bytes' => 15 * 1024 * 1024]);
        $book = Book::factory()->create([
            'type' => BookType::MaddraxDieDunkleZukunftDerErde,
        ]);
        $binary = file_get_contents(public_path('images/brina-rating.webp'));
        $this->assertIsString($binary);

        $processed = app(CoverImageProcessor::class)
            ->process($book, $binary, str_repeat('a', 40));
        $secondPass = app(CoverImageProcessor::class)
            ->process($book, $binary, str_repeat('a', 40));

        $this->assertSame('image/webp', $processed['mime_type']);
        $this->assertSame(128, $processed['width']);
        $this->assertSame(128, $processed['height']);
        Storage::disk('private')->assertExists($processed['small_path']);
        Storage::disk('private')->assertExists($processed['large_path']);
        $this->assertStringEndsWith('-small-32.webp', $processed['small_path']);
        $this->assertStringEndsWith('-large-64.webp', $processed['large_path']);
        $this->assertNotSame($processed['small_path'], $secondPass['small_path']);
        $this->assertNotSame($processed['large_path'], $secondPass['large_path']);
        $this->assertCount(4, Storage::disk('private')->allFiles());

        config([
            'cover-ratings.images.small_width' => 32,
            'cover-ratings.images.large_width' => 32,
        ]);
        $equalWidthVariants = app(CoverImageProcessor::class)
            ->process($book, $binary, str_repeat('b', 40));

        $this->assertNotSame($equalWidthVariants['small_path'], $equalWidthVariants['large_path']);
        $this->assertStringEndsWith('-small-32.webp', $equalWidthVariants['small_path']);
        $this->assertStringEndsWith('-large-32.webp', $equalWidthVariants['large_path']);
        Storage::disk('private')->assertExists($equalWidthVariants['small_path']);
        Storage::disk('private')->assertExists($equalWidthVariants['large_path']);

        config(['cover-ratings.images.max_pixels' => 100]);
        $this->expectException(CoverImageException::class);
        app(CoverImageProcessor::class)
            ->process($book, $binary, str_repeat('c', 40));
    }

    public function test_processor_rejects_oversized_declared_dimensions_before_decoding(): void
    {
        Storage::fake('private');
        config(['cover-ratings.images.max_pixels' => 1_000_000]);
        $book = Book::factory()->create();
        $oversizedPngHeader = "\x89PNG\r\n\x1a\n"
            .pack('N', 13)
            .'IHDR'
            .pack('NNCCCCC', 50_000, 50_000, 8, 2, 0, 0, 0)
            .pack('N', 0);

        try {
            app(CoverImageProcessor::class)->process(
                $book,
                $oversizedPngHeader,
                str_repeat('d', 40),
            );
            $this->fail('An image with oversized declared dimensions was accepted.');
        } catch (CoverImageException $exception) {
            $this->assertSame(
                'Das Coverbild besitzt unzulässige Abmessungen.',
                $exception->getMessage(),
            );
            $this->assertSame([], Storage::disk('private')->allFiles());
        }
    }

    public function test_processor_rejects_undecodable_content_without_leaving_files(): void
    {
        Storage::fake('private');
        $book = Book::factory()->create();

        try {
            app(CoverImageProcessor::class)->process($book, 'not-an-image', 'unsafe');
            $this->fail('Undecodable content was accepted.');
        } catch (CoverImageException) {
            $this->assertSame([], Storage::disk('private')->allFiles());
        }
    }
}
