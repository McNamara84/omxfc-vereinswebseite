<?php

namespace App\Services\Maddraxikon;

use App\Enums\BookType;
use App\Exceptions\MaddraxikonCrawlException;
use App\Models\Book;
use Illuminate\Support\Facades\Schema;

class MaddraxikonDatasetValidator
{
    /**
     * @param  array<int, mixed>  $rows
     * @param  array<int, mixed>|null  $baseline
     */
    public function validate(
        array $rows,
        BookType $type,
        ?array $baseline = null,
        bool $checkDatabaseCoverage = true,
    ): void {
        if ($rows === [] || ! array_is_list($rows)) {
            throw new MaddraxikonCrawlException(
                "Der Kandidat für {$type->label()} ist leer oder keine JSON-Liste."
            );
        }

        $numbers = [];

        foreach ($rows as $position => $row) {
            if (! is_array($row)) {
                throw new MaddraxikonCrawlException(
                    "Ungültiger Datensatz an Position {$position} für {$type->label()}."
                );
            }

            $number = filter_var($row['nummer'] ?? null, FILTER_VALIDATE_INT);
            $title = is_string($row['titel'] ?? null) ? trim($row['titel']) : '';
            $cycle = is_string($row['zyklus'] ?? null) ? trim($row['zyklus']) : '';

            if ($number === false || $number <= 0 || $title === '') {
                throw new MaddraxikonCrawlException(
                    "Roman an Position {$position} hat keine positive Nummer oder keinen Titel."
                );
            }

            if (isset($numbers[$number])) {
                throw new MaddraxikonCrawlException(
                    "Romanummer {$number} ist im Kandidaten für {$type->label()} doppelt enthalten."
                );
            }

            if (MaddraxikonSeries::requiresCycle($type) && $cycle === '') {
                throw new MaddraxikonCrawlException(
                    "MADDRAX-Roman {$number} hat keinen Zyklus."
                );
            }

            $numbers[$number] = true;
        }

        if ($baseline !== null) {
            $missingFromBaseline = array_diff(
                $this->validNumbers($baseline),
                array_keys($numbers),
            );

            if ($missingFromBaseline !== []) {
                throw new MaddraxikonCrawlException(
                    $this->coverageMessage($type, $missingFromBaseline, 'aktivem Snapshot')
                );
            }
        }

        if (! $checkDatabaseCoverage || ! Schema::hasTable('books')) {
            return;
        }

        $existingNumbers = Book::query()
            ->where('type', $type)
            ->pluck('roman_number')
            ->map(static fn (mixed $number): int => (int) $number)
            ->all();
        $missingFromDatabase = array_diff($existingNumbers, array_keys($numbers));

        if ($missingFromDatabase !== []) {
            throw new MaddraxikonCrawlException(
                $this->coverageMessage($type, $missingFromDatabase, 'Datenbankbestand')
            );
        }
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return list<int>
     */
    private function validNumbers(array $rows): array
    {
        $numbers = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $number = filter_var($row['nummer'] ?? null, FILTER_VALIDATE_INT);

            if ($number !== false && $number > 0) {
                $numbers[] = $number;
            }
        }

        return array_values(array_unique($numbers));
    }

    /** @param array<int, int|string> $missing */
    private function coverageMessage(BookType $type, array $missing, string $source): string
    {
        sort($missing);
        $sample = implode(', ', array_slice($missing, 0, 20));

        return "Der Kandidat für {$type->label()} verliert gegenüber dem {$source} ".
            count($missing)." Roman(e), darunter: {$sample}.";
    }
}
