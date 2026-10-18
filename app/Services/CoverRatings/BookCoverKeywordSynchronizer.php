<?php

namespace App\Services\CoverRatings;

use App\Models\BookCover;
use App\Support\CoverRatings\CoverKeywords;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BookCoverKeywordSynchronizer
{
    /** @param array{keywords: ?array, error: ?string} $source */
    public function sync(BookCover $cover, string $fileTitle, array $source, bool $dryRun = false): string
    {
        $error = $source['error'] ?? null;
        $keywords = null;
        try {
            if (! is_array($source['keywords'] ?? null)) {
                throw new InvalidArgumentException($error ?: 'Keine verlässlichen Schlagworte verfügbar.');
            }
            $keywords = CoverKeywords::values($source['keywords']);
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        }

        if ($dryRun) {
            return $error !== null ? 'keywords_failed' : $this->result($cover, $fileTitle, $keywords);
        }

        return DB::transaction(function () use ($cover, $fileTitle, $keywords, $error): string {
            $current = BookCover::query()->lockForUpdate()->findOrFail($cover->id);
            if ($current->source_file_title !== $fileTitle) {
                return 'keywords_failed';
            }
            if ($error !== null) {
                $current->update([
                    'keywords_sync_attempted_at' => now(),
                    'keywords_sync_error' => mb_substr($error, 0, 2000),
                ]);

                return 'keywords_failed';
            }

            $result = $this->result($current, $fileTitle, $keywords);
            if ($result !== 'keywords_unchanged') {
                $current->keywords()->delete();
                $current->keywords()->createMany(collect($keywords)->map(
                    fn (string $display, string $normalized): array => ['keyword' => $display, 'normalized_keyword' => $normalized],
                )->values()->all());
            }
            $current->update([
                'keywords_source_file_title' => $fileTitle,
                'keywords_synced_at' => now(),
                'keywords_sync_attempted_at' => now(),
                'keywords_sync_error' => null,
            ]);

            return $result;
        });
    }

    private function result(BookCover $cover, string $fileTitle, array $keywords): string
    {
        if ($keywords === []) {
            return 'keywords_empty';
        }
        $stored = $cover->exists ? $cover->keywords()->pluck('keyword', 'normalized_keyword')->all() : [];
        ksort($stored, SORT_STRING);

        return $cover->keywords_source_file_title === $fileTitle && $stored === $keywords
            ? 'keywords_unchanged' : 'keywords_updated';
    }
}
