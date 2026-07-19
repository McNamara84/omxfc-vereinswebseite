<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NewsletterImageService
{
    public const STORAGE_PATH = 'newsletter-images';

    public const MAX_FILE_SIZE_KB = 2048;

    public const MAX_INPUT_PIXELS = 24_000_000;

    public const MAX_OUTPUT_WIDTH = 1920;

    public const MAX_OUTPUT_HEIGHT = 1920;

    public const ORIGINAL_STORAGE_PATH = 'newsletter-image-originals';

    private const OUTPUT_QUALITY = 85;

    private const MIME_TO_EXTENSION = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * @param  array<int, UploadedFile|null>  $images
     * @return array<int, string>
     */
    public function uploadImages(array $images): array
    {
        $paths = [];

        foreach ($images as $index => $image) {
            if (! $image instanceof UploadedFile) {
                continue;
            }

            try {
                $paths[] = $this->uploadSingleImage($image, $index + 1);
            } catch (\Throwable $exception) {
                $this->deleteImages($paths);

                Log::error('Newsletter-Bild konnte nicht hochgeladen werden.', [
                    'image_name' => $image->getClientOriginalName(),
                    'error' => $exception->getMessage(),
                ]);

                throw new \RuntimeException('Newsletter-Bild konnte nicht hochgeladen werden.', 0, $exception);
            }
        }

        return $paths;
    }

    /**
     * @param  array<int, string>  $existingImages
     * @param  array<int, string>  $removedImages
     * @param  array<int, UploadedFile|null>  $newImages
     * @return array{images: array<int, string>, deleted: array<int, string>, uploaded: array<int, string>}
     */
    public function syncImages(array $existingImages, array $removedImages, array $newImages): array
    {
        $existing = $this->sanitizePaths($existingImages);
        $deleted = array_values(array_intersect($existing, $this->sanitizePaths($removedImages)));
        $kept = array_values(array_diff($existing, $deleted));
        $uploaded = $this->uploadImages($newImages);

        return [
            'images' => array_values(array_merge($kept, $uploaded)),
            'deleted' => $deleted,
            'uploaded' => $uploaded,
        ];
    }

    /**
     * @param  array<int, string>  $paths
     */
    public function deleteImages(array $paths): void
    {
        foreach ($paths as $path) {
            if (! is_string($path)) {
                continue;
            }

            $this->deleteImage($path);
        }
    }

    public function deleteImage(string $path): void
    {
        $sanitizedPath = $this->sanitizePath($path);

        if ($sanitizedPath === null) {
            Log::warning('Newsletter-Bildpfad wurde verworfen.', [
                'path' => $path,
            ]);

            return;
        }

        $files = [
            ['disk' => 'public', 'path' => $sanitizedPath],
            ['disk' => 'local', 'path' => $this->originalPath($sanitizedPath)],
        ];

        foreach ($files as $file) {
            try {
                Storage::disk($file['disk'])->delete($file['path']);
            } catch (\Throwable $exception) {
                Log::warning('Newsletter-Bild konnte nicht gelöscht werden.', [
                    'disk' => $file['disk'],
                    'path' => $file['path'],
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function uploadSingleImage(UploadedFile $image, int $index): string
    {
        if ($image->getSize() > self::MAX_FILE_SIZE_KB * 1024) {
            throw new \RuntimeException('Bilddatei ist zu groß.');
        }

        $source = Image::fromUpload($image);
        $mimeType = $source->mimeType();

        if (! isset(self::MIME_TO_EXTENSION[$mimeType])) {
            throw new \RuntimeException('Nicht unterstützter Bildtyp.');
        }

        [$width, $height] = $source->dimensions();

        if ($width < 1 || $height < 1 || $width * $height > self::MAX_INPUT_PIXELS) {
            throw new \RuntimeException('Bildabmessungen überschreiten das sichere Limit.');
        }

        // Every accepted upload is decoded and re-encoded. This normalizes EXIF
        // orientation and strips appended payloads while preserving its format.
        $processed = $source
            ->orient()
            ->scale(self::MAX_OUTPUT_WIDTH, self::MAX_OUTPUT_HEIGHT)
            ->quality(self::OUTPUT_QUALITY);
        $outputMimeType = $processed->mimeType();
        $extension = self::MIME_TO_EXTENSION[$outputMimeType] ?? null;

        if ($extension === null) {
            throw new \RuntimeException('Nicht unterstütztes Ausgabeformat.');
        }

        $name = Str::slug(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME));

        if ($name === '') {
            $name = 'newsletter-image-'.$index;
        }

        $filename = $name.'-'.Str::uuid().'.'.$extension;
        $originalPath = self::ORIGINAL_STORAGE_PATH.'/'.$filename;

        if (! Storage::disk('local')->put($originalPath, $image->getContent())) {
            throw new \RuntimeException('Originalbild konnte nicht gesichert werden.');
        }

        try {
            $path = $processed->storePubliclyAs(self::STORAGE_PATH, $filename, 'public');

            if ($path === false) {
                throw new \RuntimeException('Normalisiertes Bild konnte nicht gespeichert werden.');
            }

            return $path;
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($originalPath);

            throw $exception;
        }
    }

    private function originalPath(string $path): string
    {
        return self::ORIGINAL_STORAGE_PATH.'/'.pathinfo($path, PATHINFO_BASENAME);
    }

    /**
     * @param  array<int, mixed>  $paths
     * @return array<int, string>
     */
    private function sanitizePaths(array $paths): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (mixed $path): ?string => $this->sanitizePath($path),
            $paths,
        ))));
    }

    private function sanitizePath(mixed $path): ?string
    {
        if (! is_string($path)) {
            return null;
        }

        $path = str_replace('\\', '/', trim($path));

        if ($path === '') {
            return null;
        }

        if (! str_starts_with($path, self::STORAGE_PATH.'/')) {
            return null;
        }

        if (preg_match('~(?:^|/)\.\.(?:/|$)~', $path) === 1) {
            return null;
        }

        return $path;
    }
}
