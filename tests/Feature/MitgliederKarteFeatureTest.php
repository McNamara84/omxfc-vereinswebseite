<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

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

    public function test_map_view_shows_valid_members_with_jitter(): void
    {
        Cache::flush();
        $responses = [
            '11111' => ['lat' => '50.0', 'lon' => '8.0'],
            '22222' => ['lat' => '51.0', 'lon' => '9.0'],
            '12345' => ['lat' => '53.0', 'lon' => '11.0'],
            '33333' => ['lat' => '52.0', 'lon' => '10.0'],
        ];
        Http::fake(function ($request) use ($responses) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);
            $pc = $query['postalcode'];
            return Http::response([$responses[$pc]], 200);
        });

        $member = $this->actingMember('Mitglied', ['plz' => '11111', 'land' => 'Deutschland', 'stadt' => 'Ort1']);
        $member->incrementTeamPoints();
        $vorstand = $this->actingMember('Vorstand', ['plz' => '22222', 'land' => 'Deutschland', 'stadt' => 'Ort2']);
        $this->actingMember('Anwärter', ['plz' => '33333', 'land' => 'Deutschland']);
        $this->actingMember('Mitglied', ['plz' => '']);

        $this->actingAs($member);
        $response = $this->get('/mitglieder/karte');

        $response->assertOk();
        $response->assertViewIs('mitglieder.karte');

        $memberData = json_decode($response->viewData('memberData'), true);
        $this->assertCount(3, $memberData);
        $names = array_column($memberData, 'name');
        $this->assertContains($member->name, $names);
        $this->assertContains($vorstand->name, $names);

        $coords = $memberData[0]['name'] === $member->name ? $memberData[0] : $memberData[1];
        $this->assertNotEquals(50.0, $coords['lat']);
        $this->assertNotEquals(8.0, $coords['lon']);
        $this->assertLessThanOrEqual(0.005, abs($coords['lat'] - 50.0));
        $this->assertLessThanOrEqual(0.005, abs($coords['lon'] - 8.0));

        $stammtisch = json_decode($response->viewData('stammtischData'), true);
        $this->assertCount(3, $stammtisch);
    }

    public function test_coordinates_are_cached(): void
    {
        Cache::flush();
        $count = 0;
        $responses = ['12345' => ['lat' => '48.0', 'lon' => '11.0']];
        Http::fake(function ($request) use (&$count, $responses) {
            $count++;
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);
            return Http::response([$responses[$query['postalcode']]], 200);
        });

        $user = $this->actingMember('Mitglied', ['plz' => '12345', 'land' => 'Deutschland']);
        $user->incrementTeamPoints();
        $this->actingAs($user);

        $this->get('/mitglieder/karte');
        $this->get('/mitglieder/karte');

        $this->assertEquals(1, $count);
    }

    public function test_member_center_coordinates_are_average(): void
    {
        Cache::flush();
        $responses = [
            '11111' => ['lat' => '50.0', 'lon' => '8.0'],
            '22222' => ['lat' => '52.0', 'lon' => '10.0'],
            '12345' => ['lat' => '53.0', 'lon' => '11.0'],
        ];
        Http::fake(function ($request) use ($responses) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);
            return Http::response([$responses[$query['postalcode']]], 200);
        });

        $user = $this->actingMember('Mitglied', ['plz' => '11111', 'land' => 'Deutschland']);
        $user->incrementTeamPoints();
        $this->actingMember('Mitglied', ['plz' => '22222', 'land' => 'Deutschland']);

        $this->actingAs($user);
        $response = $this->get('/mitglieder/karte');
        $response->assertOk();
        $response->assertViewIs('mitglieder.karte');

        $memberCenterLat = $response->viewData('membersCenterLat');
        $memberCenterLon = $response->viewData('membersCenterLon');

        $this->assertEqualsWithDelta(51.6666666667, $memberCenterLat, 0.0001);
        $this->assertEqualsWithDelta(9.6666666667, $memberCenterLon, 0.0001);
    }
}
