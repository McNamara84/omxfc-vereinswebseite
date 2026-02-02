<?php

namespace App\Services;

use App\Models\RomanExcerpt;
use Illuminate\Support\Facades\Log;
use TeamTNT\TNTSearch\Exceptions\IndexNotFoundException;

/**
 * Service für die Kompendium-Suche und Indexierung.
 *
 * Abstrahiert die Scout-Suchaufrufe und Index-Operationen für bessere Testbarkeit.
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

    /**
     * Entfernt ein Dokument aus dem TNTSearch-Index.
     *
     * Fängt Fehler ab, wenn der Index nicht existiert oder das Dokument nicht gefunden wird.
     * Wirft keine Exceptions, da das Entfernen eines nicht-existenten Dokuments kein Fehler ist.
     */
    public function removeFromIndex(string $path): void
    {
        try {
            $excerpt = new RomanExcerpt(['path' => $path]);
            $excerpt->unsearchable();
        } catch (IndexNotFoundException) {
            // Index existiert nicht - nichts zu entfernen
            Log::info("Index nicht gefunden, überspringe De-Indexierung für: {$path}");
        } catch (\BadMethodCallException) {
            // Tritt auf wenn Scout gemockt ist (z.B. in Tests)
            Log::info("Scout gemockt, überspringe De-Indexierung für: {$path}");
        }
    }
}
