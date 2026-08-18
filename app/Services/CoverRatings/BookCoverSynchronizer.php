<?php

namespace App\Services\CoverRatings;

use App\Enums\BookCoverStatus;
use App\Exceptions\CoverImageException;
use App\Models\Book;
use App\Models\BookCover;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BookCoverSynchronizer
{
    public function __construct(
        private readonly CoverImageDownloader $downloader,
        private readonly CoverImageProcessor $processor,
    ) {}

    /**
     * @param  array<string, mixed>|null  $source
     * @return 'ready'|'unchanged'|'missing'|'changed'|'failed'
     */
    public function sync(Book $book, ?array $source, bool $force = false, bool $dryRun = false): string
    {
        $cover = BookCover::query()->firstOrNew(['book_id' => $book->id]);

        if (! is_array($source) || ! ($source['exists'] ?? false)) {
            if (! $dryRun && ! $cover->isReady()) {
                $cover->fill([
                    'status' => BookCoverStatus::Missing,
                    'last_synced_at' => now(),
                    'last_error' => 'Im Maddraxikon wurde kein geeignetes Titelbild gefunden.',
                ])->save();
            }

            return 'missing';
        }

        $sourceTitle = trim((string) ($source['file_title'] ?? ''));
        $sourceUrl = trim((string) ($source['url'] ?? ''));
        $sourceSha1 = trim((string) ($source['sha1'] ?? ''));
        $titleChanged = $cover->exists
            && filled($cover->source_file_title)
            && $cover->source_file_title !== $sourceTitle;

        if ($titleChanged && $cover->ratings()->withTrashed()->exists() && ! $force) {
            if (! $dryRun) {
                $cover->forceFill([
                    'last_synced_at' => now(),
                    'last_error' => 'Das Maddraxikon-Titelbild hat gewechselt; Übernahme erfordert --force.',
                ])->save();
            }

            return 'changed';
        }

        $disk = Storage::disk((string) config('cover-ratings.images.disk', 'private'));
        $filesExist = filled($cover->small_path)
            && filled($cover->large_path)
            && $disk->exists((string) $cover->small_path)
            && $disk->exists((string) $cover->large_path);

        if (
            ! $force
            && $cover->isReady()
            && $sourceSha1 !== ''
            && hash_equals((string) $cover->source_sha1, $sourceSha1)
            && $filesExist
        ) {
            if (! $dryRun) {
                $cover->forceFill([
                    ...$this->sourceAttributes($source),
                    'last_synced_at' => now(),
                    'last_error' => null,
                ])->save();
            }

            return 'unchanged';
        }

        if ($dryRun) {
            return $cover->exists ? 'changed' : 'ready';
        }

        $publishedPaths = [];

        try {
            if ($sourceUrl === '') {
                throw new CoverImageException('Das Maddraxikon lieferte keine Cover-Bildadresse.');
            }

            $download = $this->downloader->download($sourceUrl);
            $fingerprint = $sourceSha1 !== '' ? $sourceSha1 : sha1($download['body']);
            $processed = $this->processor->process($book, $download['body'], $fingerprint);
            $publishedPaths = [$processed['small_path'], $processed['large_path']];
            $oldPaths = array_values(array_filter([
                $cover->small_path,
                $cover->large_path,
            ], 'is_string'));

            $cover->fill([
                ...$this->sourceAttributes($source),
                ...$processed,
                'source_sha1' => $fingerprint,
                'status' => BookCoverStatus::Ready,
                'last_synced_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            // A failed save may leave the in-memory model filled with paths that
            // were never committed. Reload the last durable state before recording
            // the failure so deleted variants can never be persisted as ready.
            $cover = BookCover::query()->firstOrNew(['book_id' => $book->id]);
            $publishedPathsAreDurable = $publishedPaths !== []
                && $cover->isReady()
                && $cover->small_path === $publishedPaths[0]
                && $cover->large_path === $publishedPaths[1];

            if ($publishedPaths !== [] && ! $publishedPathsAreDurable) {
                try {
                    $disk->delete($publishedPaths);
                } catch (Throwable $cleanupException) {
                    Log::warning('Fehlgeschlagene Coverdateien konnten nicht bereinigt werden.', [
                        'book_id' => $book->id,
                        'exception' => $cleanupException::class,
                    ]);
                }
            }

            $message = mb_substr($exception->getMessage(), 0, 2000);
            $attributes = [
                'last_synced_at' => now(),
                'last_error' => $message,
            ];

            if (! $cover->isReady()) {
                $attributes['status'] = BookCoverStatus::Failed;
            }

            $cover->fill($attributes)->save();

            Log::warning('Cover-Synchronisation für ein Buch fehlgeschlagen.', [
                'book_id' => $book->id,
                'exception' => $exception::class,
            ]);

            return 'failed';
        }

        $obsoletePaths = array_values(array_diff($oldPaths, $publishedPaths));

        if ($obsoletePaths !== []) {
            try {
                $disk->delete($obsoletePaths);
            } catch (Throwable $exception) {
                Log::warning('Veraltete Coverdateien konnten nicht bereinigt werden.', [
                    'book_id' => $book->id,
                    'exception' => $exception::class,
                ]);
            }
        }

        return 'ready';
    }

    /** @param array<string, mixed> $source */
    private function sourceAttributes(array $source): array
    {
        return [
            'source_file_title' => $source['file_title'] ?? null,
            'source_url' => $source['url'] ?? null,
            'source_sha1' => $source['sha1'] ?? null,
            'source_description_url' => $source['description_url'] ?? null,
            'source_artist' => $source['artist'] ?? null,
            'source_credit' => $source['credit'] ?? null,
            'source_license' => $source['license'] ?? null,
            'source_license_url' => $source['license_url'] ?? null,
        ];
    }
}
