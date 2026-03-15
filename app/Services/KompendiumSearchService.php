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
     * Zerlegt den Suchbegriff in Phrasen (in Anführungszeichen) und freie Begriffe.
     *
     * Beispiel: '"Matthew Drax" Abenteuer "Volk der Tiefe"'
     * → ['phrases' => ['matthew drax', 'volk der tiefe'], 'terms' => ['abenteuer'], 'isPhraseSearch' => true]
     *
     * @return array{phrases: list<string>, terms: list<string>, isPhraseSearch: bool}
     */
    public function parseSearchQuery(string $query): array
    {
        $phrases = [];
        $remaining = $query;

        // Phrasen in Anführungszeichen extrahieren
        if (preg_match_all('/"([^"]+)"/', $query, $matches)) {
            foreach ($matches[1] as $phrase) {
                $trimmed = trim($phrase);
                if (mb_strlen($trimmed) >= 2) {
                    $phrases[] = mb_strtolower($trimmed);
                }
            }
        }

        // Alle Quoted-Bereiche (inkl. leere Quotes) aus dem Query entfernen
        $remaining = preg_replace('/"[^"]*"/', '', $query);

        // Freie Begriffe aus dem Rest extrahieren
        $terms = array_values(array_filter(
            preg_split('/\s+/', trim($remaining)),
            fn ($term) => mb_strlen($term) >= 2
        ));
        $terms = array_map('mb_strtolower', $terms);

        return [
            'phrases' => $phrases,
            'terms' => $terms,
            'isPhraseSearch' => count($phrases) > 0,
        ];
    }

    /**
     * Baut den TNTSearch-Query-String aus geparsten Phrasen und Begriffen.
     *
     * Alle Wörter (aus Phrasen aufgesplittet + freie Begriffe) werden als
     * Einzelwörter an TNTSearch gesendet, um die Kandidatenmenge zu maximieren.
     */
    public function buildTntSearchQuery(array $parsed): string
    {
        $allWords = [];

        foreach ($parsed['phrases'] as $phrase) {
            $allWords = array_merge($allWords, preg_split('/\s+/', $phrase));
        }

        $allWords = array_merge($allWords, $parsed['terms']);

        return implode(' ', array_unique(array_filter($allWords)));
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
