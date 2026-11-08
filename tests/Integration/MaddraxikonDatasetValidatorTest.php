<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\BookType;
use App\Exceptions\MaddraxikonCrawlException;
use App\Models\Book;
use App\Services\Maddraxikon\MaddraxikonDatasetValidator;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxikonDatasetValidatorTestFixtures::class);

test('accepts complete main series rows', function (): void {
    Book::create(['roman_number' => 1, 'title' => 'Alt', 'author' => 'Autor', 'cycle' => 'Euree']);
    app(MaddraxikonDatasetValidator::class)->validate([$this->row(1, 'Euree'), $this->row(2, 'Meeraka')], BookType::MaddraxDieDunkleZukunftDerErde);
    $this->addToAssertionCount(1);
});

test('rejects missing main series cycle', function (): void {
    expect(fn () => app(MaddraxikonDatasetValidator::class)->validate([$this->row(1, null)], BookType::MaddraxDieDunkleZukunftDerErde))->toThrow(MaddraxikonCrawlException::class, 'hat keinen Zyklus');
});

test('allows missing cycle for hardcovers', function (): void {
    app(MaddraxikonDatasetValidator::class)->validate([$this->row(1, null)], BookType::MaddraxHardcover);
    $this->addToAssertionCount(1);
});

test('rejects duplicate numbers', function (): void {
    expect(fn () => app(MaddraxikonDatasetValidator::class)->validate([$this->row(1, 'Euree'), $this->row(1, 'Euree')], BookType::MaddraxDieDunkleZukunftDerErde))->toThrow(MaddraxikonCrawlException::class, 'doppelt');
});

test('rejects loss against database and active snapshot', function (): void {
    Book::create(['roman_number' => 2, 'title' => 'Bestand', 'author' => 'Autor', 'cycle' => 'Euree']);
    $validator = app(MaddraxikonDatasetValidator::class);
    try {
        $validator->validate([$this->row(1, 'Euree')], BookType::MaddraxDieDunkleZukunftDerErde, [$this->row(1, 'Euree'), $this->row(3, 'Meeraka')], baselineSource: 'database_snapshot');
        $this->fail('Expected baseline rejection.');
    } catch (MaddraxikonCrawlException $exception) {
        expect($exception->getMessage())->toContain('aktiven Datenbank-Snapshot');
    }
    expect(fn () => $validator->validate([$this->row(1, 'Euree')], BookType::MaddraxDieDunkleZukunftDerErde))->toThrow(MaddraxikonCrawlException::class, 'Datenbankbestand');
});

test('coverage error identifies a legacy file baseline', function (): void {
    expect(fn () => app(MaddraxikonDatasetValidator::class)->validate([$this->row(1, 'Euree')], BookType::MaddraxDieDunkleZukunftDerErde, [$this->row(1, 'Euree'), $this->row(2, 'Meeraka')], checkDatabaseCoverage: false, baselineSource: 'legacy_file'))->toThrow(MaddraxikonCrawlException::class, 'gegenüber der bisherigen JSON-Datei');
});
