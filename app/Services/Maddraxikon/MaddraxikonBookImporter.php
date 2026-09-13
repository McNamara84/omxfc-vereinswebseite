<?php

namespace App\Services\Maddraxikon;

use App\Enums\BookType;
use App\Models\Book;
use Illuminate\Support\Facades\DB;

class MaddraxikonBookImporter
{
    private const UPSERT_BATCH_SIZE = 250;

    /** @param array<int|string, array<int, array<string, mixed>>> $datasets */
    public function import(array $datasets, bool $transaction = true): void
    {
        $callback = function () use ($datasets): void {
            foreach ($datasets as $seriesKey => $rows) {
                $seriesKey = (string) $seriesKey;
                $type = BookType::fromKey($seriesKey);

                if ($type === null) {
                    throw new \InvalidArgumentException("Unbekannte Buchreihe: {$seriesKey}");
                }

                $this->importSeries($rows, $type);
            }
        };

        if ($transaction) {
            DB::transaction($callback);

            return;
        }

        $callback();
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function importSeries(array $rows, BookType $type): void
    {
        $numbers = array_map(static fn (array $row): int => (int) $row['nummer'], $rows);
        $existingBooks = Book::query()
            ->where('type', $type)
            ->whereIntegerInRaw('roman_number', $numbers)
            ->get([
                'roman_number',
                'maddraxikon_page_id',
                'maddraxikon_page_title',
                'maddraxikon_page_verified_at',
            ])
            ->keyBy(static fn (Book $book): int => $book->roman_number);
        $values = [];

        foreach ($rows as $row) {
            $authorData = $row['text'] ?? null;
            $author = is_array($authorData)
                ? implode(', ', array_map('strval', $authorData))
                : (string) ($authorData ?? '');
            $pageTitle = is_string($row['maddraxikon_seitentitel'] ?? null)
                ? trim($row['maddraxikon_seitentitel'])
                : null;
            $pageTitle = $pageTitle !== '' ? $pageTitle : null;
            $cycle = is_string($row['zyklus'] ?? null)
                ? trim($row['zyklus'])
                : null;
            $number = (int) $row['nummer'];
            $book = $existingBooks->get($number);
            $pageId = null;
            $pageVerifiedAt = null;

            if (
                $pageTitle !== null
                && $book instanceof Book
                && $book->maddraxikon_page_title === $pageTitle
            ) {
                $pageId = $book->maddraxikon_page_id;
                $pageVerifiedAt = $book->maddraxikon_page_verified_at;
            }

            $values[] = [
                'roman_number' => $number,
                'title' => trim((string) $row['titel']),
                'author' => $author,
                'cycle' => $cycle !== '' ? $cycle : null,
                'type' => $type->value,
                'maddraxikon_page_title' => $pageTitle,
                'maddraxikon_page_id' => $pageId,
                'maddraxikon_page_verified_at' => $pageVerifiedAt,
            ];
        }

        foreach (array_chunk($values, self::UPSERT_BATCH_SIZE) as $batch) {
            Book::query()->upsert(
                $batch,
                ['roman_number', 'type'],
                [
                    'title',
                    'author',
                    'cycle',
                    'maddraxikon_page_title',
                    'maddraxikon_page_id',
                    'maddraxikon_page_verified_at',
                ],
            );
        }
    }
}
