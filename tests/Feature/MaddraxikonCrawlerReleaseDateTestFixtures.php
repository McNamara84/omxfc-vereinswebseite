<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Data\Maddraxikon\CrawledBook;
use App\Enums\BookType;
use App\Services\Maddraxikon\MaddraxikonArticleParser;
use App\Services\Maddraxikon\MaddraxikonCategoryPaginator;
use App\Services\Maddraxikon\MaddraxikonCrawler;
use App\Services\Maddraxikon\MaddraxikonCrawlerHttpClient;
use App\Services\Maddraxikon\MaddraxikonReleaseDateParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

trait MaddraxikonCrawlerReleaseDateTestFixtures
{
    use RefreshDatabase;

    /** @param list<CrawledBook> $books */
    private function crawlerWithBooks(array $books): MaddraxikonCrawler
    {
        $urls = array_map(static fn (int $index): string => "https://de.maddraxikon.com/wiki/HC_{$index}", array_keys($books));
        $paginator = Mockery::mock(MaddraxikonCategoryPaginator::class);
        $paginator->shouldReceive('articleUrls')->once()->andReturn($urls);
        $http = Mockery::mock(MaddraxikonCrawlerHttpClient::class);
        $http->shouldReceive('get')->times(count($books))->andReturn('<html></html>');
        $parser = Mockery::mock(MaddraxikonArticleParser::class);
        foreach ($books as $index => $book) {
            $parser->shouldReceive('parse')->once()->with('<html></html>', $urls[$index], BookType::MaddraxHardcover)->andReturn($book);
        }

        return new MaddraxikonCrawler($paginator, $http, $parser, new MaddraxikonReleaseDateParser);
    }

    private function book(int $number, string $releasedAt): CrawledBook
    {
        return new CrawledBook(number: $number, releasedAt: $releasedAt, cycle: null, rating: null, votes: 0, title: "Hardcover {$number}", authors: ['Autor'], characters: null, keywords: null, locations: null, pageTitle: "HC {$number}");
    }
}
