<?php

namespace Tests\Concerns;

use App\Enums\BookType;
use App\Models\Book;
use App\Models\BookCover;

trait CreatesBildersucheCover
{
    private function searchCover(int $number, array $keywords, BookType $type = BookType::MaddraxDieDunkleZukunftDerErde): BookCover
    {
        $book = Book::factory()->create(['roman_number' => $number, 'type' => $type, 'title' => 'Testroman '.$number]);
        $cover = BookCover::factory()->for($book)->create([
            'source_file_title' => "Datei:{$type->key()}-{$number}.jpg",
            'keywords_source_file_title' => "Datei:{$type->key()}-{$number}.jpg",
            'keywords_synced_at' => now(),
        ]);
        foreach ($keywords as $keyword) {
            $cover->keywords()->create(['keyword' => $keyword, 'normalized_keyword' => mb_strtolower($keyword)]);
        }

        return $cover;
    }

    private function exampleCovers(): array
    {
        return [
            $this->searchCover(1, ['Aruula', 'Eisgebirge', 'F-17 Alpha 2', 'Matthew Drax', 'Taratze', 'Schwert']),
            $this->searchCover(2, ['Bolluna', 'Matthew Drax', 'Jandra', 'Wulfane']),
        ];
    }
}
