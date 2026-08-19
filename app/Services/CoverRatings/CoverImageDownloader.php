<?php

namespace App\Services\CoverRatings;

use App\Exceptions\CoverImageException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

class CoverImageDownloader
{
    public function __construct(
        private readonly CoverImageUrlGuard $urlGuard,
    ) {}

    /**
     * @return array{body: string, mime_type: string}
     */
    public function download(string $url): array
    {
        $this->urlGuard->assertAllowed($url);
        $maximumBytes = max(
            1,
            (int) config('cover-ratings.images.max_bytes', 15 * 1024 * 1024),
        );
        $temporaryStream = tmpfile();

        if ($temporaryStream === false) {
            throw new CoverImageException('Für das Coverbild konnte kein temporärer Speicher geöffnet werden.');
        }

        $sizeLimitExceeded = false;

        try {
            try {
                $response = Http::accept('image/jpeg, image/png, image/webp')
                    ->withUserAgent((string) config(
                        'maddraxikon.http.user_agent',
                        'OMXFC-Vereinswebsite/1.0',
                    ))
                    ->connectTimeout((int) config('cover-ratings.images.connect_timeout', 5))
                    ->timeout((int) config('cover-ratings.images.timeout', 20))
                    ->withOptions([
                        'allow_redirects' => false,
                        'sink' => $temporaryStream,
                        'on_headers' => static function (ResponseInterface $response) use (
                            $maximumBytes,
                            &$sizeLimitExceeded,
                        ): void {
                            $contentLength = $response->getHeaderLine('Content-Length');

                            if (
                                $contentLength !== ''
                                && ctype_digit($contentLength)
                                && (int) $contentLength > $maximumBytes
                            ) {
                                $sizeLimitExceeded = true;

                                throw new RuntimeException('cover-image-size-limit');
                            }
                        },
                        'progress' => static function (
                            mixed $downloadTotal,
                            mixed $downloadedBytes,
                        ) use ($maximumBytes, &$sizeLimitExceeded): void {
                            if (
                                (int) $downloadTotal > $maximumBytes
                                || (int) $downloadedBytes > $maximumBytes
                            ) {
                                $sizeLimitExceeded = true;

                                throw new RuntimeException('cover-image-size-limit');
                            }
                        },
                    ])
                    ->get($url);
            } catch (Throwable $exception) {
                if ($sizeLimitExceeded) {
                    throw new CoverImageException(
                        'Das Coverbild überschreitet die erlaubte Dateigröße.',
                        previous: $exception,
                    );
                }

                if ($exception instanceof ConnectionException) {
                    throw new CoverImageException(
                        'Das Coverbild konnte nicht heruntergeladen werden.',
                        previous: $exception,
                    );
                }

                throw new CoverImageException(
                    'Das Coverbild konnte nicht sicher heruntergeladen werden.',
                    previous: $exception,
                );
            }

            if (! $response->successful()) {
                throw new CoverImageException('Die Cover-Bildquelle antwortete mit HTTP '.$response->status().'.');
            }

            $contentLength = $response->header('Content-Length');

            if (is_string($contentLength) && ctype_digit($contentLength) && (int) $contentLength > $maximumBytes) {
                throw new CoverImageException('Das Coverbild überschreitet die erlaubte Dateigröße.');
            }

            rewind($temporaryStream);
            $body = stream_get_contents($temporaryStream, $maximumBytes + 1);

            if (! is_string($body) || $body === '' || strlen($body) > $maximumBytes) {
                throw new CoverImageException('Das Coverbild ist leer oder überschreitet die erlaubte Dateigröße.');
            }

            $mimeType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
            $allowedMimeTypes = config('cover-ratings.images.allowed_mime_types', []);

            if (! is_array($allowedMimeTypes) || ! in_array($mimeType, $allowedMimeTypes, true)) {
                throw new CoverImageException('Die Cover-Bildquelle lieferte keinen erlaubten Bildtyp.');
            }

            return [
                'body' => $body,
                'mime_type' => $mimeType,
            ];
        } finally {
            fclose($temporaryStream);
        }
    }
}
