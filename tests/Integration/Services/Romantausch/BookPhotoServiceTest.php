<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Romantausch;

use App\Services\Romantausch\BookPhotoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(BookPhotoServiceTestFixtures::class);

beforeEach(function (): void {
    $this->service = new BookPhotoService;
});

test('second upload failure cleans up the first file', function (): void {
    Storage::fake('public');
    $image = UploadedFile::fake()->image('second.jpg');
    $failed = new class($image->getPathname(), 'second.jpg', null, null, true) extends UploadedFile
    {
        public function storeAs($path, $name = null, $options = [])
        {
            throw new RuntimeException('disk unavailable');
        }
    };
    try {
        $this->service->uploadPhotos([UploadedFile::fake()->image('first.jpg'), $failed]);
        $this->fail('The failed upload must not succeed.');
    } catch (RuntimeException $error) {
        expect($error->getMessage())->toBe('Foto-Upload fehlgeschlagen.')
            ->and(Storage::disk('public')->allFiles('book-offers'))->toBe([]);
    }
});

test('missing and corrupted image files are not stored', function (): void {
    Storage::fake('public');
    $missing = UploadedFile::fake()->image('missing.jpg');
    unlink($missing->getRealPath());
    $corrupt = UploadedFile::fake()->create('broken.jpg', 1, 'image/jpeg');
    expect($this->service->uploadPhotos([null, $missing, $corrupt]))->toBe([])
        ->and(Storage::disk('public')->allFiles('book-offers'))->toBe([]);
});

test('upload photos stores files', function (): void {
    Storage::fake('public');
    $files = [UploadedFile::fake()->image('test1.jpg'), UploadedFile::fake()->image('test2.png')];
    $paths = $this->service->uploadPhotos($files);
    expect($paths)->toHaveCount(2);
    foreach ($paths as $path) {
        Storage::disk('public')->assertExists($path);
        expect($path)->toStartWith(BookPhotoService::STORAGE_PATH.'/');
    }
});

test('upload photos rejects invalid extensions', function (): void {
    Storage::fake('public');
    $files = [UploadedFile::fake()->create('test.txt', 10, 'text/plain')];
    $paths = $this->service->uploadPhotos($files);
    // Ungültige Dateien werden übersprungen
    expect($paths)->toHaveCount(0);
});

test('upload photos accepts valid extensions', function (): void {
    Storage::fake('public');
    foreach (BookPhotoService::ALLOWED_EXTENSIONS as $ext) {
        $files = [UploadedFile::fake()->image("test.{$ext}")];
        $paths = $this->service->uploadPhotos($files);
        expect($paths)->toHaveCount(1);
        Storage::disk('public')->assertExists($paths[0]);
    }
});

test('delete photo removes file', function (): void {
    Storage::fake('public');
    $file = UploadedFile::fake()->image('test.jpg');
    $paths = $this->service->uploadPhotos([$file]);
    $path = $paths[0];
    Storage::disk('public')->assertExists($path);
    $this->service->deletePhoto($path);
    Storage::disk('public')->assertMissing($path);
});

test('delete photos removes multiple files', function (): void {
    Storage::fake('public');
    $files = [UploadedFile::fake()->image('test1.jpg'), UploadedFile::fake()->image('test2.jpg')];
    $paths = $this->service->uploadPhotos($files);
    foreach ($paths as $path) {
        Storage::disk('public')->assertExists($path);
    }
    $this->service->deletePhotos($paths);
    foreach ($paths as $path) {
        Storage::disk('public')->assertMissing($path);
    }
});

test('constants have expected values', function (): void {
    expect(BookPhotoService::MAX_PHOTOS)->toEqual(3)
        ->and(BookPhotoService::MAX_FILE_SIZE_KB)->toEqual(2048)
        ->and(BookPhotoService::STORAGE_PATH)->toEqual('book-offers')
        ->and(BookPhotoService::ALLOWED_EXTENSIONS)->toContain('jpg')
        ->toContain('png')
        ->toContain('webp');
});
