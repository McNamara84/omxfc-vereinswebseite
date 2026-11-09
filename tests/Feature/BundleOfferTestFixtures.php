<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookType;
use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;

trait BundleOfferTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    /**
     * Erstellt Test-Buchdaten für Maddrax-Serie (1-100) und Mission Mars (1).
     *
     * HINWEIS: Diese Methode verwendet Book::create() ohne exists()-Check.
     * Das ist akzeptabel weil:
     * 1. RefreshDatabase-Trait migriert die DB vor jedem Test neu
     * 2. Jeder Test startet mit leerer Datenbank
     * 3. putBookData() wird nur einmal pro Test aufgerufen
     *
     * Falls Tests ohne RefreshDatabase laufen, würden Duplicate-Key-Errors auftreten.
     */
    private function putBookData(): void
    {
        for ($i = 1; $i <= 100; $i++) {
            Book::create(['roman_number' => $i, 'title' => "Maddrax {$i}", 'author' => 'Author', 'type' => BookType::MaddraxDieDunkleZukunftDerErde]);
        }
        Book::create(['roman_number' => 1, 'title' => 'Mission Mars 1', 'author' => 'Author', 'type' => BookType::MissionMars]);
    }
}
