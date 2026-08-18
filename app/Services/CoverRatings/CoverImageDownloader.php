<?php

namespace App\Services\CoverRatings;

use App\Exceptions\CoverImageException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

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

        try {
            $response = Http::accept('image/jpeg, image/png, image/webp')
                ->withUserAgent((string) config(
                    'maddraxikon.http.user_agent',
                    'OMXFC-Vereinswebsite/1.0',
                ))
                ->connectTimeout((int) config('cover-ratings.images.connect_timeout', 5))
                ->timeout((int) config('cover-ratings.images.timeout', 20))
                ->withOptions(['allow_redirects' => false])
                ->get($url);
        } catch (ConnectionException $exception) {
            throw new CoverImageException('Das Coverbild konnte nicht heruntergeladen werden.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new CoverImageException('Die Cover-Bildquelle antwortete mit HTTP '.$response->status().'.');
        }

        $maximumBytes = (int) config('cover-ratings.images.max_bytes', 15 * 1024 * 1024);
        $contentLength = $response->header('Content-Length');

        if (is_string($contentLength) && ctype_digit($contentLength) && (int) $contentLength > $maximumBytes) {
            throw new CoverImageException('Das Coverbild überschreitet die erlaubte Dateigröße.');
        }

        $body = $response->body();

        if ($body === '' || strlen($body) > $maximumBytes) {
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
    }
}
