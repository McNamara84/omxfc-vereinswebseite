<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Exceptions\MaddraxikonCrawlException;
use App\Services\Maddraxikon\MaddraxikonReleaseDateParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);
covers(MaddraxikonReleaseDateParser::class);
dataset('MaddraxikonReleaseDateParserTest.validDateProvider', static function (): iterable {
    yield 'ISO date' => ['2024-02-29', '2024-02-29'];
    yield 'ISO year and month' => ['2024-01', '2024-01-01'];
    yield 'ISO year and single-digit month' => ['2024-1', '2024-01-01'];
    yield 'year only' => ['2024', '2024-01-01'];
    yield 'legacy hardcover year only' => ['.2002', '2002-01-01'];
    yield 'numeric month and year' => ['04.2000', '2000-04-01'];
    yield 'single-digit numeric month and year' => ['4.2000', '2000-04-01'];
    yield 'numeric German date' => ['29.02.2024', '2024-02-29'];
    yield 'German date with dot' => ['12. März 2004', '2004-03-12'];
    yield 'German date without dot' => ['12 März 2004', '2004-03-12'];
    yield 'month and year' => ['Juni 2004', '2004-06-01'];
});
dataset('MaddraxikonReleaseDateParserTest.overflowDateProvider', static function (): iterable {
    yield 'ISO overflow' => ['2026-02-31'];
    yield 'month overflow' => ['2026-13'];
    yield 'numeric month and year overflow' => ['13.2000'];
    yield 'year zero' => ['0000'];
    yield 'legacy year zero' => ['.0000'];
    yield 'numeric overflow' => ['31.02.2026'];
    yield 'German month overflow' => ['31. Februar 2026'];
    yield 'non-leap day' => ['29. Februar 2025'];
});
test('it normalizes german month only dates', function (): void {
    $parser = new MaddraxikonReleaseDateParser;
    expect($parser->parse('Juni 2004')->toDateString())->toBe('2004-06-01')
        ->and($parser->parse('Dezember 2004')->toDateString())->toBe('2004-12-01')
        ->and($parser->parse('12. März 2004')->toDateString())->toBe('2004-03-12');
});
test('it strictly parses supported date formats', function (string $value, string $expected): void {
    expect((new MaddraxikonReleaseDateParser)->parse($value)->toDateString())->toBe($expected);
})->with('MaddraxikonReleaseDateParserTest.validDateProvider');
test('it rejects calendar overflows', function (string $value): void {
    expect(fn () => (new MaddraxikonReleaseDateParser)->parse($value))->toThrow(MaddraxikonCrawlException::class, 'nicht sicher ausgewertet');
})->with('MaddraxikonReleaseDateParserTest.overflowDateProvider');
test('it rejects an unparseable date and preserves the cause', function (): void {
    try {
        (new MaddraxikonReleaseDateParser)->parse('irgendwann demnächst');
        $this->fail('Expected date parsing to fail.');
    } catch (MaddraxikonCrawlException $exception) {
        expect($exception->getMessage())->toContain('nicht sicher ausgewertet')
            ->and($exception->getPrevious())->not->toBeNull();
    }
});
