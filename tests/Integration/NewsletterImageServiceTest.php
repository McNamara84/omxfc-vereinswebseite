<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\NewsletterImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(NewsletterImageServiceTestFixtures::class);

beforeEach(function (): void {
    Storage::fake('local');
});

test('upload images stores files on public disk', function (): void {
    Storage::fake('public');
    $service = app(NewsletterImageService::class);
    $paths = $service->uploadImages([UploadedFile::fake()->image('erste-datei.jpg', 2400, 1200), UploadedFile::fake()->image('zweite-datei.png', 640, 480)]);
    expect($paths)->toHaveCount(2);
    foreach ($paths as $path) {
        expect(Storage::disk('public')->exists($path))->toBeTrue()
            ->and($path)->toStartWith(NewsletterImageService::STORAGE_PATH.'/')
            ->and(Storage::disk('local')->exists(NewsletterImageService::ORIGINAL_STORAGE_PATH.'/'.pathinfo($path, PATHINFO_BASENAME)))->toBeTrue();
    }
    expect(array_slice(getimagesize(Storage::disk('public')->path($paths[0])), 0, 2))->toBe([1920, 960]);
});

test('content mime wins over a misleading extension', function (): void {
    Storage::fake('public');
    $png = UploadedFile::fake()->image('source.png', 20, 10);
    $misnamed = UploadedFile::fake()->createWithContent('photo.jpg', $png->getContent());
    $path = app(NewsletterImageService::class)->uploadImages([$misnamed])[0];
    expect($path)->toEndWith('.png')
        ->and(Storage::disk('public')->mimeType($path))->toBe('image/png');
});

test('corrupt upload rolls back earlier files and private originals', function (): void {
    Storage::fake('public');
    try {
        app(NewsletterImageService::class)->uploadImages([UploadedFile::fake()->image('valid.jpg', 30, 20), UploadedFile::fake()->createWithContent('broken.jpg', '<?php echo "payload";')]);
        $this->fail('A corrupt image must abort the complete upload batch.');
    } catch (\RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Newsletter-Bild konnte nicht hochgeladen werden.');
    }
    expect(Storage::disk('public')->allFiles(NewsletterImageService::STORAGE_PATH))->toBe([])
        ->and(Storage::disk('local')->allFiles(NewsletterImageService::ORIGINAL_STORAGE_PATH))->toBe([]);
});

test('rejects excessive pixel dimensions before decoding', function (): void {
    Storage::fake('public');
    $oversized = UploadedFile::fake()->createWithContent('oversized.png', $this->pngWithDimensions(10000, 3000));
    expect(fn () => app(NewsletterImageService::class)->uploadImages([$oversized]))->toThrow(\RuntimeException::class, 'Newsletter-Bild konnte nicht hochgeladen werden.');
});

test('rejects files above the upload size limit', function (): void {
    Storage::fake('public');
    $oversized = UploadedFile::fake()->image('too-large.jpg', 20, 20)->size(NewsletterImageService::MAX_FILE_SIZE_KB + 1);
    expect(fn () => app(NewsletterImageService::class)->uploadImages([$oversized]))->toThrow(\RuntimeException::class, 'Newsletter-Bild konnte nicht hochgeladen werden.');
});

test('reencoding strips an appended polyglot payload', function (): void {
    Storage::fake('public');
    $jpeg = UploadedFile::fake()->image('source.jpg', 30, 20);
    $payload = '<?php echo "unexpected";';
    $polyglot = UploadedFile::fake()->createWithContent('polyglot.jpg', $jpeg->getContent().$payload);
    $path = app(NewsletterImageService::class)->uploadImages([$polyglot])[0];
    $stored = Storage::disk('public')->get($path);
    expect($stored)->not->toContain($payload)
        ->and(getimagesizefromstring($stored))->not->toBeFalse();
});

test('png transparency is preserved', function (): void {
    Storage::fake('public');
    $upload = UploadedFile::fake()->createWithContent('transparent.png', $this->transparentPng());
    $path = app(NewsletterImageService::class)->uploadImages([$upload])[0];
    $resource = imagecreatefromstring(Storage::disk('public')->get($path));
    expect($resource)->not->toBeFalse();
    $rgba = imagecolorsforindex($resource, imagecolorat($resource, 0, 0));
    expect($rgba['alpha'])->toBe(127);
});

test('exif orientation is applied before storage', function (): void {
    Storage::fake('public');
    $upload = UploadedFile::fake()->createWithContent('oriented.jpg', $this->orientedJpeg());
    $path = app(NewsletterImageService::class)->uploadImages([$upload])[0];
    expect(array_slice(getimagesize(Storage::disk('public')->path($path)), 0, 2))->toBe([20, 40]);
});

test('deleting a normalized image also deletes its private original', function (): void {
    Storage::fake('public');
    $service = app(NewsletterImageService::class);
    $path = $service->uploadImages([UploadedFile::fake()->image('delete-me.webp', 40, 20)])[0];
    $originalPath = NewsletterImageService::ORIGINAL_STORAGE_PATH.'/'.pathinfo($path, PATHINFO_BASENAME);
    $service->deleteImage($path);
    expect(Storage::disk('public')->exists($path))->toBeFalse()
        ->and(Storage::disk('local')->exists($originalPath))->toBeFalse();
});

test('sync images merges existing and new images', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('newsletter-images/alt-bleibt.jpg', 'keep');
    Storage::disk('public')->put('newsletter-images/alt-weg.jpg', 'remove');
    $service = app(NewsletterImageService::class);
    $result = $service->syncImages(['newsletter-images/alt-bleibt.jpg', 'newsletter-images/alt-weg.jpg'], ['newsletter-images/alt-weg.jpg'], [UploadedFile::fake()->image('neu.webp', 500, 500)]);
    expect($result['deleted'])->toBe(['newsletter-images/alt-weg.jpg'])
        ->and($result['uploaded'])->toHaveCount(1)
        ->and($result['images'])->toHaveCount(2)
        ->toContain('newsletter-images/alt-bleibt.jpg')
        ->toContain($result['uploaded'][0])
        ->and(Storage::disk('public')->exists($result['uploaded'][0]))->toBeTrue()
        ->and($result['uploaded'][0])->toEndWith('.webp')
        ->and(Storage::disk('public')->mimeType($result['uploaded'][0]))->toBe('image/webp');
});

test('delete images removes files from storage', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('newsletter-images/loeschen-1.jpg', 'one');
    Storage::disk('public')->put('newsletter-images/loeschen-2.jpg', 'two');
    $service = app(NewsletterImageService::class);
    $service->deleteImages(['newsletter-images/loeschen-1.jpg', 'newsletter-images/loeschen-2.jpg']);
    expect(Storage::disk('public')->exists('newsletter-images/loeschen-1.jpg'))->toBeFalse()
        ->and(Storage::disk('public')->exists('newsletter-images/loeschen-2.jpg'))->toBeFalse();
});

test('delete image ignores paths outside newsletter directory', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('anderes-feature/fremd.jpg', 'fremd');
    $service = app(NewsletterImageService::class);
    $service->deleteImage('anderes-feature/fremd.jpg');
    $service->deleteImage('newsletter-images/../anderes-feature/fremd.jpg');
    expect(Storage::disk('public')->exists('anderes-feature/fremd.jpg'))->toBeTrue();
});

test('sync images ignores unmanaged existing paths', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('anderes-feature/fremd.jpg', 'fremd');
    $service = app(NewsletterImageService::class);
    $result = $service->syncImages(['anderes-feature/fremd.jpg', 'newsletter-images/../anderes-feature/fremd.jpg'], ['anderes-feature/fremd.jpg', 'newsletter-images/../anderes-feature/fremd.jpg'], []);
    expect($result['deleted'])->toBe([])
        ->and($result['uploaded'])->toBe([])
        ->and($result['images'])->toBe([])
        ->and(Storage::disk('public')->exists('anderes-feature/fremd.jpg'))->toBeTrue();
});
