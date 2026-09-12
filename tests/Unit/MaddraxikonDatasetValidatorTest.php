<?php

namespace Tests\Unit;

use App\Enums\BookType;
use App\Exceptions\MaddraxikonCrawlException;
use App\Models\Book;
use App\Services\Maddraxikon\MaddraxikonDatasetValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaddraxikonDatasetValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepts_complete_main_series_rows(): void
    {
        Book::create([
            'roman_number' => 1,
            'title' => 'Alt',
            'author' => 'Autor',
            'cycle' => 'Euree',
        ]);

        app(MaddraxikonDatasetValidator::class)->validate([
            $this->row(1, 'Euree'),
            $this->row(2, 'Meeraka'),
        ], BookType::MaddraxDieDunkleZukunftDerErde);

        $this->addToAssertionCount(1);
    }

    public function test_rejects_missing_main_series_cycle(): void
    {
        $this->expectException(MaddraxikonCrawlException::class);
        $this->expectExceptionMessage('hat keinen Zyklus');

        app(MaddraxikonDatasetValidator::class)->validate([
            $this->row(1, null),
        ], BookType::MaddraxDieDunkleZukunftDerErde);
    }

    public function test_allows_missing_cycle_for_hardcovers(): void
    {
        app(MaddraxikonDatasetValidator::class)->validate([
            $this->row(1, null),
        ], BookType::MaddraxHardcover);

        $this->addToAssertionCount(1);
    }

    public function test_rejects_duplicate_numbers(): void
    {
        $this->expectException(MaddraxikonCrawlException::class);
        $this->expectExceptionMessage('doppelt');

        app(MaddraxikonDatasetValidator::class)->validate([
            $this->row(1, 'Euree'),
            $this->row(1, 'Euree'),
        ], BookType::MaddraxDieDunkleZukunftDerErde);
    }

    public function test_rejects_loss_against_database_and_active_snapshot(): void
    {
        Book::create([
            'roman_number' => 2,
            'title' => 'Bestand',
            'author' => 'Autor',
            'cycle' => 'Euree',
        ]);
        $validator = app(MaddraxikonDatasetValidator::class);

        try {
            $validator->validate(
                [$this->row(1, 'Euree')],
                BookType::MaddraxDieDunkleZukunftDerErde,
                [$this->row(1, 'Euree'), $this->row(3, 'Meeraka')],
            );
            $this->fail('Expected baseline rejection.');
        } catch (MaddraxikonCrawlException $exception) {
            $this->assertStringContainsString('aktiven Snapshot', $exception->getMessage());
        }

        $this->expectException(MaddraxikonCrawlException::class);
        $this->expectExceptionMessage('Datenbankbestand');
        $validator->validate(
            [$this->row(1, 'Euree')],
            BookType::MaddraxDieDunkleZukunftDerErde,
        );
    }

    /** @return array<string, mixed> */
    private function row(int $number, ?string $cycle): array
    {
        return [
            'nummer' => $number,
            'titel' => "Roman {$number}",
            'zyklus' => $cycle,
            'text' => ['Autor'],
        ];
    }
}
