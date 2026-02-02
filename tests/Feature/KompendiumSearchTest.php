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
        Storage::disk('private')->put('/cycle1/001 - ExampleTitle.txt', 'Some example content with query word');

        // Mock den KompendiumSearchService
        $this->mock(KompendiumSearchService::class, function ($mock) {
            $mock->shouldReceive('search')
                ->with('example')
                ->once()
                ->andReturn([
                    'hits' => ['total_hits' => 1],
                    'ids' => ['/cycle1/001 - ExampleTitle.txt'],
                ]);
        });

        $response = $this->getJson('/kompendium/suche?q=example');

        $response->assertOk()
            ->assertJson([
                'currentPage' => 1,
                'lastPage' => 1,
                'data' => [[
                    'cycle' => 'Cycle1-Zyklus',
                    'romanNr' => '001',
                    'title' => 'ExampleTitle',
                ]],
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
            ]);
    }
}
