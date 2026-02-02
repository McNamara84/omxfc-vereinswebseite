<?php

namespace App\Services;

use App\Models\RomanExcerpt;

/**
 * Service für die Kompendium-Suche.
 *
 * Abstrahiert die Scout-Suchaufrufe für bessere Testbarkeit.
 */
class KompendiumSearchService
{
    /**
     * Führt eine Volltextsuche in den Romantexten durch.
     *
     * @return array{hits: array{total_hits: int}, ids: array<string>}
     */
    public function search(string $query): array
    {
        return RomanExcerpt::search($query)->raw();
    }
}
