<?php

namespace App\Services;

use App\Models\RomanExcerpt;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Engines\TypesenseEngine;
use Laravel\Scout\EngineManager;
use Typesense\Exceptions\ObjectNotFound;

/**
 * Service für die Kompendium-Suche und Indexierung.
 *
 * Abstrahiert die Scout-Suchaufrufe und Index-Operationen für bessere Testbarkeit.
 */
class KompendiumSearchService
{
    public function indexExists(): bool
    {
        $indexName = (new RomanExcerpt)->searchableAs();
        $driver = config('scout.driver');

        if ($driver === 'tntsearch') {
            return $this->legacyIndexExists($indexName);
        }

        if ($driver !== 'typesense') {
            return false;
        }

        $engine = app(EngineManager::class)->engine();

        if ($engine instanceof TypesenseEngine) {
            return $this->typesenseCollectionExists($engine, $indexName);
        }

        return false;
    }

    /**
     * Führt eine Volltextsuche in den Romantexten durch.
     *
     * @return array{total: int, paths: list<string>, raw: array<mixed>}
     */
    public function search(string $query): array
    {
        $raw = RomanExcerpt::search($query)->raw();

        return [
            'total' => $this->extractTotal($raw),
            'paths' => $this->extractPaths($raw),
            'raw' => $raw,
        ];
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

        // Typografische Anführungszeichen zu geraden normalisieren
        $query = str_replace(["\u{201C}", "\u{201D}", "\u{201E}", "\u{00AB}", "\u{00BB}"], '"', $query);

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
     * Entfernt ein Dokument aus dem aktuell konfigurierten Scout-Suchindex.
     *
     * Das Entfernen ist für die unterstützten idempotenten "nicht gefunden"-Fälle bewusst tolerant:
     * - Typesense: ObjectNotFound beim Delete wird als bereits entfernt behandelt.
     * - TNTSearch (Legacy): ein fehlender Index wird aus Kompatibilitätsgründen ignoriert.
     *
     * Andere Treiber-, Konfigurations- oder Verbindungsfehler werden nicht unterdrückt,
     * damit sie im Betrieb sichtbar bleiben.
     */
    public function removeFromIndex(string $path): void
    {
        try {
            $excerpt = new RomanExcerpt(['path' => $path]);
            $excerpt->unsearchableSync();
        } catch (\BadMethodCallException) {
            // Tritt auf wenn Scout gemockt ist (z.B. in Tests)
            Log::info("Scout gemockt, überspringe De-Indexierung für: {$path}");
        } catch (\Throwable $exception) {
            if ($this->isTypesenseMissingDocumentException($exception)) {
                Log::info("Dokument nicht gefunden, überspringe De-Indexierung für: {$path}");

                return;
            }

            if ($this->isLegacyMissingIndexException($exception)) {
                Log::info("Index nicht gefunden, überspringe De-Indexierung für: {$path}");

                return;
            }

            throw $exception;
        }
    }

    private function legacyIndexExists(string $indexName): bool
    {
        $storagePath = config('scout.tntsearch.storage');

        if (! is_string($storagePath) || $storagePath === '') {
            return false;
        }

        return file_exists($storagePath.DIRECTORY_SEPARATOR.$indexName.'.index');
    }

    private function typesenseCollectionExists(TypesenseEngine $engine, string $indexName): bool
    {
        try {
            $engine->getCollections()->{$indexName}->retrieve();

            return true;
        } catch (ObjectNotFound) {
            return false;
        }
    }

    private function isLegacyMissingIndexException(\Throwable $exception): bool
    {
        return config('scout.driver') === 'tntsearch'
            && str_ends_with($exception::class, 'IndexNotFoundException');
    }

    private function isTypesenseMissingDocumentException(\Throwable $exception): bool
    {
        return config('scout.driver') === 'typesense'
            && $exception instanceof ObjectNotFound;
    }

    /**
     * @param  array<mixed>  $raw
     * @return list<string>
     */
    private function extractPaths(array $raw): array
    {
        if (isset($raw['paths']) && is_array($raw['paths'])) {
            return array_values(array_filter($raw['paths'], 'is_string'));
        }

        if (isset($raw['ids']) && is_array($raw['ids'])) {
            return array_values(array_filter($raw['ids'], 'is_string'));
        }

        if (! isset($raw['hits']) || ! is_array($raw['hits'])) {
            return [];
        }

        $paths = [];

        foreach ($raw['hits'] as $hit) {
            if (! is_array($hit)) {
                continue;
            }

            $document = $hit['document'] ?? $hit;

            if (is_array($document) && isset($document['path']) && is_string($document['path'])) {
                $paths[] = $document['path'];
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  array<mixed>  $raw
     */
    private function extractTotal(array $raw): int
    {
        if (isset($raw['total']) && is_numeric($raw['total'])) {
            return (int) $raw['total'];
        }

        if (isset($raw['found']) && is_numeric($raw['found'])) {
            return (int) $raw['found'];
        }

        if (isset($raw['hits']['total_hits']) && is_numeric($raw['hits']['total_hits'])) {
            return (int) $raw['hits']['total_hits'];
        }

        return count($this->extractPaths($raw));
    }
}
