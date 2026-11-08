<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookType;
use App\Exceptions\MaddraxikonCrawlException;
use App\Models\Book;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

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

pest()->extend(TestCase::class)->use(MaddraxikonCrawlerReleaseDateTestFixtures::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
    Mockery::close();
});

test('german month dates filter only the unpublished book', function (): void {
    CarbonImmutable::setTestNow('2004-08-15 12:00:00');
    $crawler = $this->crawlerWithBooks([$this->book(1, 'Juni 2004'), $this->book(2, 'Dezember 2004')]);
    $datasets = $crawler->crawl([BookType::MaddraxHardcover]);
    expect(array_column($datasets['hardcovers'], 'nummer'))->toBe([1]);
});

test('year and year month dates use the start of their period', function (): void {
    CarbonImmutable::setTestNow('2024-06-15 12:00:00');
    $crawler = $this->crawlerWithBooks([$this->book(1, '2024-06'), $this->book(2, '2024'), $this->book(3, '2025'), $this->book(4, '.2002'), $this->book(5, '04.2000')]);
    $datasets = $crawler->crawl([BookType::MaddraxHardcover]);
    expect(array_column($datasets['hardcovers'], 'nummer'))->toBe([1, 2, 4, 5]);
});

test('unparseable release date fails the crawl closed', function (): void {
    $crawler = $this->crawlerWithBooks([$this->book(1, 'demnächst')]);
    expect(fn () => $crawler->crawl([BookType::MaddraxHardcover]))->toThrow(MaddraxikonCrawlException::class, 'nicht sicher ausgewertet');
});

test('overflow release date fails the crawl closed', function (): void {
    $crawler = $this->crawlerWithBooks([$this->book(1, '31. Februar 2026')]);
    expect(fn () => $crawler->crawl([BookType::MaddraxHardcover]))->toThrow(MaddraxikonCrawlException::class, 'nicht sicher ausgewertet');
});

test('array and string callables receive crawl progress', function (): void {
    $crawler = $this->crawlerWithBooks([$this->book(1, '2004')]);
    $listener = new MaddraxikonCrawlerProgressListener;
    MaddraxikonCrawlerProgressListener::reset();
    $crawler->crawl([BookType::MaddraxHardcover], $listener->seriesStarted(...), MaddraxikonCrawlerProgressListener::class.'::articleProcessed');
    expect($listener->seriesType)->toBe(BookType::MaddraxHardcover)
        ->and($listener->seriesTotal)->toBe(1)
        ->and(MaddraxikonCrawlerProgressListener::$articleType)->toBe(BookType::MaddraxHardcover)
        ->and(MaddraxikonCrawlerProgressListener::$articleCurrent)->toBe(1)
        ->and(MaddraxikonCrawlerProgressListener::$articleTotal)->toBe(1);
});

test('existing book numbers are loaded once per series for future date filtering', function (): void {
    CarbonImmutable::setTestNow('2024-06-15 12:00:00');
    Book::factory()->create(['roman_number' => 2, 'type' => BookType::MaddraxHardcover]);
    $bookSelects = [];
    DB::listen(function (QueryExecuted $query) use (&$bookSelects): void {
        $sql = strtolower($query->sql);
        if (str_starts_with($sql, 'select') && str_contains($sql, 'books')) {
            $bookSelects[] = $query->sql;
        }
    });
    $crawler = $this->crawlerWithBooks([$this->book(1, '2025'), $this->book(2, '2025'), $this->book(3, '2025')]);
    $datasets = $crawler->crawl([BookType::MaddraxHardcover]);
    expect(array_column($datasets['hardcovers'], 'nummer'))->toBe([2])
        ->and($bookSelects)->toHaveCount(1);
});
