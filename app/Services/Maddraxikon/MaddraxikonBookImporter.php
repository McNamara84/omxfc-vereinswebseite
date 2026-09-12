<?php

namespace App\Services\Maddraxikon;

use App\Enums\BookType;
use App\Models\Book;
use Illuminate\Support\Facades\DB;

class MaddraxikonBookImporter
{
    /** @param array<string, array<int, array<string, mixed>>> $datasets */
    public function import(array $datasets, bool $transaction = true): void
    {
        $callback = function () use ($datasets): void {
            foreach ($datasets as $seriesKey => $rows) {
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
        foreach ($rows as $row) {
            $authorData = $row['text'] ?? null;
            $author = is_array($authorData)
                ? implode(', ', array_map('strval', $authorData))
                : (string) ($authorData ?? '');
            $pageTitle = is_string($row['maddraxikon_seitentitel'] ?? null)
                ? trim($row['maddraxikon_seitentitel'])
                : '';
            $cycle = is_string($row['zyklus'] ?? null)
                ? trim($row['zyklus'])
                : null;
            $book = Book::firstOrNew([
                'roman_number' => (int) $row['nummer'],
                'type' => $type,
            ]);
            $updates = [
                'title' => trim((string) $row['titel']),
                'author' => $author,
                'cycle' => $cycle !== '' ? $cycle : null,
                'type' => $type,
            ];

            if ($pageTitle !== '') {
                $updates['maddraxikon_page_title'] = $pageTitle;

                if ($book->maddraxikon_page_title !== $pageTitle) {
                    $updates['maddraxikon_page_id'] = null;
                    $updates['maddraxikon_page_verified_at'] = null;
                }
            }

            $book->fill($updates)->save();
        }
    }
}
