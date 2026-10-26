<?php

namespace App\Services\Profile;

use App\Enums\BookType;
use App\Models\Book;
use App\Services\MaddraxDataService;

class SeriesProfileOptions
{
    /** @return array<string, array<int, string>> */
    public function all(): array
    {
        $romane = MaddraxDataService::getRomane();
        $hardcovers = Book::where('type', BookType::MaddraxHardcover)->orderBy('roman_number')->get();

        $options = [
            'einstiegsroman' => $romane,
            'lesestand' => $romane,
            'lieblingsautor' => MaddraxDataService::getAutoren(),
            'lieblingsroman' => $romane,
            'lieblingshardcover' => $hardcovers->map(fn ($book) => ($book->roman_number ? $book->roman_number.' - ' : '').$book->title)->all(),
            'lieblingscover' => array_merge(
                array_map(fn ($roman) => 'MX '.implode(' ', explode(' - ', $roman, 2)), $romane),
                $hardcovers->whereNotNull('roman_number')->map(fn ($book) => 'HC '.$book->roman_number.' '.$book->title)->values()->all(),
            ),
            'lieblingszyklus' => MaddraxDataService::getZyklen(),
            'lieblingsfigur' => MaddraxDataService::getFiguren(),
            'lieblingsschauplatz' => MaddraxDataService::getSchauplaetze(),
            'lieblingsthema' => MaddraxDataService::getSchlagworte(),
        ];

        // Catalog rows may lack metadata. Such gaps are not selectable favorites.
        return array_map(fn (array $values): array => array_values(array_unique(array_filter(
            array_map(SeriesProfileCompletion::normalize(...), $values),
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        ))), $options);
    }
}
