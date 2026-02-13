<?php

namespace Tests\Feature;

use App\Services\KompendiumSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class KompendiumSearchTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesUserWithRole;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_search_requires_enough_points(): void
    {
        $user = $this->actingMemberWithPoints(50); // below 100

        $this->getJson('/kompendium/suche?q=test')
            ->assertStatus(403)
            ->assertJson(['message' => 'Zugang erfordert mindestens 100 Punkte oder AG-Maddraxikon-Mitgliedschaft (du hast 50 Punkte).']);
    }

    public function test_search_validates_query_length(): void
    {
        $user = $this->actingMemberWithPoints(150);

        $this->getJson('/kompendium/suche?q=a')
            ->assertStatus(422);
    }

    public function test_search_returns_formatted_results(): void
    {
        $user = $this->actingMemberWithPoints(150);

        Storage::fake('private');
        Storage::disk('private')->put('romane/maddrax/001 - ExampleTitle.txt', 'Some example content with query word');

        // Mock den KompendiumSearchService
        $this->mock(KompendiumSearchService::class, function ($mock) {
            $mock->shouldReceive('search')
                ->with('example')
                ->once()
                ->andReturn([
                    'hits' => ['total_hits' => 1],
                    'ids' => ['romane/maddrax/001 - ExampleTitle.txt'],
                ]);
        });

        $response = $this->getJson('/kompendium/suche?q=example');

        $response->assertOk()
            ->assertJson([
                'currentPage' => 1,
                'lastPage' => 1,
                'data' => [[
                    'cycle' => 'Maddrax - Die dunkle Zukunft der Erde',
                    'romanNr' => '001',
                    'title' => 'ExampleTitle',
                    'serie' => 'maddrax',
                ]],
                'serienCounts' => [
                    'maddrax' => 1,
                ],
            ]);
    }

    public function test_search_returns_empty_when_no_matches_found(): void
    {
        $user = $this->actingMemberWithPoints(150);

        Storage::fake('private');

        // Mock den KompendiumSearchService
        $this->mock(KompendiumSearchService::class, function ($mock) {
            $mock->shouldReceive('search')
                ->with('nomatch')
                ->once()
                ->andReturn([
                    'hits' => ['total_hits' => 0],
                    'ids' => [],
                ]);
        });

        $response = $this->getJson('/kompendium/suche?q=nomatch');

        $response->assertOk()
            ->assertJson([
                'currentPage' => 1,
                'lastPage' => 1,
                'data' => [],
                'serienCounts' => [],
            ]);
    }

    public function test_search_filters_by_serien_parameter(): void
    {
        $user = $this->actingMemberWithPoints(150);

        Storage::fake('private');
        Storage::disk('private')->put('romane/maddrax/001 - MaddraxRoman.txt', 'Content maddrax');
        Storage::disk('private')->put('romane/missionmars/001 - MarsRoman.txt', 'Content mars');

        // Mock: Suche findet beide Romane
        $this->mock(KompendiumSearchService::class, function ($mock) {
            $mock->shouldReceive('search')
                ->with('content')
                ->once()
                ->andReturn([
                    'hits' => ['total_hits' => 2],
                    'ids' => [
                        'romane/maddrax/001 - MaddraxRoman.txt',
                        'romane/missionmars/001 - MarsRoman.txt',
                    ],
                ]);
        });

        // Nur Maddrax-Serie anfordern
        $response = $this->getJson('/kompendium/suche?q=content&serien[]=maddrax');

        $response->assertOk();
        $data = $response->json('data');

        // Nur 1 Treffer (Maddrax), da missionmars gefiltert wurde
        $this->assertCount(1, $data);
        $this->assertEquals('maddrax', $data[0]['serie']);

        // serienCounts zeigt aber beide (Gesamtübersicht)
        $response->assertJson([
            'serienCounts' => [
                'maddrax' => 1,
                'missionmars' => 1,
            ],
        ]);
    }

    public function test_search_returns_all_serien_when_no_filter(): void
    {
        $user = $this->actingMemberWithPoints(150);

        Storage::fake('private');
        Storage::disk('private')->put('romane/maddrax/001 - MaddraxRoman.txt', 'Content maddrax');
        Storage::disk('private')->put('romane/missionmars/001 - MarsRoman.txt', 'Content mars');

        $this->mock(KompendiumSearchService::class, function ($mock) {
            $mock->shouldReceive('search')
                ->with('content')
                ->once()
                ->andReturn([
                    'hits' => ['total_hits' => 2],
                    'ids' => [
                        'romane/maddrax/001 - MaddraxRoman.txt',
                        'romane/missionmars/001 - MarsRoman.txt',
                    ],
                ]);
        });

        // Keine serien[] Parameter = alle Serien
        $response = $this->getJson('/kompendium/suche?q=content');

        $response->assertOk();
        $data = $response->json('data');

        // Beide Treffer
        $this->assertCount(2, $data);
    }

    public function test_search_validates_invalid_serien_parameter(): void
    {
        $user = $this->actingMemberWithPoints(150);

        // Ungültige Serie "invalid-serie" sollte Validierungsfehler auslösen
        $response = $this->getJson('/kompendium/suche?q=test&serien[]=invalid-serie');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['serien.0']);
    }

    public function test_search_accepts_valid_serien_parameter(): void
    {
        $user = $this->actingMemberWithPoints(150);

        Storage::fake('private');

        $this->mock(KompendiumSearchService::class, function ($mock) {
            $mock->shouldReceive('search')
                ->with('test')
                ->once()
                ->andReturn([
                    'hits' => ['total_hits' => 0],
                    'ids' => [],
                ]);
        });

        // Gültige Serie "maddrax" sollte akzeptiert werden
        $response = $this->getJson('/kompendium/suche?q=test&serien[]=maddrax');

        $response->assertOk();
    }

    public function test_search_ignores_path_traversal_attempts(): void
    {
        $user = $this->actingMemberWithPoints(150);

        Storage::fake('private');
        // Legitime Datei erstellen
        Storage::disk('private')->put('romane/maddrax/001 - ValidRoman.txt', 'Valid content');

        // Mock: Suche gibt sowohl legitime als auch bösartige Pfade zurück
        $this->mock(KompendiumSearchService::class, function ($mock) {
            $mock->shouldReceive('search')
                ->with('content')
                ->once()
                ->andReturn([
                    'hits' => ['total_hits' => 3],
                    'ids' => [
                        'romane/maddrax/001 - ValidRoman.txt',           // Gültig
                        '../.env',                                        // Path-Traversal
                        'romane/../../../.env',                           // Path-Traversal
                    ],
                ]);
        });

        $response = $this->getJson('/kompendium/suche?q=content');

        $response->assertOk();
        $data = $response->json('data');

        // Nur der gültige Roman sollte zurückgegeben werden
        $this->assertCount(1, $data);
        $this->assertEquals('001', $data[0]['romanNr']);
    }

    /* --------------------------------------------------------------------- */
    /*  Pagination */
    /* --------------------------------------------------------------------- */

    public function test_search_paginates_results_correctly(): void
    {
        $user = $this->actingMemberWithPoints(150);

        Storage::fake('private');

        // 7 Romane erstellen (5 pro Seite = 2 Seiten)
        $ids = [];
        for ($i = 1; $i <= 7; $i++) {
            $nr = str_pad($i, 3, '0', STR_PAD_LEFT);
            $path = "romane/maddrax/{$nr} - Roman{$i}.txt";
            Storage::disk('private')->put($path, "Content roman{$i} with searchterm");
            $ids[] = $path;
        }

        $this->mock(KompendiumSearchService::class, function ($mock) use ($ids) {
            $mock->shouldReceive('search')
                ->with('searchterm')
                ->twice()
                ->andReturn([
                    'hits' => ['total_hits' => 7],
                    'ids' => $ids,
                ]);
        });

        // Seite 1: 5 Ergebnisse
        $response = $this->getJson('/kompendium/suche?q=searchterm&page=1');
        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(1, $response->json('currentPage'));
        $this->assertEquals(2, $response->json('lastPage'));

        // Seite 2: 2 Ergebnisse
        $response = $this->getJson('/kompendium/suche?q=searchterm&page=2');
        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertEquals(2, $response->json('currentPage'));
    }

    /* --------------------------------------------------------------------- */
    /*  Snippet-Highlighting */
    /* --------------------------------------------------------------------- */

    public function test_search_generates_snippets_with_mark_highlighting(): void
    {
        $user = $this->actingMemberWithPoints(150);

        Storage::fake('private');
        Storage::disk('private')->put(
            'romane/maddrax/001 - HighlightTest.txt',
            'Vor dem Suchbegriff steht Text und hier kommt der Maddrax und danach mehr Text.'
        );

        $this->mock(KompendiumSearchService::class, function ($mock) {
            $mock->shouldReceive('search')
                ->with('maddrax')
                ->once()
                ->andReturn([
                    'hits' => ['total_hits' => 1],
                    'ids' => ['romane/maddrax/001 - HighlightTest.txt'],
                ]);
        });

        $response = $this->getJson('/kompendium/suche?q=maddrax');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data[0]['snippets']);

        // Snippet enthält <mark>-Tags um den Suchbegriff
        $snippet = $data[0]['snippets'][0];
        $this->assertStringContainsString('<mark>', $snippet);
        $this->assertStringContainsString('</mark>', $snippet);
    }

    /* --------------------------------------------------------------------- */
    /*  Fehlende Dateien überspringen */
    /* --------------------------------------------------------------------- */

    public function test_search_skips_files_that_no_longer_exist(): void
    {
        $user = $this->actingMemberWithPoints(150);

        Storage::fake('private');
        // Nur eine von zwei Dateien existiert
        Storage::disk('private')->put('romane/maddrax/001 - Exists.txt', 'Existing content test');

        $this->mock(KompendiumSearchService::class, function ($mock) {
            $mock->shouldReceive('search')
                ->with('content')
                ->once()
                ->andReturn([
                    'hits' => ['total_hits' => 2],
                    'ids' => [
                        'romane/maddrax/001 - Exists.txt',
                        'romane/maddrax/002 - Deleted.txt',  // existiert nicht
                    ],
                ]);
        });

        $response = $this->getJson('/kompendium/suche?q=content');

        $response->assertOk();
        $data = $response->json('data');
        // Nur die existierende Datei wird zurückgegeben
        $this->assertCount(1, $data);
        $this->assertEquals('001', $data[0]['romanNr']);
        $this->assertEquals('Exists', $data[0]['title']);
    }

    /* --------------------------------------------------------------------- */
    /*  Mehrere Serien-Filter gleichzeitig */
    /* --------------------------------------------------------------------- */

    public function test_search_handles_multiple_serien_filters(): void
    {
        $user = $this->actingMemberWithPoints(150);

        Storage::fake('private');
        Storage::disk('private')->put('romane/maddrax/001 - MaddraxRoman.txt', 'Content testterm maddrax');
        Storage::disk('private')->put('romane/missionmars/001 - MarsRoman.txt', 'Content testterm mars');
        Storage::disk('private')->put('romane/hardcovers/001 - HCRoman.txt', 'Content testterm hardcover');

        $this->mock(KompendiumSearchService::class, function ($mock) {
            $mock->shouldReceive('search')
                ->with('testterm')
                ->once()
                ->andReturn([
                    'hits' => ['total_hits' => 3],
                    'ids' => [
                        'romane/maddrax/001 - MaddraxRoman.txt',
                        'romane/missionmars/001 - MarsRoman.txt',
                        'romane/hardcovers/001 - HCRoman.txt',
                    ],
                ]);
        });

        // Zwei von drei Serien filtern
        $response = $this->getJson('/kompendium/suche?q=testterm&serien[]=maddrax&serien[]=missionmars');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);

        $serien = array_column($data, 'serie');
        $this->assertContains('maddrax', $serien);
        $this->assertContains('missionmars', $serien);
        $this->assertNotContains('hardcovers', $serien);

        // serienCounts zeigt alle 3 (unabhängig vom Filter)
        $this->assertEquals(3, count($response->json('serienCounts')));
    }

    /* --------------------------------------------------------------------- */
    /*  Dateiname ohne Standardformat */
    /* --------------------------------------------------------------------- */

    public function test_search_handles_filename_without_separator(): void
    {
        $user = $this->actingMemberWithPoints(150);

        Storage::fake('private');
        Storage::disk('private')->put('romane/maddrax/BadFormat.txt', 'Some badformat content');

        $this->mock(KompendiumSearchService::class, function ($mock) {
            $mock->shouldReceive('search')
                ->with('badformat')
                ->once()
                ->andReturn([
                    'hits' => ['total_hits' => 1],
                    'ids' => ['romane/maddrax/BadFormat.txt'],
                ]);
        });

        $response = $this->getJson('/kompendium/suche?q=badformat');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        // Fallback bei ungültigem Format
        $this->assertEquals('???', $data[0]['romanNr']);
        $this->assertEquals('BadFormat', $data[0]['title']);
    }

    /* --------------------------------------------------------------------- */
    /*  JSON-Struktur der Antwort */
    /* --------------------------------------------------------------------- */

    public function test_search_response_contains_all_required_fields(): void
    {
        $user = $this->actingMemberWithPoints(150);

        Storage::fake('private');
        Storage::disk('private')->put('romane/maddrax/001 - TestRoman.txt', 'Content structtest');

        $this->mock(KompendiumSearchService::class, function ($mock) {
            $mock->shouldReceive('search')
                ->with('structtest')
                ->once()
                ->andReturn([
                    'hits' => ['total_hits' => 1],
                    'ids' => ['romane/maddrax/001 - TestRoman.txt'],
                ]);
        });

        $response = $this->getJson('/kompendium/suche?q=structtest');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['cycle', 'romanNr', 'title', 'serie', 'snippets'],
                ],
                'currentPage',
                'lastPage',
                'serienCounts',
            ]);
    }

    /* --------------------------------------------------------------------- */
    /*  Leere Seite jenseits der letzten Seite */
    /* --------------------------------------------------------------------- */

    public function test_search_returns_empty_data_beyond_last_page(): void
    {
        $user = $this->actingMemberWithPoints(150);

        Storage::fake('private');
        Storage::disk('private')->put('romane/maddrax/001 - OnlyOne.txt', 'Content pagetest');

        $this->mock(KompendiumSearchService::class, function ($mock) {
            $mock->shouldReceive('search')
                ->with('pagetest')
                ->once()
                ->andReturn([
                    'hits' => ['total_hits' => 1],
                    'ids' => ['romane/maddrax/001 - OnlyOne.txt'],
                ]);
        });

        // Seite 999 bei nur 1 Ergebnis
        $response = $this->getJson('/kompendium/suche?q=pagetest&page=999');

        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    /* --------------------------------------------------------------------- */
    /*  Zugang: Nicht-authentifizierter User */
    /* --------------------------------------------------------------------- */

    public function test_search_requires_authentication(): void
    {
        $this->getJson('/kompendium/suche?q=test')
            ->assertUnauthorized();
    }

    public function test_serien_requires_authentication(): void
    {
        $this->getJson('/kompendium/serien')
            ->assertUnauthorized();
    }

    public function test_index_requires_authentication(): void
    {
        $this->get('/kompendium')
            ->assertRedirect('/login');
    }
}
