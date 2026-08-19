<?php

namespace App\Http\Controllers;

use App\Models\BookCover;
use App\Services\CoverRatings\CoverRatingAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class CoverImageController extends Controller
{
    public function __invoke(
        Request $request,
        BookCover $bookCover,
        string $variant,
        CoverRatingAccessService $access,
    ): Response {
        $access->ensureMemberAccess();
        abort_unless($bookCover->isReady(), 404);

        $path = match ($variant) {
            'small' => $bookCover->small_path,
            'large' => $bookCover->large_path,
            default => null,
        };
        abort_unless(is_string($path) && $path !== '', 404);
        $directory = trim((string) config('cover-ratings.images.directory', 'cover-ratings'), '/');
        abort_unless(
            $directory !== ''
            && str_starts_with($path, $directory.'/')
            && ! str_contains($path, '..'),
            404,
        );

        $disk = Storage::disk((string) config('cover-ratings.images.disk', 'private'));
        abort_unless($disk->exists($path), 404);

        $lastModified = $disk->lastModified($path);
        $etag = '"'.hash('sha256', implode('|', [
            $bookCover->source_sha1,
            $variant,
            $disk->size($path),
            $lastModified,
        ])).'"';
        $headers = [
            'Content-Type' => 'image/webp',
            'Content-Length' => (string) $disk->size($path),
            'Cache-Control' => 'private, max-age=86400',
            'ETag' => $etag,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified).' GMT',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response('', 304, $headers);
        }

        return response()->stream(function () use ($disk, $path): void {
            $stream = $disk->readStream($path);

            if (! is_resource($stream)) {
                return;
            }

            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, $headers);
    }
}
