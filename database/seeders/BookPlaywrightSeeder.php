<?php

namespace Database\Seeders;

use App\Enums\BookType;
use App\Models\Book;
use Illuminate\Database\Seeder;

/**
 * Seeder für Playwright E2E-Tests.
 * Erstellt eine minimale Anzahl von Büchern für die Romantauschbörse-Tests.
 */
class BookPlaywrightSeeder extends Seeder
{
    public function run(): void
    {
        // Erstelle 100 Maddrax-Romane für Bundle-Tests
        for ($i = 1; $i <= 100; $i++) {
            Book::firstOrCreate(
                [
                    'type' => BookType::MaddraxDieDunkleZukunftDerErde,
                    'roman_number' => $i,
                ],
                [
                    'title' => "Maddrax {$i}",
                    'author' => 'Various',
                ]
            );
        }

        // Erstelle einige Mission Mars Romane
        for ($i = 1; $i <= 10; $i++) {
            Book::firstOrCreate(
                [
                    'type' => BookType::MissionMars,
                    'roman_number' => $i,
                ],
                [
                    'title' => "Mission Mars {$i}",
                    'author' => 'Various',
                ]
            );
        }
    }
}
