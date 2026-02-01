<?php

namespace App\Services\Romantausch;

use App\Models\Activity;
use App\Models\Book;
use App\Models\BookOffer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Service für die Verwaltung von Stapel-Angeboten (Bundles) in der Romantauschbörse.
 *
 * Verantwortlich für:
 * - Erstellen von Bundles (mehrere BookOffers mit gleicher bundle_id)
 * - Aktualisieren von Bundles (Bücher hinzufügen/entfernen, Zustand ändern)
 * - Löschen von Bundles
 * - Parsen von Buchnummern-Eingaben
 * - Formatieren von Buchnummern-Bereichen
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

    public function __construct(
        private readonly BookPhotoService $photoService,
        private readonly SwapMatchingService $matchingService,
    ) {}

    /**
     * Erstellt ein neues Bundle mit mehreren Angeboten.
     *
     * @param  string  $series  Die Serien-Kennung (BookType value)
     * @param  array<int>  $bookNumbers  Die Buchnummern
     * @param  string  $condition  Zustand (von)
     * @param  string|null  $conditionMax  Zustand (bis), optional
     * @param  array<string>  $photoPaths  Bereits hochgeladene Foto-Pfade
     * @param  int  $userId  User-ID des Erstellers
     * @return array{offers: array<BookOffer>, bundle_id: string}
     *
     * @throws \RuntimeException Bei Fehlern
     */
    public function createBundle(
        string $series,
        array $bookNumbers,
        string $condition,
        ?string $conditionMax,
        array $photoPaths,
        int $userId
    ): array {
        $existingBooks = $this->getExistingBooks($series, $bookNumbers);
        $bundleId = Str::uuid()->toString();

        try {
            $offers = DB::transaction(function () use ($existingBooks, $series, $condition, $conditionMax, $bundleId, $photoPaths, $userId) {
                $offers = [];

                foreach ($existingBooks as $book) {
                    $offers[] = BookOffer::create([
                        'user_id' => $userId,
                        'bundle_id' => $bundleId,
                        'series' => $series,
                        'book_number' => $book->roman_number,
                        'book_title' => $book->title,
                        'condition' => $condition,
                        'condition_max' => $conditionMax,
                        'photos' => $photoPaths,
                    ]);
                }

                return $offers;
            });
        } catch (\Throwable $e) {
            // Bei Transaktionsfehler: Hochgeladene Fotos aufräumen
            $this->photoService->deletePhotos($photoPaths);

            Log::error('Bundle-Erstellung fehlgeschlagen', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Beim Erstellen des Stapel-Angebots ist ein Fehler aufgetreten.', 0, $e);
        }

        // Punkte vergeben
        $this->awardPointsForOffers($userId, count($offers));

        // Matching für alle Angebote durchführen
        foreach ($offers as $offer) {
            $this->matchingService->matchSwap($offer, 'offer');
        }

        // Activity-Log erstellen
        $this->createBundleActivity($userId, $offers[0]->id);

        return [
            'offers' => $offers,
            'bundle_id' => $bundleId,
        ];
    }

    /**
     * Aktualisiert ein bestehendes Bundle.
     *
     * @param  string  $bundleId  Die Bundle-ID
     * @param  array<int>  $newBookNumbers  Die neuen Buchnummern
     * @param  string  $condition  Zustand (von)
     * @param  string|null  $conditionMax  Zustand (bis)
     * @param  array<string>  $allPhotos  Alle Foto-Pfade (behalten + neu)
     * @param  array<string>  $photosToDelete  Zu löschende Foto-Pfade
     * @param  int  $userId  User-ID
     * @return array{added: int, removed: int, updated: int}
     */
    public function updateBundle(
        string $bundleId,
        array $newBookNumbers,
        string $condition,
        ?string $conditionMax,
        array $allPhotos,
        array $photosToDelete,
        int $userId
    ): array {
        $existingOffers = BookOffer::where('bundle_id', $bundleId)
            ->where('user_id', $userId)
            ->get();

        if ($existingOffers->isEmpty()) {
            throw new \RuntimeException('Bundle nicht gefunden.');
        }

        $series = $existingOffers->first()->series;
        $existingBooks = $this->getExistingBooks($series, $newBookNumbers);
        $currentNumbers = $existingOffers->pluck('book_number')->toArray();

        $toRemove = array_diff($currentNumbers, $newBookNumbers);
        $toAdd = array_diff($newBookNumbers, $currentNumbers);
        $toUpdate = array_intersect($currentNumbers, $newBookNumbers);

        $stats = ['added' => 0, 'removed' => 0, 'updated' => 0];

        DB::transaction(function () use ($existingOffers, $existingBooks, $toRemove, $toAdd, $toUpdate, $condition, $conditionMax, $bundleId, $allPhotos, $userId, &$stats) {
            // Angebote entfernen
            foreach ($existingOffers as $offer) {
                if (in_array($offer->book_number, $toRemove)) {
                    $this->deleteOfferWithSwap($offer);
                    $stats['removed']++;
                } elseif (in_array($offer->book_number, $toUpdate)) {
                    $offer->update([
                        'condition' => $condition,
                        'condition_max' => $conditionMax,
                        'photos' => $allPhotos,
                    ]);
                    $stats['updated']++;
                }
            }

            // Neue Angebote hinzufügen
            foreach ($toAdd as $bookNumber) {
                $book = $existingBooks->get($bookNumber);
                if ($book) {
                    $newOffer = BookOffer::create([
                        'user_id' => $userId,
                        'bundle_id' => $bundleId,
                        'series' => $book->type->value,
                        'book_number' => $book->roman_number,
                        'book_title' => $book->title,
                        'condition' => $condition,
                        'condition_max' => $conditionMax,
                        'photos' => $allPhotos,
                    ]);

                    $this->matchingService->matchSwap($newOffer, 'offer');
                    $stats['added']++;
                }
            }
        });

        // Fotos erst nach erfolgreicher Transaktion löschen
        DB::afterCommit(function () use ($photosToDelete, $allPhotos) {
            foreach ($photosToDelete as $path) {
                if (! in_array($path, $allPhotos, true)) {
                    $this->photoService->deletePhoto($path);
                }
            }
        });

        return $stats;
    }

    /**
     * Löscht ein komplettes Bundle.
     *
     * @param  string  $bundleId  Die Bundle-ID
     * @param  int  $userId  User-ID
     * @return int Anzahl gelöschter Angebote
     */
    public function deleteBundle(string $bundleId, int $userId): int
    {
        $offers = BookOffer::where('bundle_id', $bundleId)
            ->where('user_id', $userId)
            ->get();

        if ($offers->isEmpty()) {
            throw new \RuntimeException('Bundle nicht gefunden.');
        }

        $firstOffer = $offers->first();
        $photosToDelete = $firstOffer->photos ?? [];
        $count = $offers->count();

        DB::transaction(function () use ($offers) {
            foreach ($offers as $offer) {
                $this->deleteOfferWithSwap($offer);
            }
        });

        // Fotos nach erfolgreicher Löschung entfernen
        $this->photoService->deletePhotos($photosToDelete);

        return $count;
    }

    /**
     * Löscht ein Angebot und dessen zugehörigen Swap.
     */
    private function deleteOfferWithSwap(BookOffer $offer): void
    {
        if ($offer->swap) {
            $this->matchingService->deleteSwapWithActivity($offer->swap);
        }
        $offer->delete();
    }

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

    /**
     * Vergibt Punkte basierend auf der Gesamtanzahl der Angebote.
     */
    private function awardPointsForOffers(int $userId, int $newOfferCount): void
    {
        $totalOfferCount = BookOffer::where('user_id', $userId)->count();
        $previousCount = $totalOfferCount - $newOfferCount;
        $newBaxx = intdiv($totalOfferCount, 10) - intdiv($previousCount, 10);

        if ($newBaxx > 0) {
            $user = \App\Models\User::find($userId);
            $user?->incrementTeamPoints($newBaxx);
        }
    }

    /**
     * Erstellt einen Activity-Log-Eintrag für ein neues Bundle.
     */
    private function createBundleActivity(int $userId, int $firstOfferId): void
    {
        Activity::create([
            'user_id' => $userId,
            'subject_type' => BookOffer::class,
            'subject_id' => $firstOfferId,
            'action' => 'bundle_created',
        ]);
    }
}
