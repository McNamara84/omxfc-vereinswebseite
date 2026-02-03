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
            ->assertJson(['message' => 'Mindestens 100 Punkte erforderlich (du hast 50).']);
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
}
