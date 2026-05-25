<?php

namespace Tests\Unit;

use App\Services\KompendiumSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit-Tests für den Query-Parser im KompendiumSearchService.
 */
#[CoversMethod(KompendiumSearchService::class, 'parseSearchQuery')]
#[CoversMethod(KompendiumSearchService::class, 'buildTntSearchQuery')]
#[CoversMethod(KompendiumSearchService::class, 'hasPositiveOperands')]
#[CoversMethod(KompendiumSearchService::class, 'matchesText')]
#[CoversMethod(KompendiumSearchService::class, 'postFilterResultPaths')]
class KompendiumSearchQueryParserTest extends TestCase
{
    use RefreshDatabase;

    private KompendiumSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new KompendiumSearchService;
    }

    /* --------------------------------------------------------------------- */
    /*  parseSearchQuery – Phrasen-Erkennung */
    /* --------------------------------------------------------------------- */

    #[Test]
    public function parse_nur_phrase(): void
    {
        $result = $this->service->parseSearchQuery('"Matthew Drax"');

        $this->assertTrue($result['isPhraseSearch']);
        $this->assertArraysAreEqual(['matthew drax'], $result['phrases']);
        $this->assertEmpty($result['terms']);
    }

    #[Test]
    public function parse_nur_freie_begriffe(): void
    {
        $result = $this->service->parseSearchQuery('Abenteuer Mutation');

        $this->assertFalse($result['isPhraseSearch']);
        $this->assertEmpty($result['phrases']);
        $this->assertArraysAreEqual(['abenteuer', 'mutation'], $result['terms']);
    }

    #[Test]
    public function parse_gemischte_suche(): void
    {
        $result = $this->service->parseSearchQuery('"Matthew Drax" Abenteuer');

        $this->assertTrue($result['isPhraseSearch']);
        $this->assertArraysAreEqual(['matthew drax'], $result['phrases']);
        $this->assertArraysAreEqual(['abenteuer'], $result['terms']);
    }

    #[Test]
    public function parse_mehrere_phrasen(): void
    {
        $result = $this->service->parseSearchQuery('"Matthew Drax" "Volk der Tiefe"');

        $this->assertTrue($result['isPhraseSearch']);
        $this->assertArraysAreEqual(['matthew drax', 'volk der tiefe'], $result['phrases']);
        $this->assertEmpty($result['terms']);
        $this->assertCount(1, $result['groups']);
    }

    #[Test]
    public function parse_leere_quotes_werden_ignoriert(): void
    {
        $result = $this->service->parseSearchQuery('"" Abenteuer');

        $this->assertFalse($result['isPhraseSearch']);
        $this->assertEmpty($result['phrases']);
        $this->assertArraysAreEqual(['abenteuer'], $result['terms']);
    }

    #[Test]
    public function parse_kurze_phrase_wird_ignoriert(): void
    {
        $result = $this->service->parseSearchQuery('"A" Abenteuer');

        $this->assertFalse($result['isPhraseSearch']);
        $this->assertEmpty($result['phrases']);
        $this->assertArraysAreEqual(['abenteuer'], $result['terms']);
    }

    #[Test]
    public function parse_kurze_freie_begriffe_werden_ignoriert(): void
    {
        $result = $this->service->parseSearchQuery('"Matthew Drax" x');

        $this->assertTrue($result['isPhraseSearch']);
        $this->assertArraysAreEqual(['matthew drax'], $result['phrases']);
        $this->assertEmpty($result['terms']);
    }

    #[Test]
    public function parse_phrase_mit_mehreren_leerzeichen(): void
    {
        $result = $this->service->parseSearchQuery('"Matthew   Drax"');

        $this->assertTrue($result['isPhraseSearch']);
        // Phrase wird getrimmt, interne Leerzeichen bleiben erhalten
        $this->assertArraysAreEqual(['matthew   drax'], $result['phrases']);
    }

    #[Test]
    public function parse_komplexe_gemischte_suche(): void
    {
        $result = $this->service->parseSearchQuery('"Matthew Drax" Abenteuer "Volk der Tiefe" Mutation');

        $this->assertTrue($result['isPhraseSearch']);
        $this->assertArraysAreEqual(['matthew drax', 'volk der tiefe'], $result['phrases']);
        $this->assertArraysAreEqual(['abenteuer', 'mutation'], $result['terms']);
    }

    #[Test]
    public function parse_or_erzeugt_mehrere_gruppen(): void
    {
        $result = $this->service->parseSearchQuery('Matthew OR Aruula Abenteuer');

        $this->assertTrue($result['usesOrOperator']);
        $this->assertCount(2, $result['groups']);
        $this->assertSame(['matthew'], $result['groups'][0]['requiredTerms']);
        $this->assertSame(['aruula', 'abenteuer'], $result['groups'][1]['requiredTerms']);
    }

    #[Test]
    public function parse_not_und_minus_notieren_ausschluesse(): void
    {
        $result = $this->service->parseSearchQuery('Matthew NOT Aruula -"Volk der Tiefe"');

        $this->assertTrue($result['usesNotOperator']);
        $this->assertSame(['matthew'], $result['terms']);
        $this->assertSame(['aruula'], $result['excludedTerms']);
        $this->assertSame(['volk der tiefe'], $result['excludedPhrases']);
    }

    #[Test]
    public function parse_konvertiert_alles_zu_lowercase(): void
    {
        $result = $this->service->parseSearchQuery('"MATTHEW DRAX" ABENTEUER');

        $this->assertArraysAreEqual(['matthew drax'], $result['phrases']);
        $this->assertArraysAreEqual(['abenteuer'], $result['terms']);
    }

    #[Test]
    public function parse_typografische_anfuehrungszeichen_werden_normalisiert(): void
    {
        // Linkes/rechtes typografisches Anführungszeichen (U+201C / U+201D)
        $result = $this->service->parseSearchQuery("\u{201C}Matthew Drax\u{201D} Abenteuer");

        $this->assertTrue($result['isPhraseSearch']);
        $this->assertArraysAreEqual(['matthew drax'], $result['phrases']);
        $this->assertArraysAreEqual(['abenteuer'], $result['terms']);
    }

    #[Test]
    public function parse_deutsche_anfuehrungszeichen_werden_normalisiert(): void
    {
        // Deutsches „…“ (U+201E / U+201C)
        $result = $this->service->parseSearchQuery("\u{201E}Matthew Drax\u{201C} Mutation");

        $this->assertTrue($result['isPhraseSearch']);
        $this->assertArraysAreEqual(['matthew drax'], $result['phrases']);
        $this->assertArraysAreEqual(['mutation'], $result['terms']);
    }

    /* --------------------------------------------------------------------- */
    /*  buildTntSearchQuery – TNTSearch-Query-Generierung */
    /* --------------------------------------------------------------------- */

    #[Test]
    public function build_query_aus_phrase(): void
    {
        $parsed = $this->service->parseSearchQuery('"Matthew Drax"');
        $tntQuery = $this->service->buildTntSearchQuery($parsed);

        $this->assertStringContainsString('matthew', $tntQuery);
        $this->assertStringContainsString('drax', $tntQuery);
    }

    #[Test]
    public function build_query_aus_gemischter_suche(): void
    {
        $parsed = $this->service->parseSearchQuery('"Matthew Drax" Abenteuer');
        $tntQuery = $this->service->buildTntSearchQuery($parsed);

        $this->assertStringContainsString('matthew', $tntQuery);
        $this->assertStringContainsString('drax', $tntQuery);
        $this->assertStringContainsString('abenteuer', $tntQuery);
    }

    #[Test]
    public function build_query_ignoriert_ausgeschlossene_begriffe(): void
    {
        $parsed = $this->service->parseSearchQuery('Matthew OR Aruula NOT Mutant');
        $tntQuery = $this->service->buildTntSearchQuery($parsed);

        $this->assertStringContainsString('matthew', $tntQuery);
        $this->assertStringContainsString('aruula', $tntQuery);
        $this->assertStringNotContainsString('mutant', $tntQuery);
    }

    #[Test]
    public function build_query_dedupliziert_woerter(): void
    {
        $parsed = $this->service->parseSearchQuery('"Matthew Drax" Matthew');
        $tntQuery = $this->service->buildTntSearchQuery($parsed);

        // "matthew" soll nur einmal vorkommen
        $this->assertEquals(1, substr_count($tntQuery, 'matthew'));
    }

    #[Test]
    public function build_query_ohne_phrasen(): void
    {
        $parsed = $this->service->parseSearchQuery('Abenteuer Mutation');
        $tntQuery = $this->service->buildTntSearchQuery($parsed);

        $this->assertEquals('abenteuer mutation', $tntQuery);
    }

    #[Test]
    public function has_positive_operands_ist_false_bei_nur_negativen_begriffen(): void
    {
        $parsed = $this->service->parseSearchQuery('NOT Aruula');

        $this->assertFalse($this->service->hasPositiveOperands($parsed));
        $this->assertFalse($parsed['hasPositiveOperands']);
    }

    #[Test]
    public function matches_text_verlangt_standardmaessig_and_semantik(): void
    {
        $parsed = $this->service->parseSearchQuery('Matthew Drax');

        $this->assertTrue($this->service->matchesText('Matthew und Drax tauchen beide im Text auf.', $parsed));
        $this->assertFalse($this->service->matchesText('Nur Matthew ist vorhanden.', $parsed));
    }

    #[Test]
    public function matches_text_beruecksichtigt_or_gruppen(): void
    {
        $parsed = $this->service->parseSearchQuery('Matthew OR Aruula');

        $this->assertTrue($this->service->matchesText('Aruula betrat den Raum.', $parsed));
        $this->assertTrue($this->service->matchesText('Matthew war ebenfalls da.', $parsed));
        $this->assertFalse($this->service->matchesText('Xij Hamel sprach allein.', $parsed));
    }

    #[Test]
    public function matches_text_respektiert_not_und_phrasen(): void
    {
        $parsed = $this->service->parseSearchQuery('"Matthew Drax" NOT Aruula');

        $this->assertTrue($this->service->matchesText('Matthew Drax erkundete die Anlage.', $parsed));
        $this->assertFalse($this->service->matchesText('Matthew Drax und Aruula sprachen miteinander.', $parsed));
    }

    #[Test]
    public function matches_text_wendet_globale_ausschluesse_auch_bei_or_gruppen_an(): void
    {
        $parsed = $this->service->parseSearchQuery('Matthew OR Aruula NOT Mutant');

        $this->assertTrue($this->service->matchesText('Aruula betrat den Raum.', $parsed));
        $this->assertFalse($this->service->matchesText('Matthew traf auf einen Mutant in der Anlage.', $parsed));
        $this->assertFalse($this->service->matchesText('Aruula warnte vor einem Mutant.', $parsed));
    }

    #[Test]
    public function post_filter_result_paths_erweitert_kandidaten_iterativ_bis_genug_treffer_vorliegen(): void
    {
        $parsed = $this->service->parseSearchQuery('Matthew OR Aruula');
        $paths = [];
        $texts = [];

        foreach (range(1, 205) as $number) {
            $path = sprintf('romane/maddrax/%03d - Test.txt', $number);
            $paths[] = $path;
            $texts[$path] = $number === 205
                ? 'Aruula fand endlich den gesuchten Hinweis.'
                : 'Xij Hamel blieb im Schatten.';
        }

        $result = $this->service->postFilterResultPaths(
            $paths,
            $parsed,
            static fn (string $path): ?string => $texts[$path] ?? null,
            1,
            200,
            1000,
        );

        $this->assertSame([sprintf('romane/maddrax/%03d - Test.txt', 205)], $result['matchedPaths']);
        $this->assertSame(205, $result['scannedCandidates']);
        $this->assertFalse($result['candidatesTruncated']);
    }

    #[Test]
    public function post_filter_result_paths_bricht_innerhalb_einer_batch_ab_sobald_genug_treffer_gefunden_sind(): void
    {
        $parsed = $this->service->parseSearchQuery('Matthew OR Aruula');
        $paths = [];
        $texts = [];

        foreach (range(1, 20) as $number) {
            $path = sprintf('romane/maddrax/%03d - Test.txt', $number);
            $paths[] = $path;
            $texts[$path] = $number === 3
                ? 'Aruula fand endlich den gesuchten Hinweis.'
                : 'Xij Hamel blieb im Schatten.';
        }

        $result = $this->service->postFilterResultPaths(
            $paths,
            $parsed,
            static fn (string $path): ?string => $texts[$path] ?? null,
            1,
            20,
            20,
        );

        $this->assertSame([sprintf('romane/maddrax/%03d - Test.txt', 3)], $result['matchedPaths']);
        $this->assertSame(3, $result['scannedCandidates']);
        $this->assertSame([
            sprintf('romane/maddrax/%03d - Test.txt', 3) => 'Aruula fand endlich den gesuchten Hinweis.',
        ], $result['textCache']);
        $this->assertTrue($result['candidatesTruncated']);
    }

    #[Test]
    public function post_filter_result_paths_respektiert_ein_kleineres_maximum_als_die_initiale_batchgroesse(): void
    {
        $parsed = $this->service->parseSearchQuery('Matthew OR Aruula');
        $paths = [];
        $texts = [];

        foreach (range(1, 150) as $number) {
            $path = sprintf('romane/maddrax/%03d - Test.txt', $number);
            $paths[] = $path;
            $texts[$path] = $number === 120
                ? 'Aruula fand endlich den gesuchten Hinweis.'
                : 'Xij Hamel blieb im Schatten.';
        }

        $result = $this->service->postFilterResultPaths(
            $paths,
            $parsed,
            static fn (string $path): ?string => $texts[$path] ?? null,
            1,
            100,
            50,
        );

        $this->assertSame([], $result['matchedPaths']);
        $this->assertSame(50, $result['scannedCandidates']);
        $this->assertTrue($result['candidatesTruncated']);
    }

    #[Test]
    public function post_filter_budget_liest_konfigurierbare_grenzen(): void
    {
        Config::set('kompendium.post_filter.initial_batch_size', 80);
        Config::set('kompendium.post_filter.max_candidates_per_request', 450);
        Config::set('kompendium.post_filter.batch_growth_factor', 3);

        $this->assertSame([
            'initialBatchSize' => 80,
            'maxCandidatesPerRequest' => 450,
            'batchGrowthFactor' => 3,
        ], $this->service->postFilterBudget());
    }

    #[Test]
    public function post_filter_result_paths_zaehlt_nur_passende_treffer_fuer_das_required_matches_limit(): void
    {
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

        $result = $this->service->postFilterResultPaths(
            $paths,
            $parsed,
            static fn (string $path): ?string => $texts[$path] ?? null,
            2,
            10,
            10,
            2,
            static fn (string $path): bool => str_contains($path, '/maddrax/'),
        );

        $this->assertCount(8, $result['matchedPaths']);
        $this->assertSame(8, $result['scannedCandidates']);
        $this->assertFalse($result['candidatesTruncated']);
    }
}
