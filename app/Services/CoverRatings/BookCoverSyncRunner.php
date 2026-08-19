<?php

namespace App\Services\CoverRatings;

use App\Models\Book;
use App\Services\Maddraxikon\MaddraxikonApiClient;
use Illuminate\Database\Eloquent\Builder;

class BookCoverSyncRunner
{
    public function __construct(
        private readonly MaddraxikonApiClient $apiClient,
        private readonly BookCoverSynchronizer $synchronizer,
    ) {}

    /**
     * @param  null|callable(Book, string): void  $report
     * @return array{ready: int, unchanged: int, missing: int, changed: int, failed: int}
     */
    public function run(
        Builder $query,
        bool $force = false,
        bool $dryRun = false,
        ?callable $report = null,
    ): array {
        $counts = array_fill_keys(['ready', 'unchanged', 'missing', 'changed', 'failed'], 0);
        $batchSize = (int) config('cover-ratings.sync.batch_size', 25);

        $query->with('cover')->orderBy('books.id')->chunkById(
            $batchSize,
            function ($books) use (&$counts, $force, $dryRun, $report): void {
                $pageIds = $books->pluck('maddraxikon_page_id')
                    ->filter(fn (mixed $id): bool => (int) $id > 0)
                    ->map(fn (mixed $id): int => (int) $id)
                    ->values()
                    ->all();
                $sources = $this->apiClient->coverImages($pageIds);

                foreach ($books as $book) {
                    $pageId = (int) ($book->maddraxikon_page_id ?? 0);
                    $result = $this->synchronizer->sync(
                        $book,
                        $sources[$pageId] ?? null,
                        $force,
                        $dryRun,
                    );
                    $counts[$result]++;

                    if ($report) {
                        $report($book, $result);
                    }
                }
            },
            column: 'books.id',
            alias: 'id',
        );

        return $counts;
    }
}
