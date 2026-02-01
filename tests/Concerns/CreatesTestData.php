<?php

namespace Tests\Concerns;

use App\Enums\BookType;
use App\Models\Book;

/**
 * Trait für häufig benötigte Test-Daten.
 *
 * Bietet wiederverwendbare Helper-Methoden zur Erstellung von Test-Daten,
 * z.B. für Romantausch-Tests.
 */
trait CreatesTestData
{
    /**
     * Erstellt Bücher für Romantausch-Tests.
     *
     * Erstellt je ein Buch der Typen:
     * - MaddraxDieDunkleZukunftDerErde (Roman Nr. 1)
     * - MissionMars (Roman Nr. 2)
     * - DasVolkDerTiefe (Roman Nr. 1)
     * - ZweiTausendZwölfDasJahrDerApokalypse (Roman Nr. 1)
     * - DieAbenteurer (Roman Nr. 1)
     */
    protected function seedBooksForRomantausch(): void
    {
        $books = [
            ['roman_number' => 1, 'title' => 'Roman1', 'type' => BookType::MaddraxDieDunkleZukunftDerErde],
            ['roman_number' => 2, 'title' => 'MM Roman', 'type' => BookType::MissionMars],
            ['roman_number' => 1, 'title' => 'Volk Roman', 'type' => BookType::DasVolkDerTiefe],
            ['roman_number' => 1, 'title' => '2012 Roman', 'type' => BookType::ZweiTausendZwölfDasJahrDerApokalypse],
            ['roman_number' => 1, 'title' => 'Abenteurer Roman', 'type' => BookType::DieAbenteurer],
        ];

        foreach ($books as $book) {
            Book::create(array_merge($book, ['author' => 'Author']));
        }
    }

    /**
     * Erstellt einen Maddrax-Roman für Tests.
     *
     * @param  int  $number  Die Romannummer
     * @param  string  $title  Der Titel des Romans
     */
    protected function createMaddraxBook(int $number = 1, string $title = 'Test Roman'): Book
    {
        return Book::create([
            'roman_number' => $number,
            'title' => $title,
            'author' => 'Test Author',
            'type' => BookType::MaddraxDieDunkleZukunftDerErde,
        ]);
    }

    /**
     * Erstellt einen Mission Mars-Roman für Tests.
     *
     * @param  int  $number  Die Romannummer
     * @param  string  $title  Der Titel des Romans
     */
    protected function createMissionMarsBook(int $number = 1, string $title = 'Mission Mars Test'): Book
    {
        return Book::create([
            'roman_number' => $number,
            'title' => $title,
            'author' => 'Test Author',
            'type' => BookType::MissionMars,
        ]);
    }
}
