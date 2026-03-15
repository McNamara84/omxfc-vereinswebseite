<?php

namespace Tests\Unit;

use App\Services\KompendiumSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit-Tests für den Query-Parser im KompendiumSearchService.
 */
#[CoversMethod(KompendiumSearchService::class, 'parseSearchQuery')]
#[CoversMethod(KompendiumSearchService::class, 'buildTntSearchQuery')]
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
        $this->assertTrue($result['hadQuotes']);
        $this->assertEquals(['matthew drax'], $result['phrases']);
        $this->assertEmpty($result['terms']);
    }

    #[Test]
    public function parse_nur_freie_begriffe(): void
    {
        $result = $this->service->parseSearchQuery('Abenteuer Mutation');

        $this->assertFalse($result['isPhraseSearch']);
        $this->assertFalse($result['hadQuotes']);
        $this->assertEmpty($result['phrases']);
        $this->assertEquals(['abenteuer', 'mutation'], $result['terms']);
    }

    #[Test]
    public function parse_gemischte_suche(): void
    {
        $result = $this->service->parseSearchQuery('"Matthew Drax" Abenteuer');

        $this->assertTrue($result['isPhraseSearch']);
        $this->assertTrue($result['hadQuotes']);
        $this->assertEquals(['matthew drax'], $result['phrases']);
        $this->assertEquals(['abenteuer'], $result['terms']);
    }

    #[Test]
    public function parse_mehrere_phrasen(): void
    {
        $result = $this->service->parseSearchQuery('"Matthew Drax" "Volk der Tiefe"');

        $this->assertTrue($result['isPhraseSearch']);
        $this->assertEquals(['matthew drax', 'volk der tiefe'], $result['phrases']);
        $this->assertEmpty($result['terms']);
    }

    #[Test]
    public function parse_leere_quotes_werden_ignoriert(): void
    {
        $result = $this->service->parseSearchQuery('"" Abenteuer');

        $this->assertFalse($result['isPhraseSearch']);
        $this->assertTrue($result['hadQuotes']);
        $this->assertEmpty($result['phrases']);
        $this->assertEquals(['abenteuer'], $result['terms']);
    }

    #[Test]
    public function parse_kurze_phrase_wird_ignoriert(): void
    {
        $result = $this->service->parseSearchQuery('"A" Abenteuer');

        $this->assertFalse($result['isPhraseSearch']);
        $this->assertTrue($result['hadQuotes']);
        $this->assertEmpty($result['phrases']);
        $this->assertEquals(['abenteuer'], $result['terms']);
    }

    #[Test]
    public function parse_kurze_freie_begriffe_werden_ignoriert(): void
    {
        $result = $this->service->parseSearchQuery('"Matthew Drax" x');

        $this->assertTrue($result['isPhraseSearch']);
        $this->assertEquals(['matthew drax'], $result['phrases']);
        $this->assertEmpty($result['terms']);
    }

    #[Test]
    public function parse_phrase_mit_mehreren_leerzeichen(): void
    {
        $result = $this->service->parseSearchQuery('"Matthew   Drax"');

        $this->assertTrue($result['isPhraseSearch']);
        // Phrase wird getrimmt, interne Leerzeichen bleiben erhalten
        $this->assertEquals(['matthew   drax'], $result['phrases']);
    }

    #[Test]
    public function parse_komplexe_gemischte_suche(): void
    {
        $result = $this->service->parseSearchQuery('"Matthew Drax" Abenteuer "Volk der Tiefe" Mutation');

        $this->assertTrue($result['isPhraseSearch']);
        $this->assertEquals(['matthew drax', 'volk der tiefe'], $result['phrases']);
        $this->assertEquals(['abenteuer', 'mutation'], $result['terms']);
    }

    #[Test]
    public function parse_konvertiert_alles_zu_lowercase(): void
    {
        $result = $this->service->parseSearchQuery('"MATTHEW DRAX" ABENTEUER');

        $this->assertEquals(['matthew drax'], $result['phrases']);
        $this->assertEquals(['abenteuer'], $result['terms']);
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
}
