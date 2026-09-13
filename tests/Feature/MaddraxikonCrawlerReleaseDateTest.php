<?php

namespace Tests\Feature;

use App\Data\Maddraxikon\CrawledBook;
use App\Enums\BookType;
use App\Exceptions\MaddraxikonCrawlException;
use App\Services\Maddraxikon\MaddraxikonArticleParser;
use App\Services\Maddraxikon\MaddraxikonCategoryPaginator;
use App\Services\Maddraxikon\MaddraxikonCrawler;
use App\Services\Maddraxikon\MaddraxikonCrawlerHttpClient;
use App\Services\Maddraxikon\MaddraxikonReleaseDateParser;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MaddraxikonCrawlerReleaseDateTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Mockery::close();

        parent::tearDown();
    }

    public function test_german_month_dates_filter_only_the_unpublished_book(): void
    {
        CarbonImmutable::setTestNow('2004-08-15 12:00:00');
        $crawler = $this->crawlerWithBooks([
            $this->book(1, 'Juni 2004'),
            $this->book(2, 'Dezember 2004'),
        ]);

        $datasets = $crawler->crawl([BookType::MaddraxHardcover]);

        $this->assertSame([1], array_column($datasets['hardcovers'], 'nummer'));
    }

    public function test_year_and_year_month_dates_use_the_start_of_their_period(): void
    {
        CarbonImmutable::setTestNow('2024-06-15 12:00:00');
        $crawler = $this->crawlerWithBooks([
            $this->book(1, '2024-06'),
            $this->book(2, '2024'),
            $this->book(3, '2025'),
            $this->book(4, '.2002'),
        ]);

        $datasets = $crawler->crawl([BookType::MaddraxHardcover]);

        $this->assertSame([1, 2, 4], array_column($datasets['hardcovers'], 'nummer'));
    }

    public function test_unparseable_release_date_fails_the_crawl_closed(): void
    {
        $crawler = $this->crawlerWithBooks([$this->book(1, 'demnächst')]);

        $this->expectException(MaddraxikonCrawlException::class);
        $this->expectExceptionMessage('nicht sicher ausgewertet');

        $crawler->crawl([BookType::MaddraxHardcover]);
    }

    public function test_overflow_release_date_fails_the_crawl_closed(): void
    {
        $crawler = $this->crawlerWithBooks([$this->book(1, '31. Februar 2026')]);

        $this->expectException(MaddraxikonCrawlException::class);
        $this->expectExceptionMessage('nicht sicher ausgewertet');

        $crawler->crawl([BookType::MaddraxHardcover]);
    }

    public function test_array_and_string_callables_receive_crawl_progress(): void
    {
        $crawler = $this->crawlerWithBooks([$this->book(1, '2004')]);
        $listener = new MaddraxikonCrawlerProgressListener;
        MaddraxikonCrawlerProgressListener::reset();

        $crawler->crawl(
            [BookType::MaddraxHardcover],
            [$listener, 'seriesStarted'],
            MaddraxikonCrawlerProgressListener::class.'::articleProcessed',
        );

        $this->assertSame(BookType::MaddraxHardcover, $listener->seriesType);
        $this->assertSame(1, $listener->seriesTotal);
        $this->assertSame(BookType::MaddraxHardcover, MaddraxikonCrawlerProgressListener::$articleType);
        $this->assertSame(1, MaddraxikonCrawlerProgressListener::$articleCurrent);
        $this->assertSame(1, MaddraxikonCrawlerProgressListener::$articleTotal);
    }

    /** @param list<CrawledBook> $books */
    private function crawlerWithBooks(array $books): MaddraxikonCrawler
    {
        $urls = array_map(
            static fn (int $index): string => "https://de.maddraxikon.com/wiki/HC_{$index}",
            array_keys($books),
        );
        $paginator = Mockery::mock(MaddraxikonCategoryPaginator::class);
        $paginator->shouldReceive('articleUrls')->once()->andReturn($urls);
        $http = Mockery::mock(MaddraxikonCrawlerHttpClient::class);
        $http->shouldReceive('get')->times(count($books))->andReturn('<html></html>');
        $parser = Mockery::mock(MaddraxikonArticleParser::class);

        foreach ($books as $index => $book) {
            $parser->shouldReceive('parse')
                ->once()
                ->with('<html></html>', $urls[$index], BookType::MaddraxHardcover)
                ->andReturn($book);
        }

        return new MaddraxikonCrawler(
            $paginator,
            $http,
            $parser,
            new MaddraxikonReleaseDateParser,
        );
    }

    private function book(int $number, string $releasedAt): CrawledBook
    {
        return new CrawledBook(
            number: $number,
            releasedAt: $releasedAt,
            cycle: null,
            rating: null,
            votes: 0,
            title: "Hardcover {$number}",
            authors: ['Autor'],
            characters: null,
            keywords: null,
            locations: null,
            pageTitle: "HC {$number}",
        );
    }
}

final class MaddraxikonCrawlerProgressListener
{
    public ?BookType $seriesType = null;

    public int $seriesTotal = 0;

    public static ?BookType $articleType = null;

    public static int $articleCurrent = 0;

    public static int $articleTotal = 0;

    public function seriesStarted(BookType $type, int $total): void
    {
        $this->seriesType = $type;
        $this->seriesTotal = $total;
    }

    public static function articleProcessed(BookType $type, int $current, int $total): void
    {
        self::$articleType = $type;
        self::$articleCurrent = $current;
        self::$articleTotal = $total;
    }

    public static function reset(): void
    {
        self::$articleType = null;
        self::$articleCurrent = 0;
        self::$articleTotal = 0;
    }
}
