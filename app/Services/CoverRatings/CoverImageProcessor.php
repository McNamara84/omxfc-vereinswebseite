<?php

namespace App\Services\CoverRatings;

use App\Exceptions\CoverImageException;
use App\Models\Book;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Throwable;

class CoverImageProcessor
{
    /**
     * @return array{small_path: string, large_path: string, width: int, height: int, mime_type: string}
     */
    public function process(Book $book, string $binary, string $fingerprint): array
    {
        $dimensions = @getimagesizefromstring($binary);
        $width = is_array($dimensions) ? (int) ($dimensions[0] ?? 0) : 0;
        $height = is_array($dimensions) ? (int) ($dimensions[1] ?? 0) : 0;
        $maximumPixels = max(
            1,
            (int) config('cover-ratings.images.max_pixels', 40_000_000),
        );

        if (
            $width < 1
            || $height < 1
            || $width > intdiv($maximumPixels, $height)
        ) {
            throw new CoverImageException('Das Coverbild besitzt unzulässige Abmessungen.');
        }

        $manager = new ImageManager(new Driver);
        $diskName = (string) config('cover-ratings.images.disk', 'private');
        $directory = trim((string) config('cover-ratings.images.directory', 'cover-ratings'), '/');
        $safeFingerprint = Str::lower(preg_replace('/[^a-f0-9]/i', '', $fingerprint) ?: sha1($binary));
        $quality = (int) config('cover-ratings.images.webp_quality', 82);
        $variants = [
            'small_path' => [
                'name' => 'small',
                'width' => (int) config('cover-ratings.images.small_width', 360),
            ],
            'large_path' => [
                'name' => 'large',
                'width' => (int) config('cover-ratings.images.large_width', 720),
            ],
        ];
        $variantToken = Str::lower((string) Str::uuid());
        $paths = [];
        $temporaryPaths = [];

        try {
            foreach ($variants as $key => $configuration) {
                try {
                    $variant = $manager->decodeBinary($binary);
                } catch (Throwable $exception) {
                    throw new CoverImageException(
                        'Das Coverbild konnte nicht sicher dekodiert werden.',
                        previous: $exception,
                    );
                }

                if ($variant->width() !== $width || $variant->height() !== $height) {
                    throw new CoverImageException('Das Coverbild besitzt widersprüchliche Abmessungen.');
                }

                $targetWidth = $configuration['width'];
                $variant->scaleDown(width: $targetWidth);
                $encoded = (string) $variant->encode(new WebpEncoder(
                    quality: $quality,
                    strip: true,
                ));
                $path = "{$directory}/{$book->id}/{$safeFingerprint}-{$variantToken}-{$configuration['name']}-{$targetWidth}.webp";
                $temporaryPath = $path.'.tmp-'.Str::uuid();

                if (! Storage::disk($diskName)->put($temporaryPath, $encoded)) {
                    throw new CoverImageException('Die optimierte Coverdatei konnte nicht gespeichert werden.');
                }

                $paths[$key] = $path;
                $temporaryPaths[$key] = $temporaryPath;
            }

            foreach ($temporaryPaths as $key => $temporaryPath) {
                if (! Storage::disk($diskName)->move($temporaryPath, $paths[$key])) {
                    throw new CoverImageException('Die optimierte Coverdatei konnte nicht veröffentlicht werden.');
                }
            }
        } catch (Throwable $exception) {
            Storage::disk($diskName)->delete(array_values($temporaryPaths));
            Storage::disk($diskName)->delete(array_values($paths));

            if ($exception instanceof CoverImageException) {
                throw $exception;
            }

            throw new CoverImageException('Das Coverbild konnte nicht verarbeitet werden.', previous: $exception);
        }

        return [
            ...$paths,
            'width' => $width,
            'height' => $height,
            'mime_type' => 'image/webp',
        ];
    }
}
