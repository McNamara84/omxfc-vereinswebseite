<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\KompendiumSearchService;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\CoversMethod;
use Tests\Support\PhpunitMetadata;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(KompendiumSearchQueryParserTestFixtures::class);

beforeEach(function (): void {
    $this->service = new KompendiumSearchService;
});

test('hybrid search is limited to simple positive typesense queries', function (): void {
    Config::set('kompendium.search.mode', 'hybrid');
    Config::set('kompendium.search.index_variant', 'hybrid');
    Config::set('scout.driver', 'typesense');
    expect($this->service->searchModeFor($this->service->parseSearchQuery('Aruula')))->toBe('hybrid')
        ->and($this->service->searchModeFor($this->service->parseSearchQuery('"Matthew Drax"')))->toBe('lexical')
        ->and($this->service->searchModeFor($this->service->parseSearchQuery('Aruula OR Aruula')))->toBe('lexical')
        ->and($this->service->searchModeFor($this->service->parseSearchQuery('Aruula NOT Drax')))->toBe('lexical');
});

test('lexical mode remains the kill switch', function (): void {
    Config::set('kompendium.search.mode', 'lexical');
    Config::set('kompendium.search.index_variant', 'hybrid');
    Config::set('scout.driver', 'typesense');
    expect($this->service->searchModeFor($this->service->parseSearchQuery('Aruula')))->toBe('lexical');
});

test('hybrid queries require the hybrid index variant', function (): void {
    Config::set('kompendium.search.mode', 'hybrid');
    Config::set('kompendium.search.index_variant', 'lexical');
    Config::set('scout.driver', 'typesense');
    expect($this->service->searchModeFor($this->service->parseSearchQuery('Aruula')))->toBe('lexical');
});

test('parse nur phrase', function (): void {
    $result = $this->service->parseSearchQuery('"Matthew Drax"');
    expect($result['isPhraseSearch'])->toBeTrue();
    $this->assertArraysAreEqual(['matthew drax'], $result['phrases']);
    expect($result['terms'])->toBeEmpty();
});

test('parse nur freie begriffe', function (): void {
    $result = $this->service->parseSearchQuery('Abenteuer Mutation');
    expect($result['isPhraseSearch'])->toBeFalse()
        ->and($result['phrases'])->toBeEmpty();
    $this->assertArraysAreEqual(['abenteuer', 'mutation'], $result['terms']);
});

test('parse gemischte suche', function (): void {
    $result = $this->service->parseSearchQuery('"Matthew Drax" Abenteuer');
    expect($result['isPhraseSearch'])->toBeTrue();
    $this->assertArraysAreEqual(['matthew drax'], $result['phrases']);
    $this->assertArraysAreEqual(['abenteuer'], $result['terms']);
});

test('parse mehrere phrasen', function (): void {
    $result = $this->service->parseSearchQuery('"Matthew Drax" "Volk der Tiefe"');
    expect($result['isPhraseSearch'])->toBeTrue();
    $this->assertArraysAreEqual(['matthew drax', 'volk der tiefe'], $result['phrases']);
    expect($result['terms'])->toBeEmpty()
        ->and($result['groups'])->toHaveCount(1);
});

test('parse leere quotes werden ignoriert', function (): void {
    $result = $this->service->parseSearchQuery('"" Abenteuer');
    expect($result['isPhraseSearch'])->toBeFalse()
        ->and($result['phrases'])->toBeEmpty();
    $this->assertArraysAreEqual(['abenteuer'], $result['terms']);
});

test('parse kurze phrase wird ignoriert', function (): void {
    $result = $this->service->parseSearchQuery('"A" Abenteuer');
    expect($result['isPhraseSearch'])->toBeFalse()
        ->and($result['phrases'])->toBeEmpty();
    $this->assertArraysAreEqual(['abenteuer'], $result['terms']);
});

test('parse kurze freie begriffe werden ignoriert', function (): void {
    $result = $this->service->parseSearchQuery('"Matthew Drax" x');
    expect($result['isPhraseSearch'])->toBeTrue();
    $this->assertArraysAreEqual(['matthew drax'], $result['phrases']);
    expect($result['terms'])->toBeEmpty();
});

test('parse phrase mit mehreren leerzeichen', function (): void {
    $result = $this->service->parseSearchQuery('"Matthew   Drax"');
    expect($result['isPhraseSearch'])->toBeTrue();
    // Phrase wird getrimmt, interne Leerzeichen bleiben erhalten
    $this->assertArraysAreEqual(['matthew   drax'], $result['phrases']);
});

test('parse komplexe gemischte suche', function (): void {
    $result = $this->service->parseSearchQuery('"Matthew Drax" Abenteuer "Volk der Tiefe" Mutation');
    expect($result['isPhraseSearch'])->toBeTrue();
    $this->assertArraysAreEqual(['matthew drax', 'volk der tiefe'], $result['phrases']);
    $this->assertArraysAreEqual(['abenteuer', 'mutation'], $result['terms']);
});

test('parse or erzeugt mehrere gruppen', function (): void {
    $result = $this->service->parseSearchQuery('Matthew OR Aruula Abenteuer');
    expect($result['usesOrOperator'])->toBeTrue()
        ->and($result['groups'])->toHaveCount(2)
        ->and($result['groups'][0]['requiredTerms'])->toBe(['matthew'])
        ->and($result['groups'][1]['requiredTerms'])->toBe(['aruula', 'abenteuer']);
});

test('parse not und minus notieren ausschluesse', function (): void {
    $result = $this->service->parseSearchQuery('Matthew NOT Aruula -"Volk der Tiefe"');
    expect($result['usesNotOperator'])->toBeTrue()
        ->and($result['terms'])->toBe(['matthew'])
        ->and($result['excludedTerms'])->toBe(['aruula'])
        ->and($result['excludedPhrases'])->toBe(['volk der tiefe']);
});

test('parse or nimmt keine gruppe ohne positive operanden auf', function (): void {
    $result = $this->service->parseSearchQuery('NOT aruula OR matthew');
    expect($result['usesOrOperator'])->toBeTrue()
        ->and($result['groups'])->toHaveCount(1)
        ->and($result['groups'][0]['requiredTerms'])->toBe(['matthew'])
        ->and($result['groups'][0]['excludedTerms'])->toBe([])
        ->and($result['excludedTerms'])->toBe(['aruula']);
});

test('parse konvertiert alles zu lowercase', function (): void {
    $result = $this->service->parseSearchQuery('"MATTHEW DRAX" ABENTEUER');
    $this->assertArraysAreEqual(['matthew drax'], $result['phrases']);
    $this->assertArraysAreEqual(['abenteuer'], $result['terms']);
});

test('parse typografische anfuehrungszeichen werden normalisiert', function (): void {
    // Linkes/rechtes typografisches Anführungszeichen (U+201C / U+201D)
    $result = $this->service->parseSearchQuery('“Matthew Drax” Abenteuer');
    expect($result['isPhraseSearch'])->toBeTrue();
    $this->assertArraysAreEqual(['matthew drax'], $result['phrases']);
    $this->assertArraysAreEqual(['abenteuer'], $result['terms']);
});

test('parse deutsche anfuehrungszeichen werden normalisiert', function (): void {
    // Deutsches „…“ (U+201E / U+201C)
    $result = $this->service->parseSearchQuery('„Matthew Drax“ Mutation');
    expect($result['isPhraseSearch'])->toBeTrue();
    $this->assertArraysAreEqual(['matthew drax'], $result['phrases']);
    $this->assertArraysAreEqual(['mutation'], $result['terms']);
});

test('build query aus phrase', function (): void {
    $parsed = $this->service->parseSearchQuery('"Matthew Drax"');
    $tntQuery = $this->service->buildTntSearchQuery($parsed);
    expect($tntQuery)->toContain('matthew')
        ->toContain('drax');
});

test('build query aus gemischter suche', function (): void {
    $parsed = $this->service->parseSearchQuery('"Matthew Drax" Abenteuer');
    $tntQuery = $this->service->buildTntSearchQuery($parsed);
    expect($tntQuery)->toContain('matthew')
        ->toContain('drax')
        ->toContain('abenteuer');
});

test('build query ignoriert ausgeschlossene begriffe', function (): void {
    $parsed = $this->service->parseSearchQuery('Matthew OR Aruula NOT Mutant');
    $tntQuery = $this->service->buildTntSearchQuery($parsed);
    expect($tntQuery)->toContain('matthew')
        ->toContain('aruula')->not->toContain('mutant');
});

test('build query dedupliziert woerter', function (): void {
    $parsed = $this->service->parseSearchQuery('"Matthew Drax" Matthew');
    $tntQuery = $this->service->buildTntSearchQuery($parsed);
    // "matthew" soll nur einmal vorkommen
    expect(substr_count($tntQuery, 'matthew'))->toEqual(1);
});

test('build query ohne phrasen', function (): void {
    $parsed = $this->service->parseSearchQuery('Abenteuer Mutation');
    $tntQuery = $this->service->buildTntSearchQuery($parsed);
    expect($tntQuery)->toEqual('abenteuer mutation');
});

test('has positive operands ist false bei nur negativen begriffen', function (): void {
    $parsed = $this->service->parseSearchQuery('NOT Aruula');
    expect($this->service->hasPositiveOperands($parsed))->toBeFalse()
        ->and($parsed['hasPositiveOperands'])->toBeFalse();
});

test('matches text verlangt standardmaessig and semantik', function (): void {
    $parsed = $this->service->parseSearchQuery('Matthew Drax');
    expect($this->service->matchesText('Matthew und Drax tauchen beide im Text auf.', $parsed))->toBeTrue()
        ->and($this->service->matchesText('Nur Matthew ist vorhanden.', $parsed))->toBeFalse();
});

test('matches text beruecksichtigt or gruppen', function (): void {
    $parsed = $this->service->parseSearchQuery('Matthew OR Aruula');
    expect($this->service->matchesText('Aruula betrat den Raum.', $parsed))->toBeTrue()
        ->and($this->service->matchesText('Matthew war ebenfalls da.', $parsed))->toBeTrue()
        ->and($this->service->matchesText('Xij Hamel sprach allein.', $parsed))->toBeFalse();
});

test('matches text respektiert not und phrasen', function (): void {
    $parsed = $this->service->parseSearchQuery('"Matthew Drax" NOT Aruula');
    expect($this->service->matchesText('Matthew Drax erkundete die Anlage.', $parsed))->toBeTrue()
        ->and($this->service->matchesText('Matthew Drax und Aruula sprachen miteinander.', $parsed))->toBeFalse();
});

test('matches text wendet globale ausschluesse auch bei or gruppen an', function (): void {
    $parsed = $this->service->parseSearchQuery('Matthew OR Aruula NOT Mutant');
    expect($this->service->matchesText('Aruula betrat den Raum.', $parsed))->toBeTrue()
        ->and($this->service->matchesText('Matthew traf auf einen Mutant in der Anlage.', $parsed))->toBeFalse()
        ->and($this->service->matchesText('Aruula warnte vor einem Mutant.', $parsed))->toBeFalse();
});

test('matches text laesst exclude only or prefix nicht zu einem breiten match werden', function (): void {
    $parsed = $this->service->parseSearchQuery('NOT aruula OR matthew');
    expect($this->service->matchesText('Xij Hamel blieb im Schatten.', $parsed))->toBeFalse()
        ->and($this->service->matchesText('Matthew erkundete die Anlage.', $parsed))->toBeTrue()
        ->and($this->service->matchesText('Matthew und Aruula sprachen miteinander.', $parsed))->toBeFalse();
});

test('post filter result paths erweitert kandidaten iterativ bis genug treffer vorliegen', function (): void {
    $parsed = $this->service->parseSearchQuery('Matthew OR Aruula');
    $paths = [];
    $texts = [];
    foreach (range(1, 205) as $number) {
        $path = sprintf('romane/maddrax/%03d - Test.txt', $number);
        $paths[] = $path;
        $texts[$path] = $number === 205 ? 'Aruula fand endlich den gesuchten Hinweis.' : 'Xij Hamel blieb im Schatten.';
    }
    $result = $this->service->postFilterResultPaths($paths, $parsed, static fn (string $path): ?string => $texts[$path] ?? null, 1, 200, 1000);
    expect($result['matchedPaths'])->toBe([sprintf('romane/maddrax/%03d - Test.txt', 205)])
        ->and($result['scannedCandidates'])->toBe(205)
        ->and($result['candidatesTruncated'])->toBeFalse();
});

test('post filter result paths bricht innerhalb einer batch ab sobald genug treffer gefunden sind', function (): void {
    $parsed = $this->service->parseSearchQuery('Matthew OR Aruula');
    $paths = [];
    $texts = [];
    foreach (range(1, 20) as $number) {
        $path = sprintf('romane/maddrax/%03d - Test.txt', $number);
        $paths[] = $path;
        $texts[$path] = $number === 3 ? 'Aruula fand endlich den gesuchten Hinweis.' : 'Xij Hamel blieb im Schatten.';
    }
    $result = $this->service->postFilterResultPaths($paths, $parsed, static fn (string $path): ?string => $texts[$path] ?? null, 1, 20, 20);
    expect($result['matchedPaths'])->toBe([sprintf('romane/maddrax/%03d - Test.txt', 3)])
        ->and($result['scannedCandidates'])->toBe(3)
        ->and($result['textCache'])->toBe([sprintf('romane/maddrax/%03d - Test.txt', 3) => 'Aruula fand endlich den gesuchten Hinweis.'])
        ->and($result['candidatesTruncated'])->toBeTrue();
});

test('post filter result paths respektiert ein kleineres maximum als die initiale batchgroesse', function (): void {
    $parsed = $this->service->parseSearchQuery('Matthew OR Aruula');
    $paths = [];
    $texts = [];
    foreach (range(1, 150) as $number) {
        $path = sprintf('romane/maddrax/%03d - Test.txt', $number);
        $paths[] = $path;
        $texts[$path] = $number === 120 ? 'Aruula fand endlich den gesuchten Hinweis.' : 'Xij Hamel blieb im Schatten.';
    }
    $result = $this->service->postFilterResultPaths($paths, $parsed, static fn (string $path): ?string => $texts[$path] ?? null, 1, 100, 50);
    expect($result['matchedPaths'])->toBe([])
        ->and($result['scannedCandidates'])->toBe(50)
        ->and($result['candidatesTruncated'])->toBeTrue();
});

test('post filter budget liest konfigurierbare grenzen', function (): void {
    Config::set('kompendium.post_filter.initial_batch_size', 80);
    Config::set('kompendium.post_filter.max_candidates_per_request', 450);
    Config::set('kompendium.post_filter.batch_growth_factor', 3);
    expect($this->service->postFilterBudget())->toBe(['initialBatchSize' => 80, 'maxCandidatesPerRequest' => 450, 'batchGrowthFactor' => 3]);
});

test('post filter result paths zaehlt nur passende treffer fuer das required matches limit', function (): void {
    $parsed = $this->service->parseSearchQuery('Aruula OR Hinweis');
    $paths = [];
    $texts = [];
    foreach (range(1, 6) as $number) {
        $path = sprintf('romane/missionmars/%03d - Mission.txt', $number);
        $paths[] = $path;
        $texts[$path] = 'Aruula fand einen wichtigen Hinweis.';
    }
    foreach (range(1, 2) as $number) {
        $path = sprintf('romane/maddrax/%03d - Maddrax.txt', $number);
        $paths[] = $path;
        $texts[$path] = 'Aruula fand einen wichtigen Hinweis.';
    }
    $result = $this->service->postFilterResultPaths($paths, $parsed, static fn (string $path): ?string => $texts[$path] ?? null, 2, 10, 10, 2, static fn (string $path): bool => str_contains($path, '/maddrax/'));
    expect($result['matchedPaths'])->toHaveCount(8)
        ->and($result['scannedCandidates'])->toBe(8)
        ->and($result['candidatesTruncated'])->toBeFalse();
});

PhpunitMetadata::onFile(__FILE__, CoversMethod::class, KompendiumSearchService::class, 'parseSearchQuery');

PhpunitMetadata::onFile(__FILE__, CoversMethod::class, KompendiumSearchService::class, 'buildTntSearchQuery');

PhpunitMetadata::onFile(__FILE__, CoversMethod::class, KompendiumSearchService::class, 'hasPositiveOperands');

PhpunitMetadata::onFile(__FILE__, CoversMethod::class, KompendiumSearchService::class, 'matchesText');

PhpunitMetadata::onFile(__FILE__, CoversMethod::class, KompendiumSearchService::class, 'postFilterResultPaths');

PhpunitMetadata::onFile(__FILE__, CoversMethod::class, KompendiumSearchService::class, 'searchModeFor');
