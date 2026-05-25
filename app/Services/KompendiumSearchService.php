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
    /**
     * @return array{requiredTerms: list<string>, requiredPhrases: list<string>, excludedTerms: list<string>, excludedPhrases: list<string>}
     */
    private function emptySearchGroup(): array
    {
        return [
            'requiredTerms' => [],
            'requiredPhrases' => [],
            'excludedTerms' => [],
            'excludedPhrases' => [],
        ];
    }

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
     * Beispiel: '"Matthew Drax" OR Abenteuer NOT Mutation'
     * → gruppiert als ("Matthew Drax") OR (Abenteuer AND NOT Mutation)
     *
     * @return array{
     *     groups: list<array{requiredTerms: list<string>, requiredPhrases: list<string>, excludedTerms: list<string>, excludedPhrases: list<string>}>,
     *     phrases: list<string>,
     *     terms: list<string>,
     *     excludedPhrases: list<string>,
     *     excludedTerms: list<string>,
     *     isPhraseSearch: bool,
     *     usesOrOperator: bool,
     *     usesNotOperator: bool,
     *     hasPositiveOperands: bool
     * }
     */
    public function parseSearchQuery(string $query): array
    {
        // Typografische Anführungszeichen zu geraden normalisieren
        $query = str_replace(["\u{201C}", "\u{201D}", "\u{201E}", "\u{00AB}", "\u{00BB}"], '"', $query);

        $groups = [];
        $currentGroup = $this->emptySearchGroup();
        $usesOrOperator = false;
        $usesNotOperator = false;
        $negateNextOperand = false;

        preg_match_all('/-?"[^"]*"|-?\S+/u', $query, $matches);
        $tokens = $matches[0] ?? [];

        foreach ($tokens as $rawToken) {
            if (preg_match('/^or$/iu', $rawToken) === 1) {
                $usesOrOperator = true;

                if ($this->groupHasOperands($currentGroup)) {
                    $groups[] = $currentGroup;
                }

                $currentGroup = $this->emptySearchGroup();
                $negateNextOperand = false;

                continue;
            }

            if (preg_match('/^and$/iu', $rawToken) === 1) {
                continue;
            }

            if (preg_match('/^not$/iu', $rawToken) === 1) {
                $usesNotOperator = true;
                $negateNextOperand = true;

                continue;
            }

            $token = $rawToken;
            $isNegated = $negateNextOperand;
            $negateNextOperand = false;

            if (str_starts_with($token, '-')) {
                $usesNotOperator = true;
                $isNegated = true;
                $token = mb_substr($token, 1);
            }

            if ($token === '') {
                continue;
            }

            $isPhrase = str_starts_with($token, '"') && str_ends_with($token, '"');
            $value = $isPhrase
                ? trim(mb_substr($token, 1, -1))
                : trim($token);

            if (mb_strlen($value) < 2) {
                continue;
            }

            $normalizedValue = mb_strtolower($value);

            $bucket = match (true) {
                $isPhrase && $isNegated => 'excludedPhrases',
                $isPhrase => 'requiredPhrases',
                $isNegated => 'excludedTerms',
                default => 'requiredTerms',
            };

            if (! in_array($normalizedValue, $currentGroup[$bucket], true)) {
                $currentGroup[$bucket][] = $normalizedValue;
            }
        }

        if ($this->groupHasOperands($currentGroup)) {
            $groups[] = $currentGroup;
        }

        $phrases = [];
        $terms = [];
        $excludedPhrases = [];
        $excludedTerms = [];

        foreach ($groups as $group) {
            $phrases = array_merge($phrases, $group['requiredPhrases']);
            $terms = array_merge($terms, $group['requiredTerms']);
            $excludedPhrases = array_merge($excludedPhrases, $group['excludedPhrases']);
            $excludedTerms = array_merge($excludedTerms, $group['excludedTerms']);
        }

        $phrases = array_values(array_unique($phrases));
        $terms = array_values(array_unique($terms));
        $excludedPhrases = array_values(array_unique($excludedPhrases));
        $excludedTerms = array_values(array_unique($excludedTerms));

        return [
            'groups' => $groups,
            'phrases' => $phrases,
            'terms' => $terms,
            'excludedPhrases' => $excludedPhrases,
            'excludedTerms' => $excludedTerms,
            'isPhraseSearch' => count($phrases) > 0 || count($excludedPhrases) > 0,
            'usesOrOperator' => $usesOrOperator,
            'usesNotOperator' => $usesNotOperator,
            'hasPositiveOperands' => $this->hasPositiveOperands([
                'phrases' => $phrases,
                'terms' => $terms,
            ]),
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
     * @param  array{phrases?: list<string>, terms?: list<string>}  $parsed
     */
    public function hasPositiveOperands(array $parsed): bool
    {
        return ! empty($parsed['phrases']) || ! empty($parsed['terms']);
    }

    /**
     * @param  array{
     *     groups?: list<array{requiredTerms: list<string>, requiredPhrases: list<string>, excludedTerms: list<string>, excludedPhrases: list<string>}>,
     *     terms?: list<string>,
     *     phrases?: list<string>
     * }  $parsed
     */
    public function matchesText(string $text, array $parsed): bool
    {
        if (! $this->hasPositiveOperands($parsed)) {
            return false;
        }

        if ($this->containsExcludedOperands(
            $text,
            $parsed['excludedTerms'] ?? [],
            $parsed['excludedPhrases'] ?? [],
        )) {
            return false;
        }

        $groups = $parsed['groups'] ?? [];

        if ($groups === []) {
            $groups = [[
                'requiredTerms' => $parsed['terms'] ?? [],
                'requiredPhrases' => $parsed['phrases'] ?? [],
                'excludedTerms' => [],
                'excludedPhrases' => [],
            ]];
        }

        foreach ($groups as $group) {
            if ($this->groupMatchesText($text, $group)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $excludedTerms
     * @param  list<string>  $excludedPhrases
     */
    private function containsExcludedOperands(string $text, array $excludedTerms, array $excludedPhrases): bool
    {
        foreach ($excludedPhrases as $phrase) {
            if (mb_stripos($text, $phrase) !== false) {
                return true;
            }
        }

        foreach ($excludedTerms as $term) {
            if (mb_stripos($text, $term) !== false) {
                return true;
            }
        }

        return false;
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
     * @param  array{requiredTerms: list<string>, requiredPhrases: list<string>, excludedTerms: list<string>, excludedPhrases: list<string>}  $group
     */
    private function groupMatchesText(string $text, array $group): bool
    {
        foreach ($group['excludedPhrases'] as $phrase) {
            if (mb_stripos($text, $phrase) !== false) {
                return false;
            }
        }

        foreach ($group['excludedTerms'] as $term) {
            if (mb_stripos($text, $term) !== false) {
                return false;
            }
        }

        foreach ($group['requiredPhrases'] as $phrase) {
            if (mb_stripos($text, $phrase) === false) {
                return false;
            }
        }

        foreach ($group['requiredTerms'] as $term) {
            if (mb_stripos($text, $term) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{requiredTerms: list<string>, requiredPhrases: list<string>, excludedTerms: list<string>, excludedPhrases: list<string>}  $group
     */
    private function groupHasOperands(array $group): bool
    {
        return $group['requiredTerms'] !== []
            || $group['requiredPhrases'] !== []
            || $group['excludedTerms'] !== []
            || $group['excludedPhrases'] !== [];
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
