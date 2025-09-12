<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;

class KompendiumControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(int $points = 0): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => \App\Enums\Role::Mitglied->value]);
        if ($points) {
            $user->incrementTeamPoints($points);
        }
        return $user;
    }

    public function test_index_hides_search_when_points_insufficient(): void
    {
        $user = $this->actingMember(50);
        $this->actingAs($user);

        $response = $this->get('/kompendium');

        $response->assertOk();
        $response->assertViewHas('showSearch', false);
        $response->assertViewHas('userPoints', 50);
    }

    public function test_index_shows_search_when_enough_points(): void
    {
        $user = $this->actingMember(120);
        $this->actingAs($user);

        $response = $this->get('/kompendium');

        $response->assertOk();
        $response->assertViewHas('showSearch', true);
        $response->assertViewHas('userPoints', 120);
    }
}
