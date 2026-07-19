<?php

namespace Tests\Unit;

use App\Services\NewsletterImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsletterImageServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_upload_images_stores_files_on_public_disk(): void
    {
        Storage::fake('public');

        $service = app(NewsletterImageService::class);

        $paths = $service->uploadImages([
            UploadedFile::fake()->image('erste-datei.jpg', 2400, 1200),
            UploadedFile::fake()->image('zweite-datei.png', 640, 480),
        ]);

        $this->assertCount(2, $paths);

        foreach ($paths as $path) {
            $this->assertTrue(Storage::disk('public')->exists($path));
            $this->assertStringStartsWith(NewsletterImageService::STORAGE_PATH.'/', $path);
            $this->assertTrue(Storage::disk('local')->exists(
                NewsletterImageService::ORIGINAL_STORAGE_PATH.'/'.pathinfo($path, PATHINFO_BASENAME)
            ));
        }

        $this->assertSame(
            [1920, 960],
            array_slice(getimagesize(Storage::disk('public')->path($paths[0])), 0, 2),
        );
    }

    public function test_content_mime_wins_over_a_misleading_extension(): void
    {
        Storage::fake('public');

        $png = UploadedFile::fake()->image('source.png', 20, 10);
        $misnamed = UploadedFile::fake()->createWithContent('photo.jpg', $png->getContent());

        $path = app(NewsletterImageService::class)->uploadImages([$misnamed])[0];

        $this->assertStringEndsWith('.png', $path);
        $this->assertSame('image/png', Storage::disk('public')->mimeType($path));
    }

    public function test_corrupt_upload_rolls_back_earlier_files_and_private_originals(): void
    {
        Storage::fake('public');

        try {
            app(NewsletterImageService::class)->uploadImages([
                UploadedFile::fake()->image('valid.jpg', 30, 20),
                UploadedFile::fake()->createWithContent('broken.jpg', '<?php echo "payload";'),
            ]);
            $this->fail('A corrupt image must abort the complete upload batch.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Newsletter-Bild konnte nicht hochgeladen werden.', $exception->getMessage());
        }

        $this->assertSame([], Storage::disk('public')->allFiles(NewsletterImageService::STORAGE_PATH));
        $this->assertSame([], Storage::disk('local')->allFiles(NewsletterImageService::ORIGINAL_STORAGE_PATH));
    }

    public function test_rejects_excessive_pixel_dimensions_before_decoding(): void
    {
        Storage::fake('public');

        $oversized = UploadedFile::fake()->createWithContent(
            'oversized.png',
            $this->pngWithDimensions(10_000, 3_000),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Newsletter-Bild konnte nicht hochgeladen werden.');

        app(NewsletterImageService::class)->uploadImages([$oversized]);
    }

    public function test_rejects_files_above_the_upload_size_limit(): void
    {
        Storage::fake('public');

        $oversized = UploadedFile::fake()
            ->image('too-large.jpg', 20, 20)
            ->size(NewsletterImageService::MAX_FILE_SIZE_KB + 1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Newsletter-Bild konnte nicht hochgeladen werden.');

        app(NewsletterImageService::class)->uploadImages([$oversized]);
    }

    public function test_reencoding_strips_an_appended_polyglot_payload(): void
    {
        Storage::fake('public');

        $jpeg = UploadedFile::fake()->image('source.jpg', 30, 20);
        $payload = '<?php echo "unexpected";';
        $polyglot = UploadedFile::fake()->createWithContent('polyglot.jpg', $jpeg->getContent().$payload);

        $path = app(NewsletterImageService::class)->uploadImages([$polyglot])[0];
        $stored = Storage::disk('public')->get($path);

        $this->assertStringNotContainsString($payload, $stored);
        $this->assertNotFalse(getimagesizefromstring($stored));
    }

    public function test_png_transparency_is_preserved(): void
    {
        Storage::fake('public');

        $upload = UploadedFile::fake()->createWithContent('transparent.png', $this->transparentPng());
        $path = app(NewsletterImageService::class)->uploadImages([$upload])[0];
        $resource = imagecreatefromstring(Storage::disk('public')->get($path));

        $this->assertNotFalse($resource);
        $rgba = imagecolorsforindex($resource, imagecolorat($resource, 0, 0));
        $this->assertSame(127, $rgba['alpha']);

        imagedestroy($resource);
    }

    public function test_exif_orientation_is_applied_before_storage(): void
    {
        Storage::fake('public');

        $upload = UploadedFile::fake()->createWithContent('oriented.jpg', $this->orientedJpeg());
        $path = app(NewsletterImageService::class)->uploadImages([$upload])[0];

        $this->assertSame(
            [20, 40],
            array_slice(getimagesize(Storage::disk('public')->path($path)), 0, 2),
        );
    }

    public function test_deleting_a_normalized_image_also_deletes_its_private_original(): void
    {
        Storage::fake('public');

        $service = app(NewsletterImageService::class);
        $path = $service->uploadImages([UploadedFile::fake()->image('delete-me.webp', 40, 20)])[0];
        $originalPath = NewsletterImageService::ORIGINAL_STORAGE_PATH.'/'.pathinfo($path, PATHINFO_BASENAME);

        $service->deleteImage($path);

        $this->assertFalse(Storage::disk('public')->exists($path));
        $this->assertFalse(Storage::disk('local')->exists($originalPath));
    }

    public function test_sync_images_merges_existing_and_new_images(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('newsletter-images/alt-bleibt.jpg', 'keep');
        Storage::disk('public')->put('newsletter-images/alt-weg.jpg', 'remove');

        $service = app(NewsletterImageService::class);

        $result = $service->syncImages(
            ['newsletter-images/alt-bleibt.jpg', 'newsletter-images/alt-weg.jpg'],
            ['newsletter-images/alt-weg.jpg'],
            [UploadedFile::fake()->image('neu.webp', 500, 500)],
        );

        $this->assertSame(['newsletter-images/alt-weg.jpg'], $result['deleted']);
        $this->assertCount(1, $result['uploaded']);
        $this->assertCount(2, $result['images']);
        $this->assertContains('newsletter-images/alt-bleibt.jpg', $result['images']);
        $this->assertContains($result['uploaded'][0], $result['images']);
        $this->assertTrue(Storage::disk('public')->exists($result['uploaded'][0]));
        $this->assertStringEndsWith('.webp', $result['uploaded'][0]);
        $this->assertSame('image/webp', Storage::disk('public')->mimeType($result['uploaded'][0]));
    }

    public function test_delete_images_removes_files_from_storage(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('newsletter-images/loeschen-1.jpg', 'one');
        Storage::disk('public')->put('newsletter-images/loeschen-2.jpg', 'two');

        $service = app(NewsletterImageService::class);
        $service->deleteImages([
            'newsletter-images/loeschen-1.jpg',
            'newsletter-images/loeschen-2.jpg',
        ]);

        $this->assertFalse(Storage::disk('public')->exists('newsletter-images/loeschen-1.jpg'));
        $this->assertFalse(Storage::disk('public')->exists('newsletter-images/loeschen-2.jpg'));
    }

    public function test_delete_image_ignores_paths_outside_newsletter_directory(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('anderes-feature/fremd.jpg', 'fremd');

        $service = app(NewsletterImageService::class);
        $service->deleteImage('anderes-feature/fremd.jpg');
        $service->deleteImage('newsletter-images/../anderes-feature/fremd.jpg');

        $this->assertTrue(Storage::disk('public')->exists('anderes-feature/fremd.jpg'));
    }

    public function test_sync_images_ignores_unmanaged_existing_paths(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('anderes-feature/fremd.jpg', 'fremd');

        $service = app(NewsletterImageService::class);

        $result = $service->syncImages(
            ['anderes-feature/fremd.jpg', 'newsletter-images/../anderes-feature/fremd.jpg'],
            ['anderes-feature/fremd.jpg', 'newsletter-images/../anderes-feature/fremd.jpg'],
            [],
        );

        $this->assertSame([], $result['deleted']);
        $this->assertSame([], $result['uploaded']);
        $this->assertSame([], $result['images']);
        $this->assertTrue(Storage::disk('public')->exists('anderes-feature/fremd.jpg'));
    }

    private function pngWithDimensions(int $width, int $height): string
    {
        $header = pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n"
            .$this->pngChunk('IHDR', $header)
            .$this->pngChunk('IEND', '');
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data))
            .$type
            .$data
            .pack('N', crc32($type.$data));
    }

    private function transparentPng(): string
    {
        $image = imagecreatetruecolor(2, 2);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        ob_start();
        imagepng($image);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        return $contents;
    }

    private function orientedJpeg(): string
    {
        $image = imagecreatetruecolor(40, 20);
        $color = imagecolorallocate($image, 30, 120, 210);
        imagefill($image, 0, 0, $color);

        ob_start();
        imagejpeg($image, null, 90);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);

        $tiff = 'II'
            .pack('v', 42)
            .pack('V', 8)
            .pack('v', 1)
            .pack('v', 0x0112)
            .pack('v', 3)
            .pack('V', 1)
            .pack('v', 6)."\0\0"
            .pack('V', 0);
        $exif = "Exif\0\0".$tiff;
        $app1 = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;

        return substr($jpeg, 0, 2).$app1.substr($jpeg, 2);
    }
}
