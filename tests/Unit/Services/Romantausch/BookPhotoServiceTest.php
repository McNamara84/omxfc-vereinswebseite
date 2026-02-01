<?php

namespace Tests\Unit\Services\Romantausch;

use App\Services\Romantausch\BookPhotoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookPhotoServiceTest extends TestCase
{
    use RefreshDatabase;

    private BookPhotoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BookPhotoService();
    }

    public function test_upload_photos_stores_files(): void
    {
        Storage::fake('public');

        $files = [
            UploadedFile::fake()->image('test1.jpg'),
            UploadedFile::fake()->image('test2.png'),
        ];

        $paths = $this->service->uploadPhotos($files);

        $this->assertCount(2, $paths);

        foreach ($paths as $path) {
            Storage::disk('public')->assertExists($path);
            $this->assertStringStartsWith(BookPhotoService::STORAGE_PATH.'/', $path);
        }
    }

    public function test_upload_photos_rejects_invalid_extensions(): void
    {
        Storage::fake('public');

        $files = [
            UploadedFile::fake()->create('test.txt', 10, 'text/plain'),
        ];

        $paths = $this->service->uploadPhotos($files);

        // Ungültige Dateien werden übersprungen
        $this->assertCount(0, $paths);
    }

    public function test_upload_photos_accepts_valid_extensions(): void
    {
        Storage::fake('public');

        foreach (BookPhotoService::ALLOWED_EXTENSIONS as $ext) {
            $files = [UploadedFile::fake()->image("test.$ext")];
            $paths = $this->service->uploadPhotos($files);

            $this->assertCount(1, $paths, "Extension '$ext' should be accepted");
            Storage::disk('public')->assertExists($paths[0]);
        }
    }

    public function test_delete_photo_removes_file(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('test.jpg');
        $paths = $this->service->uploadPhotos([$file]);
        $path = $paths[0];

        Storage::disk('public')->assertExists($path);

        $this->service->deletePhoto($path);

        Storage::disk('public')->assertMissing($path);
    }

    public function test_delete_photos_removes_multiple_files(): void
    {
        Storage::fake('public');

        $files = [
            UploadedFile::fake()->image('test1.jpg'),
            UploadedFile::fake()->image('test2.jpg'),
        ];

        $paths = $this->service->uploadPhotos($files);

        foreach ($paths as $path) {
            Storage::disk('public')->assertExists($path);
        }

        $this->service->deletePhotos($paths);

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    public function test_constants_have_expected_values(): void
    {
        $this->assertEquals(3, BookPhotoService::MAX_PHOTOS);
        $this->assertEquals(2048, BookPhotoService::MAX_FILE_SIZE_KB);
        $this->assertEquals('book-offers', BookPhotoService::STORAGE_PATH);
        $this->assertContains('jpg', BookPhotoService::ALLOWED_EXTENSIONS);
        $this->assertContains('png', BookPhotoService::ALLOWED_EXTENSIONS);
        $this->assertContains('webp', BookPhotoService::ALLOWED_EXTENSIONS);
    }
}
