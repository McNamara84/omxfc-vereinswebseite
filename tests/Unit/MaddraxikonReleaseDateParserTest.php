<?php

namespace Tests\Unit;

use App\Exceptions\MaddraxikonCrawlException;
use App\Services\Maddraxikon\MaddraxikonReleaseDateParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(MaddraxikonReleaseDateParser::class)]
class MaddraxikonReleaseDateParserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_normalizes_german_month_only_dates(): void
    {
        $parser = new MaddraxikonReleaseDateParser;

        $this->assertSame('2004-06-01', $parser->parse('Juni 2004')->toDateString());
        $this->assertSame('2004-12-01', $parser->parse('Dezember 2004')->toDateString());
        $this->assertSame('2004-03-12', $parser->parse('12. März 2004')->toDateString());
    }

    #[DataProvider('validDateProvider')]
    public function test_it_strictly_parses_supported_date_formats(string $value, string $expected): void
    {
        $this->assertSame($expected, (new MaddraxikonReleaseDateParser)->parse($value)->toDateString());
    }

    /** @return iterable<string, array{string, string}> */
    public static function validDateProvider(): iterable
    {
        yield 'ISO date' => ['2024-02-29', '2024-02-29'];
        yield 'ISO year and month' => ['2024-01', '2024-01-01'];
        yield 'ISO year and single-digit month' => ['2024-1', '2024-01-01'];
        yield 'year only' => ['2024', '2024-01-01'];
        yield 'legacy hardcover year only' => ['.2002', '2002-01-01'];
        yield 'numeric German date' => ['29.02.2024', '2024-02-29'];
        yield 'German date with dot' => ['12. März 2004', '2004-03-12'];
        yield 'German date without dot' => ['12 März 2004', '2004-03-12'];
        yield 'month and year' => ['Juni 2004', '2004-06-01'];
    }

    #[DataProvider('overflowDateProvider')]
    public function test_it_rejects_calendar_overflows(string $value): void
    {
        $this->expectException(MaddraxikonCrawlException::class);
        $this->expectExceptionMessage('nicht sicher ausgewertet');

        (new MaddraxikonReleaseDateParser)->parse($value);
    }

    /** @return iterable<string, array{string}> */
    public static function overflowDateProvider(): iterable
    {
        yield 'ISO overflow' => ['2026-02-31'];
        yield 'month overflow' => ['2026-13'];
        yield 'year zero' => ['0000'];
        yield 'legacy year zero' => ['.0000'];
        yield 'numeric overflow' => ['31.02.2026'];
        yield 'German month overflow' => ['31. Februar 2026'];
        yield 'non-leap day' => ['29. Februar 2025'];
    }

    public function test_it_rejects_an_unparseable_date_and_preserves_the_cause(): void
    {
        try {
            (new MaddraxikonReleaseDateParser)->parse('irgendwann demnächst');
            $this->fail('Expected date parsing to fail.');
        } catch (MaddraxikonCrawlException $exception) {
            $this->assertStringContainsString('nicht sicher ausgewertet', $exception->getMessage());
            $this->assertNotNull($exception->getPrevious());
        }
    }
}
