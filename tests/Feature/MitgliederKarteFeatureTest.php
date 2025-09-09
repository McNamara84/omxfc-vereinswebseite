<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MitgliederKarteFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(string $role = 'Mitglied', array $attributes = []): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(array_merge(['current_team_id' => $team->id], $attributes));
        $team->users()->attach($user, ['role' => $role]);

        return $user;
    }

    public function test_locked_view_when_user_has_no_points(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->get('/mitglieder/karte');

        $response->assertOk();
        $response->assertViewIs('mitglieder.karte-locked');
        $response->assertSee('Karte noch nicht verfügbar');
    }

    public function test_coordinates_are_cached(): void
    {
        Cache::flush();

        $user = $this->actingMember('Mitglied', ['plz' => '12345', 'land' => 'Deutschland', 'lat' => 48.0, 'lon' => 11.0]);
        $user->incrementTeamPoints();
        $this->actingAs($user);

        $this->get('/mitglieder/karte');
        $this->get('/mitglieder/karte');

        $team = $user->currentTeam;
        $this->assertTrue(Cache::has("member_map_data_team_{$team->id}"));
    }

    public function test_member_center_coordinates_are_average(): void
    {
        Cache::flush();

        $user = $this->actingMember('Mitglied', ['plz' => '11111', 'land' => 'Deutschland', 'lat' => 50.0, 'lon' => 8.0]);
        $user->incrementTeamPoints();
        $this->actingMember('Mitglied', ['plz' => '22222', 'land' => 'Deutschland', 'lat' => 52.0, 'lon' => 10.0]);
        $this->actingMember('Mitglied', ['plz' => '12345', 'land' => 'Deutschland', 'lat' => 53.0, 'lon' => 11.0]);

        $this->actingAs($user);
        $response = $this->get('/mitglieder/karte');
        $response->assertOk();
        $response->assertViewIs('mitglieder.karte');

        $memberCenterLat = $response->viewData('membersCenterLat');
        $memberCenterLon = $response->viewData('membersCenterLon');

        $this->assertEqualsWithDelta(51.6666666667, $memberCenterLat, 0.0001);
        $this->assertEqualsWithDelta(9.6666666667, $memberCenterLon, 0.0001);
    }

    public function test_map_data_is_cached(): void
    {
        Cache::flush();

        $user = $this->actingMember('Mitglied', ['plz' => '12345', 'land' => 'Deutschland', 'lat' => 48.0, 'lon' => 11.0]);
        $user->incrementTeamPoints();
        $this->actingAs($user);

        $this->get('/mitglieder/karte');

        $team = $user->currentTeam;
        $cacheKey = "member_map_data_team_{$team->id}";
        $this->assertTrue(Cache::has($cacheKey));
    }
}