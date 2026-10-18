<?php

namespace App\Services\CoverRatings;

use App\Exceptions\CoverSyncTimeoutException;
use App\Models\Book;
use App\Models\BookCover;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class BookCoverSyncRunner
{
    public function __construct(
        private readonly MaddraxikonApiClient $apiClient,
        private readonly BookCoverSynchronizer $synchronizer,
    ) {}

    /**
     * @param  null|callable(Book, string): void  $report
     * @return array<string, int>
     */
    public function run(
        Builder $query,
        bool $force = false,
        bool $dryRun = false,
        ?callable $report = null,
        ?CoverSyncDeadline $deadline = null,
    ): array {
        $deadline ??= new CoverSyncDeadline;
        $lock = Cache::lock('cover-ratings:sync-run', 7200);
        if (! $lock->get()) {
            throw new RuntimeException('Ein Cover-Abgleich läuft bereits.');
        }
        try {
            $deadline->ensureRemaining();

            return $this->runLocked($query, $force, $dryRun, $report, $deadline);
        } finally {
            $lock->release();
        }
    }

    private function runLocked(Builder $query, bool $force, bool $dryRun, ?callable $report, CoverSyncDeadline $deadline): array
    {
        $counts = array_fill_keys(['ready', 'unchanged', 'missing', 'changed', 'failed'], 0);
        $keywordsEnabled = (bool) config('bildersuche.sync_enabled');
        if ($keywordsEnabled) {
            $counts += array_fill_keys(['keywords_updated', 'keywords_unchanged', 'keywords_empty', 'keywords_failed'], 0);
        }
        $batchSize = (int) config('cover-ratings.sync.batch_size', 25);

        $query->orderBy('books.id')->chunkById(
            $batchSize,
            function ($books) use (&$counts, $force, $dryRun, $report, $keywordsEnabled, $deadline): void {
                $deadline->ensureRemaining();
                $pageIds = $books->pluck('maddraxikon_page_id')
                    ->filter(fn (mixed $id): bool => (int) $id > 0)
                    ->map(fn (mixed $id): int => (int) $id)
                    ->values()
                    ->all();
                $sourceError = null;
                try {
                    $sources = $this->apiClient->coverImages($pageIds, $deadline);
                } catch (CoverSyncTimeoutException $exception) {
                    throw $exception;
                } catch (Throwable $exception) {
                    $sources = [];
                    $sourceError = $exception;
                    Log::warning('Cover-Quellen konnten nicht abgerufen werden.', ['exception' => $exception::class]);
                }

                $imageResults = [];
                foreach ($books as $book) {
                    $deadline->ensureRemaining();
                    $pageId = (int) ($book->maddraxikon_page_id ?? 0);
                    $result = $sourceError ? 'failed' : $this->synchronizer->sync(
                        $book,
                        $sources[$pageId] ?? null,
                        $force,
                        $dryRun,
                        $deadline,
                    );
                    $counts[$result]++;
                    $imageResults[$book->id] = $result;

                    if ($report) {
                        $report($book, $result);
                    }
                    $deadline->ensureRemaining();
                }
                if (! $keywordsEnabled) {
                    return;
                }

                // Image sync may create or replace covers, or retain an old source
                // after a failure. Load that durable state once for the whole batch.
                $books->load('cover');
                $keywordCovers = [];
                foreach ($books as $book) {
                    $deadline->ensureRemaining();
                    $pageId = (int) ($book->maddraxikon_page_id ?? 0);
                    $cover = $book->cover;
                    // A dry-run can examine a not-yet-persisted proposed source.
                    if ($dryRun && ! $sourceError && ($sources[$pageId]['exists'] ?? false)
                        && ($imageResults[$book->id] !== 'changed' || $force || ! $cover?->ratings()->withTrashed()->exists())) {
                        $cover ??= new BookCover(['book_id' => $book->id]);
                        $cover->source_file_title = $sources[$pageId]['file_title'] ?? null;
                    }
                    if ($cover && filled($cover->source_file_title)) {
                        $keywordCovers[] = $cover;
                    }
                }
                if ($keywordCovers !== []) {
                    $titles = array_map(fn (BookCover $cover): string => $cover->source_file_title, $keywordCovers);
                    $keywords = $this->apiClient->coverKeywords($titles, $deadline);
                    foreach ($keywordCovers as $cover) {
                        $deadline->ensureRemaining();
                        $title = $cover->source_file_title;
                        $result = app(BookCoverKeywordSynchronizer::class)->sync($cover, $title,
                            $keywords[$title] ?? ['keywords' => null, 'error' => 'Keine Schlagwortantwort.'], $dryRun);
                        $counts[$result]++;
                    }
                }
            },
            column: 'books.id',
            alias: 'id',
        );

        $deadline->ensureRemaining();

        return $counts;
    }
}
