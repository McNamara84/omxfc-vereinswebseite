<?php

namespace App\Services\Maddraxikon;

use App\Enums\BookType;
use App\Models\Book;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MaddraxikonCrawler
{
    public function __construct(
        private readonly MaddraxikonCategoryPaginator $paginator,
        private readonly MaddraxikonCrawlerHttpClient $http,
        private readonly MaddraxikonArticleParser $parser,
    ) {}

    /**
     * @param  list<BookType>  $types
     * @param  callable(BookType, int): void|null  $onSeriesStarted
     * @param  callable(BookType, int, int): void|null  $onArticleProcessed
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function crawl(
        array $types,
        ?callable $onSeriesStarted = null,
        ?callable $onArticleProcessed = null,
    ): array {
        $datasets = [];

        foreach ($types as $type) {
            $startedAt = microtime(true);
            $urls = $this->paginator->articleUrls(MaddraxikonSeries::categoryUrl($type));
            $onSeriesStarted?->__invoke($type, count($urls));
            $rows = [];

            foreach ($urls as $index => $url) {
                $book = $this->parser->parse($this->http->get($url), $url, $type);

                if (! $this->isUnpublishedFutureBook($book->releasedAt, $book->number, $type)) {
                    $rows[] = $book->toArray();
                }

                $onArticleProcessed?->__invoke($type, $index + 1, count($urls));
            }

            usort(
                $rows,
                static fn (array $left, array $right): int => ((int) $left['nummer']) <=> ((int) $right['nummer'])
            );
            $datasets[$type->key()] = $rows;

            Log::info('Maddraxikon-Crawl abgeschlossen.', [
                'series' => $type->key(),
                'article_urls' => count($urls),
                'published_rows' => count($rows),
                'highest_number' => $rows !== [] ? max(array_column($rows, 'nummer')) : null,
                'cycle_count' => count(array_unique(array_filter(array_column($rows, 'zyklus')))),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
        }

        return $datasets;
    }

    private function isUnpublishedFutureBook(
        ?string $releasedAt,
        int $number,
        BookType $type,
    ): bool {
        if ($releasedAt === null || trim($releasedAt) === '') {
            return false;
        }

        try {
            if (! Carbon::parse($releasedAt)->isAfter(Carbon::today())) {
                return false;
            }

            return ! Book::query()
                ->where('roman_number', $number)
                ->where('type', $type)
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
