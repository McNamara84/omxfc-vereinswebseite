<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class MaddraxiversumControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(string $role = 'Mitglied', int $points = 0): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role]);
        if ($points > 0) {
            $user->incrementTeamPoints($points);
        }
        return $user;
    }

    public function test_index_shows_map_when_user_has_enough_points(): void
    {
        $user = $this->actingMember('Mitglied', 15);
        $this->actingAs($user);

        $response = $this->get('/maddraxiversum');

        $response->assertOk();
        $response->assertViewIs('maddraxiversum.index');
        $response->assertViewHas('showMap', true);
    }

    public function test_index_accessible_for_ehrenmitglied(): void
    {
        $user = $this->actingMember('Ehrenmitglied');
        $this->actingAs($user);

        $this->get('/maddraxiversum')->assertOk();
    }

    public function test_index_hides_map_when_points_are_too_low(): void
    {
        $user = $this->actingMember('Mitglied', 2);
        $this->actingAs($user);

        $response = $this->get('/maddraxiversum');

        $response->assertOk();
        $response->assertViewHas('showMap', false);
    }

    public function test_get_cities_returns_api_response(): void
    {
        Http::fake(['de.maddraxikon.com/*' => Http::response(['test' => 'data'], 200)]);
        $user = $this->actingMember('Mitglied', 10);
        $this->actingAs($user);

        $response = $this->get('/maddraxikon-staedte');

        $response->assertOk();
        $response->assertJson(['test' => 'data']);
    }

    public function test_get_mission_status_returns_none_when_no_mission_exists(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $this->getJson('/mission/status')
            ->assertOk()
            ->assertJson(['status' => 'none']);
    }

    private function startMission(User $user): void
    {
        $this->postJson('/mission/starten', [
            'name' => 'Testmission',
            'origin' => 'A',
            'destination' => 'B',
            'travel_duration' => 10,
            'mission_duration' => 20,
        ]);
    }

    public function test_get_mission_status_reports_progression(): void
    {
        Carbon::setTestNow('2025-01-01 12:00:00');
        $user = $this->actingMember();
        $this->actingAs($user);
        $this->startMission($user);

        Carbon::setTestNow('2025-01-01 12:00:05');
        $this->getJson('/mission/status')
            ->assertOk()
            ->assertJson([
                'status' => 'traveling',
                'current_location' => 'A',
            ]);

        Carbon::setTestNow('2025-01-01 12:00:15');
        $this->getJson('/mission/status')
            ->assertOk()
            ->assertJson([
                'status' => 'traveling',
                'current_location' => 'A',
            ]);

        Carbon::setTestNow('2025-01-01 12:01:00');
        $this->getJson('/mission/status')
            ->assertOk()
            ->assertJson([
                'status' => 'traveling',
                'current_location' => 'A',
            ]);
    }
}
