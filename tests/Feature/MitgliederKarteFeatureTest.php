<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Reward;
use App\Models\Team;
use App\Models\User;
use App\Services\RewardService;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use LogicException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MitgliederKarteFeatureTestFixtures::class);

beforeEach(function (): void {
    Config::set('app.testing_minimal_layout', true);
});

test('locked members see preview with unlock cta', function (): void {
    $user = $this->actingMember();
    $this->actingAs($user);
    $response = $this->get('/mitglieder/karte');
    $response->assertOk();
    $response->assertViewIs('mitglieder.karte');
    $response->assertSee('Mitgliederkarte freischalten');
    $response->assertSee('data-member-map', false);
    $response->assertSee('data-testid="member-map-earn-baxx-cta"', false);
    $response->assertSee('Zu Baxx verdienen');
    $response->assertViewHas('availableBaxx', 0);
    $response->assertViewHas('canPurchase', false);
});

test('locked preview remains reachable when members team is missing', function (): void {
    $user = $this->actingMember();
    Team::membersTeam()?->delete();
    $this->actingAs($user->fresh());
    $response = $this->get('/mitglieder/karte');
    $response->assertOk();
    $response->assertViewIs('mitglieder.karte');
    $response->assertSee('Mitgliederkarte freischalten');
    $response->assertViewHas('walletWarning', fn ($warning) => is_string($warning) && $warning !== '');
    expect($response->viewData('memberData'))->toBe('[]');
});

test('missing member map reward is restored instead of returning 404', function (): void {
    $user = $this->actingMember();
    Reward::query()->where('slug', 'mitgliederkarte')->delete();
    $response = $this->actingAs($user)->get('/mitglieder/karte');
    $response->assertOk();
    $response->assertViewIs('mitglieder.karte');
    $response->assertSee('Mitgliederkarte freischalten');
    $reward = Reward::query()->where('slug', 'mitgliederkarte')->first();
    expect($reward)->not->toBeNull()
        ->and($reward->title)->toBe('Mitgliederkarte')
        ->and($reward->category)->toBe('Allgemein')
        ->and($reward->is_active)->toBeTrue()
        ->and($reward->cost_baxx)->toBeGreaterThan(0);
});

test('purchase returns friendly error when reward purchase throws logic exception', function (): void {
    $user = $this->actingMember();
    $reward = Reward::query()->firstOrCreate(['slug' => 'mitgliederkarte'], ['title' => 'Mitgliederkarte', 'description' => 'Interaktive Karte aller freigeschalteten Mitglieder.', 'cost_baxx' => 250, 'is_active' => true, 'category' => 'Allgemein', 'sort_order' => 0]);
    $this->mock(RewardService::class, function ($mock) use ($reward) {
        $mock->shouldReceive('resolveMitgliederkarteReward')->once()->andReturn($reward);
        $mock->shouldReceive('purchaseReward')->once()->andThrow(new LogicException('Boom'));
    });
    $response = $this->actingAs($user)->post(route('mitglieder.karte.purchase'));
    $response->assertRedirect(route('mitglieder.karte'));
    $response->assertSessionHasErrors('reward');
});

test('coordinates are cached', function (): void {
    Cache::flush();
    $count = 0;
    $responses = ['12345' => ['lat' => self::DEFAULT_LAT, 'lon' => self::DEFAULT_LON]];
    Http::swap(new Factory);
    Http::fake(['nominatim.openstreetmap.org/*' => function ($request) use (&$count, $responses) {
        $count++;
        parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

        return Http::response([$responses[$query['postalcode']]], 200);
    }]);
    $user = $this->actingMember(Role::Mitglied, ['plz' => '12345', 'land' => 'Deutschland']);
    $this->purchaseMemberMapReward($user);
    $this->actingAs($user);
    $this->get('/mitglieder/karte');
    $this->get('/mitglieder/karte');
    expect($count)->toEqual(1);
});

test('member center coordinates are average', function (): void {
    Cache::flush();
    $responses = ['11111' => ['lat' => '50.0', 'lon' => '8.0'], '22222' => ['lat' => '52.0', 'lon' => '10.0'], '12345' => ['lat' => self::DEFAULT_LAT, 'lon' => self::DEFAULT_LON]];
    Http::swap(new Factory);
    Http::fake(['nominatim.openstreetmap.org/*' => function ($request) use ($responses) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

        return Http::response([$responses[$query['postalcode']]], 200);
    }]);
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
    expect($memberCenterLat)->toEqualWithDelta(50.0, 0.0001);
    expect($memberCenterLon)->toEqualWithDelta(9.666666666699999, 0.0001);
});

test('map data is cached', function (): void {
    Cache::flush();
    Http::swap(new Factory);
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => self::DEFAULT_LAT, 'lon' => self::DEFAULT_LON]], 200)]);
    $user = $this->actingMember(Role::Mitglied, ['plz' => '12345', 'land' => 'Deutschland']);
    $this->purchaseMemberMapReward($user);
    $this->actingAs($user);
    $this->get('/mitglieder/karte');
    $team = Team::membersTeam();
    $cacheKey = "member_map_data_v2_team_{$team->id}";
    expect(Cache::has($cacheKey))->toBeTrue();
});

test('unlocked map uses members team even when current team differs', function (): void {
    Cache::flush();
    Http::swap(new Factory);
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => self::DEFAULT_LAT, 'lon' => self::DEFAULT_LON]], 200)]);
    $membersTeam = Team::membersTeam();
    $otherTeam = Team::factory()->create(['name' => 'Andere AG']);
    $user = User::factory()->create(['current_team_id' => $otherTeam->id, 'plz' => '12345', 'land' => 'Deutschland', 'stadt' => 'Musterstadt']);
    $membersTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
    $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
    $this->purchaseMemberMapReward($user);
    $this->actingAs($user);
    $response = $this->get('/mitglieder/karte');
    $response->assertOk();
    $memberData = json_decode($response->viewData('memberData'), true);
    expect(array_column($memberData, 'city'))->toContain('Musterstadt')
        ->and(Cache::has("member_map_data_v2_team_{$membersTeam->id}"))->toBeTrue()
        ->and(Cache::has("member_map_data_v2_team_{$otherTeam->id}"))->toBeFalse();
});

test('map view contains accessibility attributes and data', function (): void {
    Cache::flush();
    Http::swap(new Factory);
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => self::DEFAULT_LAT, 'lon' => self::DEFAULT_LON]], 200)]);
    $user = $this->actingMember(Role::Mitglied, ['plz' => '12345', 'land' => 'Deutschland', 'stadt' => 'Musterstadt']);
    $this->purchaseMemberMapReward($user);
    $this->actingAs($user);
    $response = $this->get('/mitglieder/karte');
    $response->assertOk();
    $response->assertViewIs('mitglieder.karte');
    $response->assertSee('data-member-map', false);
    $response->assertSee('aria-label="Mitgliederkarte"', false);
    $response->assertSee('style="min-height: 600px;"', false);
    $memberData = json_decode($response->viewData('memberData'), true);
    expect($memberData)->toBeArray()->not->toBeEmpty()
        ->and(array_column($memberData, 'city'))->toContain('Musterstadt')
        ->and(array_column($memberData, 'profile_url'))->toContain(route('profile.view', $user->id));
    $stammtischData = json_decode($response->viewData('stammtischData'), true);
    expect($stammtischData)->toBeArray()
        ->toHaveCount(3)
        ->and($stammtischData[0]['name'])->toBe('Regionalstammtisch München');
});
