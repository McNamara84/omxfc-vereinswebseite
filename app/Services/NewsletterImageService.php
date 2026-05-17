<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NewsletterImageService
{
    public const STORAGE_PATH = 'newsletter-images';

    public const MAX_FILE_SIZE_KB = 2048;

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
        foreach ($this->sanitizePaths($paths) as $path) {
            $this->deleteImage($path);
        }
    }

    public function deleteImage(string $path): void
    {
        try {
            Storage::disk('public')->delete($path);
        } catch (\Throwable $exception) {
            Log::warning('Newsletter-Bild konnte nicht gelöscht werden.', [
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function uploadSingleImage(UploadedFile $image, int $index): string
    {
        $mimeType = $image->getMimeType();
        $extension = self::MIME_TO_EXTENSION[$mimeType] ?? null;

        if ($extension === null) {
            throw new \RuntimeException('Nicht unterstützter Bildtyp.');
        }

        if (getimagesize($image->getRealPath()) === false) {
            throw new \RuntimeException('Ungültige Bilddatei.');
        }

        $name = Str::slug(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME));

        if ($name === '') {
            $name = 'newsletter-image-'.$index;
        }

        return $image->storeAs(
            self::STORAGE_PATH,
            $name.'-'.Str::uuid().'.'.$extension,
            'public',
        );
    }

    /**
     * @param  array<int, mixed>  $paths
     * @return array<int, string>
     */
    private function sanitizePaths(array $paths): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $path): string => is_string($path) ? trim($path) : '',
            $paths,
        )));
    }
}