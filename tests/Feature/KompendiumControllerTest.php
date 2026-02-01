<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;

class KompendiumControllerTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesUserWithRole;

    public function test_index_hides_search_when_points_insufficient(): void
    {
        $user = $this->actingMemberWithPoints(50);

        $response = $this->get('/kompendium');

        $response->assertOk();
        $response->assertViewHas('showSearch', false);
        $response->assertViewHas('userPoints', 50);
    }

    public function test_index_shows_search_when_enough_points(): void
    {
        $user = $this->actingMemberWithPoints(120);

        $response = $this->get('/kompendium');

        $response->assertOk();
        $response->assertViewHas('showSearch', true);
        $response->assertViewHas('userPoints', 120);
    }
}
