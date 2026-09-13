<?php

namespace App\Services\Maddraxikon;

use App\Enums\BookType;
use App\Models\Book;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class MaddraxikonCrawler
{
    public function __construct(
        private readonly MaddraxikonCategoryPaginator $paginator,
        private readonly MaddraxikonCrawlerHttpClient $http,
        private readonly MaddraxikonArticleParser $parser,
        private readonly MaddraxikonReleaseDateParser $releaseDates,
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
        ?MaddraxikonCrawlDeadline $deadline = null,
    ): array {
        $deadline ??= MaddraxikonCrawlDeadline::afterSeconds(max(
            60,
            (int) config('maddraxikon.crawler.max_runtime_seconds', 1800),
        ));
        $datasets = [];

        foreach ($types as $type) {
            $deadline->ensureNotExpired();
            $startedAt = microtime(true);
            $urls = $this->paginator->articleUrls(
                MaddraxikonSeries::categoryUrl($type),
                $deadline,
            );
            if ($onSeriesStarted !== null) {
                $onSeriesStarted($type, count($urls));
            }
            $rows = [];

            foreach ($urls as $index => $url) {
                $deadline->ensureNotExpired($url);
                $book = $this->parser->parse(
                    $this->http->get($url, $deadline),
                    $url,
                    $type,
                );
                $deadline->ensureNotExpired($url);

                if (! $this->isUnpublishedFutureBook($book->releasedAt, $book->number, $type)) {
                    $rows[] = $book->toArray();
                }

                $deadline->ensureNotExpired($url);
                if ($onArticleProcessed !== null) {
                    $onArticleProcessed($type, $index + 1, count($urls));
                }
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

        $releasedOn = $this->releaseDates->parse($releasedAt);
        $today = CarbonImmutable::today((string) config('maddraxikon.timezone', 'Europe/Berlin'));

        if (! $releasedOn->isAfter($today)) {
            return false;
        }

        return ! Book::query()
            ->where('roman_number', $number)
            ->where('type', $type)
            ->exists();
    }
}
