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

    public function test_upload_images_stores_files_on_public_disk(): void
    {
        Storage::fake('public');

        $service = app(NewsletterImageService::class);

        $paths = $service->uploadImages([
            UploadedFile::fake()->image('erste-datei.jpg', 800, 600),
            UploadedFile::fake()->image('zweite-datei.png', 640, 480),
        ]);

        $this->assertCount(2, $paths);

        foreach ($paths as $path) {
            $this->assertTrue(Storage::disk('public')->exists($path));
            $this->assertStringStartsWith(NewsletterImageService::STORAGE_PATH.'/', $path);
        }
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
}