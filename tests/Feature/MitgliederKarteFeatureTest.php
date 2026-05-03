<?php

namespace Tests\Feature;

use App\Models\Reward;
use App\Models\User;
use App\Services\RewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class MitgliederKarteFeatureTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function purchaseMemberMapReward(User $user): void
    {
        $reward = Reward::query()->where('slug', 'mitgliederkarte')->firstOrFail();

        $user->incrementTeamPoints($reward->cost_baxx);
        app(RewardService::class)->purchaseReward($user, $reward);
    }

    public function test_locked_members_see_preview_with_unlock_cta(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->get('/mitglieder/karte');

        $response->assertOk();
        $response->assertViewIs('mitglieder.karte');
        $response->assertSee('Mitgliederkarte freischalten');
        $response->assertSee('data-member-map', false);
    }

    public function test_coordinates_are_cached(): void
    {
        Cache::flush();
        $count = 0;
        $responses = ['12345' => ['lat' => self::DEFAULT_LAT, 'lon' => self::DEFAULT_LON]];
        Http::swap(new Factory);
        Http::fake([
            'nominatim.openstreetmap.org/*' => function ($request) use (&$count, $responses) {
                $count++;
                parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

                return Http::response([$responses[$query['postalcode']]], 200);
            },
        ]);

        $user = $this->actingMember('Mitglied', ['plz' => '12345', 'land' => 'Deutschland']);
        $this->purchaseMemberMapReward($user);
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
        Http::swap(new Factory);
        Http::fake([
            'nominatim.openstreetmap.org/*' => function ($request) use ($responses) {
                parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

                return Http::response([$responses[$query['postalcode']]], 200);
            },
        ]);

        $user = $this->actingMember('Mitglied', ['plz' => '11111', 'land' => 'Deutschland']);
        $this->purchaseMemberMapReward($user);
        $this->actingMember('Mitglied', ['plz' => '22222', 'land' => 'Deutschland']);

        $this->actingAs($user);
        $response = $this->get('/mitglieder/karte');
        $response->assertOk();
        $response->assertViewIs('mitglieder.karte');

        $memberCenterLat = $response->viewData('membersCenterLat');
        $memberCenterLon = $response->viewData('membersCenterLon');

        // The seeded admin user has coordinates (48.0, 11.0) provided by the
        // default HTTP stubs in the test case. Together with the two members
        // created above (50/8 and 52/10), the expected center is the average
        // of all three sets of coordinates.
        $this->assertEqualsWithDelta(50.0, $memberCenterLat, 0.0001);
        $this->assertEqualsWithDelta(9.6666666667, $memberCenterLon, 0.0001);
    }

    public function test_map_data_is_cached(): void
    {
        Cache::flush();
        Http::swap(new Factory);
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([['lat' => self::DEFAULT_LAT, 'lon' => self::DEFAULT_LON]], 200),
        ]);

        $user = $this->actingMember('Mitglied', ['plz' => '12345', 'land' => 'Deutschland']);
        $this->purchaseMemberMapReward($user);
        $this->actingAs($user);

        $this->get('/mitglieder/karte');

        $team = $user->currentTeam;
        $cacheKey = "member_map_data_team_{$team->id}";
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_map_view_contains_accessibility_attributes_and_data(): void
    {
        Cache::flush();
        Http::swap(new Factory);
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([['lat' => self::DEFAULT_LAT, 'lon' => self::DEFAULT_LON]], 200),
        ]);

        $user = $this->actingMember('Mitglied', [
            'plz' => '12345',
            'land' => 'Deutschland',
            'stadt' => 'Musterstadt',
        ]);
        $this->purchaseMemberMapReward($user);
        $this->actingAs($user);

        $response = $this->get('/mitglieder/karte');

        $response->assertOk();
        $response->assertViewIs('mitglieder.karte');
        $response->assertSee('data-member-map', false);
        $response->assertSee('aria-label="Mitgliederkarte"', false);

        $memberData = json_decode($response->viewData('memberData'), true);

        $this->assertIsArray($memberData);
        $this->assertNotEmpty($memberData);
        $this->assertContains('Musterstadt', array_column($memberData, 'city'));
        $this->assertContains(route('profile.view', $user->id), array_column($memberData, 'profile_url'));

        $stammtischData = json_decode($response->viewData('stammtischData'), true);
        $this->assertIsArray($stammtischData);
        $this->assertCount(3, $stammtischData);
        $this->assertSame('Regionalstammtisch München', $stammtischData[0]['name']);
    }
}
