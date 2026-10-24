<?php

namespace App\Services\Romantausch;

use App\Models\Book;
use App\Models\BookOffer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Nummernparser, Darstellung und Buchabfragen für Stapelangebote.
 * Schreiboperationen und Transaktionen liegen im BundleWorkflow.
 */
class BundleService
{
    /**
     * Maximale Spanne für einen Nummernbereich (z.B. 1-500 erlaubt, 1-501 nicht).
     */
    public const MAX_RANGE_SPAN = 500;

    /**
     * Minimale Anzahl Bücher pro Bundle.
     */
    public const MIN_BUNDLE_SIZE = 2;

    /**
     * Maximale Anzahl Bücher pro Bundle (Performance-Limit).
     */
    public const MAX_BUNDLE_SIZE = 200;

    /**
     * Zustandswerte in der Reihenfolge von best (0) bis schlechtester (8).
     */
    public const CONDITION_ORDER = ['Z0', 'Z0-1', 'Z1', 'Z1-2', 'Z2', 'Z2-3', 'Z3', 'Z3-4', 'Z4'];

    /**
     * Parst eine Eingabe wie "1, 5, 7, 12-50, 52" in ein Array von Nummern.
     *
     * @return array<int>
     */
    public function parseBookNumbers(string $input): array
    {
        $numbers = [];
        $parts = array_map('trim', explode(',', $input));
        $skippedParts = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (str_contains($part, '-')) {
                $rangeNumbers = $this->parseRange($part);
                if ($rangeNumbers === null) {
                    $skippedParts[] = $part;
                } else {
                    $numbers = array_merge($numbers, $rangeNumbers);
                }
            } else {
                $num = $this->parseSingleNumber($part);
                if ($num === null) {
                    $skippedParts[] = $part;
                } elseif ($num > 0) {
                    $numbers[] = $num;
                }
            }
        }

        // Logging für ungewöhnliche Eingabemuster
        if (! empty($skippedParts)) {
            Log::info('parseBookNumbers: Ungültige Eingabeteile übersprungen', [
                'skipped_parts' => array_slice($skippedParts, 0, 10),
                'total_skipped' => count($skippedParts),
                'user_id' => Auth::id(),
            ]);
        }

        return array_values(array_unique($numbers));
    }

    /**
     * Parst einen Bereich wie "12-50".
     *
     * @return array<int>|null Array von Nummern oder null bei ungültiger Eingabe
     */
    private function parseRange(string $part): ?array
    {
        $rangeParts = explode('-', $part, 2);
        $start = $this->parseSingleNumber($rangeParts[0]);
        $end = $this->parseSingleNumber($rangeParts[1]);

        if ($start === null || $end === null) {
            return null;
        }

        if ($start <= 0 || $end <= 0 || $end < $start || ($end - $start) > self::MAX_RANGE_SPAN) {
            return null;
        }

        return range($start, $end);
    }

    /**
     * Parst eine einzelne Nummer mit führenden Nullen.
     */
    private function parseSingleNumber(string $part): ?int
    {
        $normalized = ltrim(trim($part), '0') ?: '0';
        $num = filter_var($normalized, FILTER_VALIDATE_INT);

        return $num === false ? null : $num;
    }

    /**
     * Formatiert eine Sammlung von Angeboten als kompakte Nummernbereiche.
     * z.B. Angebote mit book_number [1,2,3,5,7,8,9] => "1-3, 5, 7-9"
     */
    public function formatBookNumbersRange(Collection $offers): string
    {
        $numbers = $offers->pluck('book_number')->sort()->values()->toArray();

        if (empty($numbers)) {
            return '';
        }

        $ranges = [];
        $start = $numbers[0];
        $end = $numbers[0];

        for ($i = 1; $i < count($numbers); $i++) {
            if ($numbers[$i] === $end + 1) {
                $end = $numbers[$i];
            } else {
                $ranges[] = $start === $end ? (string) $start : "$start-$end";
                $start = $numbers[$i];
                $end = $numbers[$i];
            }
        }
        $ranges[] = $start === $end ? (string) $start : "$start-$end";

        return implode(', ', $ranges);
    }

    /**
     * Validiert den Zustandsbereich.
     *
     * @return string|null Fehlermeldung oder null wenn gültig
     */
    public function validateConditionRange(string $condition, ?string $conditionMax): ?string
    {
        if (empty($conditionMax)) {
            return null;
        }

        $conditionIndex = array_search($condition, self::CONDITION_ORDER);
        $conditionMaxIndex = array_search($conditionMax, self::CONDITION_ORDER);

        if ($conditionIndex === false) {
            $allowed = implode(', ', self::CONDITION_ORDER);

            return "Unbekannter Zustandswert '{$condition}'. Erlaubt sind: {$allowed}";
        }

        if ($conditionMaxIndex === false) {
            $allowed = implode(', ', self::CONDITION_ORDER);

            return "Unbekannter Zustandswert '{$conditionMax}'. Erlaubt sind: {$allowed}";
        }

        if ($conditionMaxIndex < $conditionIndex) {
            return 'Der "Bis"-Zustand muss gleich oder schlechter als der "Von"-Zustand sein.';
        }

        return null;
    }

    /**
     * Validiert fehlende Buchnummern.
     *
     * @return string|null Formatierte Liste fehlender Nummern oder null
     */
    public function validateMissingBookNumbers(array $requestedNumbers, array $existingNumbers): ?string
    {
        $missingNumbers = array_diff($requestedNumbers, $existingNumbers);

        if (empty($missingNumbers)) {
            return null;
        }

        $missingList = implode(', ', array_slice($missingNumbers, 0, 10));
        if (count($missingNumbers) > 10) {
            $missingList .= ' ... ('.count($missingNumbers).' insgesamt)';
        }

        return $missingList;
    }

    /**
     * Holt existierende Bücher aus der Datenbank.
     *
     * @return Collection<int, Book> Key = roman_number
     */
    public function getExistingBooks(string $series, array $bookNumbers): Collection
    {
        return Book::where('type', $series)
            ->whereIn('roman_number', $bookNumbers)
            ->get()
            ->keyBy('roman_number');
    }

    /**
     * Prüft ob ein Bundle aktive Swaps hat.
     */
    public function bundleHasActiveSwaps(string $bundleId, int $userId): bool
    {
        return BookOffer::where('bundle_id', $bundleId)
            ->where('user_id', $userId)
            ->whereHas('swap')
            ->exists();
    }
}
