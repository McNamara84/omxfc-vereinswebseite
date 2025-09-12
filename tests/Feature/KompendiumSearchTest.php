<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Mockery;
use App\Models\RomanExcerpt;

class KompendiumSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function actingMember(int $points): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Mitglied']);
        $user->incrementTeamPoints($points);
        return $user;
    }

    public function test_search_requires_enough_points(): void
    {
        $user = $this->actingMember(50); // below 100
        $this->actingAs($user);

        $this->getJson('/kompendium/suche?q=test')
            ->assertStatus(403)
            ->assertJson(['message' => 'Mindestens 100 Punkte erforderlich (du hast 50).']);
    }

    public function test_search_validates_query_length(): void
    {
        $user = $this->actingMember(150);
        $this->actingAs($user);

        $this->getJson('/kompendium/suche?q=a')
            ->assertStatus(422);
    }

    public function test_search_returns_formatted_results(): void
    {
        $user = $this->actingMember(150);
        $this->actingAs($user);

        Storage::fake('private');
        Storage::disk('private')->put('/cycle1/001 - ExampleTitle.txt', 'Some example content with query word');

        $mock = Mockery::mock();
        $mock->shouldReceive('raw')->andReturn([
            'hits' => ['total_hits' => 1],
            'ids' => ['/cycle1/001 - ExampleTitle.txt'],
        ]);
        Mockery::mock('alias:' . RomanExcerpt::class)
            ->shouldReceive('search')
            ->with('example')
            ->andReturn($mock);

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
        $user = $this->actingMember(150);
        $this->actingAs($user);

        Storage::fake('private');

        $mock = Mockery::mock();
        $mock->shouldReceive('raw')->andReturn([
            'hits' => ['total_hits' => 0],
            'ids' => [],
        ]);
        Mockery::mock('alias:' . RomanExcerpt::class)
            ->shouldReceive('search')
            ->with('nomatch')
            ->andReturn($mock);

        $response = $this->getJson('/kompendium/suche?q=nomatch');

        $response->assertOk()
            ->assertJson([
                'currentPage' => 1,
                'lastPage' => 1,
                'data' => [],
            ]);
    }
}
