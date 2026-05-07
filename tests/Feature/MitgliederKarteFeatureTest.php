<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Reward;
use App\Models\Team;
use App\Models\UserPoint;
use App\Models\User;
use App\Services\RewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use LogicException;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class MitgliederKarteFeatureTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.testing_minimal_layout', true);
    }

    private function purchaseMemberMapReward(User $user): void
    {
        $reward = Reward::query()->where('slug', 'mitgliederkarte')->firstOrFail();
        $membersTeam = Team::membersTeam();

        $this->assertNotNull($membersTeam, 'Das Mitglieder-Team fehlt für purchaseMemberMapReward().');

        if (! $membersTeam->users()->whereKey($user->id)->exists()) {
            $membersTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
        }

        UserPoint::query()->create([
            'user_id' => $user->id,
            'team_id' => $membersTeam->id,
            'points' => $reward->cost_baxx,
        ]);
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

    public function test_locked_preview_remains_reachable_when_members_team_is_missing(): void
    {
        $user = $this->actingMember();

        Team::membersTeam()?->delete();

        $this->actingAs($user->fresh());

        $response = $this->get('/mitglieder/karte');

        $response->assertOk();
        $response->assertViewIs('mitglieder.karte');
        $response->assertSee('Mitgliederkarte freischalten');
        $response->assertViewHas('walletWarning', fn ($warning) => is_string($warning) && $warning !== '');
        $this->assertSame('[]', $response->viewData('memberData'));
    }

    public function test_missing_member_map_reward_is_restored_instead_of_returning_404(): void
    {
        $user = $this->actingMember();

        Reward::query()->where('slug', 'mitgliederkarte')->delete();

        $response = $this->actingAs($user)
            ->get('/mitglieder/karte');

        $response->assertOk();
        $response->assertViewIs('mitglieder.karte');
        $response->assertSee('Mitgliederkarte freischalten');

        $reward = Reward::query()->where('slug', 'mitgliederkarte')->first();

        $this->assertNotNull($reward);
        $this->assertSame('Mitgliederkarte', $reward->title);
        $this->assertSame('Allgemein', $reward->category);
        $this->assertTrue($reward->is_active);
        $this->assertGreaterThan(0, $reward->cost_baxx);
    }

    public function test_purchase_returns_friendly_error_when_reward_purchase_throws_logic_exception(): void
    {
        $user = $this->actingMember();

        $this->mock(RewardService::class, function ($mock) {
            $mock->shouldReceive('purchaseReward')
                ->once()
                ->andThrow(new LogicException('Boom'));
        });

        $response = $this->actingAs($user)
            ->post(route('mitglieder.karte.purchase'));

        $response->assertRedirect(route('mitglieder.karte'));
        $response->assertSessionHasErrors('reward');
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

        $user = $this->actingMember(Role::Mitglied, ['plz' => '12345', 'land' => 'Deutschland']);
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

        $user = $this->actingMember(Role::Mitglied, ['plz' => '11111', 'land' => 'Deutschland']);
        $this->purchaseMemberMapReward($user);
        $this->actingMember(Role::Mitglied, ['plz' => '22222', 'land' => 'Deutschland']);

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

        $user = $this->actingMember(Role::Mitglied, ['plz' => '12345', 'land' => 'Deutschland']);
        $this->purchaseMemberMapReward($user);
        $this->actingAs($user);

        $this->get('/mitglieder/karte');

        $team = Team::membersTeam();
        $cacheKey = "member_map_data_team_{$team->id}";
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_unlocked_map_uses_members_team_even_when_current_team_differs(): void
    {
        Cache::flush();
        Http::swap(new Factory);
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([['lat' => self::DEFAULT_LAT, 'lon' => self::DEFAULT_LON]], 200),
        ]);

        $membersTeam = Team::membersTeam();
        $otherTeam = Team::factory()->create(['name' => 'Andere AG']);
        $user = User::factory()->create([
            'current_team_id' => $otherTeam->id,
            'plz' => '12345',
            'land' => 'Deutschland',
            'stadt' => 'Musterstadt',
        ]);
        $membersTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
        $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);

        $this->purchaseMemberMapReward($user);
        $this->actingAs($user);

        $response = $this->get('/mitglieder/karte');

        $response->assertOk();
        $memberData = json_decode($response->viewData('memberData'), true);

        $this->assertContains('Musterstadt', array_column($memberData, 'city'));
        $this->assertTrue(Cache::has("member_map_data_team_{$membersTeam->id}"));
        $this->assertFalse(Cache::has("member_map_data_team_{$otherTeam->id}"));
    }

    public function test_map_view_contains_accessibility_attributes_and_data(): void
    {
        Cache::flush();
        Http::swap(new Factory);
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([['lat' => self::DEFAULT_LAT, 'lon' => self::DEFAULT_LON]], 200),
        ]);

        $user = $this->actingMember(Role::Mitglied, [
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
