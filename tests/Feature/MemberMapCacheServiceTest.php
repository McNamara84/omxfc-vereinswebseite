<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\MemberMapCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MemberMapCacheServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_caches_and_refreshes_member_map_data(): void
    {
        Cache::flush();

        $team = Team::factory()->create();
        $team->users()->detach();

        $user1 = User::factory()->create([
            'plz' => '11111',
            'stadt' => 'City1',
            'lat' => 10.0,
            'lon' => 20.0,
            'current_team_id' => $team->id,
        ]);
        $user2 = User::factory()->create([
            'plz' => '22222',
            'stadt' => 'City2',
            'lat' => 20.0,
            'lon' => 30.0,
            'current_team_id' => $team->id,
        ]);

        $team->users()->attach($user1, ['role' => \App\Enums\Role::Mitglied->value]);
        $team->users()->attach($user2, ['role' => \App\Enums\Role::Mitglied->value]);

        $service = new MemberMapCacheService();

        $data = $service->getMemberMapData($team);
        $this->assertCount(2, $data['memberData']);
        $this->assertEquals(15.0, $data['centerLat']);
        $this->assertEquals(25.0, $data['centerLon']);

        $user1->update(['lat' => 30.0, 'lon' => 40.0]);

        $cached = $service->getMemberMapData($team);
        $this->assertEquals(15.0, $cached['centerLat']);
        $this->assertEquals(25.0, $cached['centerLon']);

        $refreshed = $service->refresh($team);
        $this->assertEquals(25.0, $refreshed['centerLat']);
        $this->assertEquals(35.0, $refreshed['centerLon']);
    }

    public function test_member_without_coordinates_is_geocoded_and_included(): void
    {
        Cache::flush();

        $team = Team::factory()->create();
        $team->users()->detach();

        $user = User::factory()->create([
            'plz' => '12345',
            'stadt' => 'City',
            'lat' => null,
            'lon' => null,
            'current_team_id' => $team->id,
        ]);

        $team->users()->attach($user, ['role' => \App\Enums\Role::Mitglied->value]);

        $service = new MemberMapCacheService();

        $data = $service->getMemberMapData($team);
        $this->assertCount(1, $data['memberData']);
        $this->assertEquals((float) self::DEFAULT_LAT, $data['centerLat']);
        $this->assertEquals((float) self::DEFAULT_LON, $data['centerLon']);
    }

    public function test_members_without_plz_or_with_anwaerter_role_are_excluded(): void
    {
        Cache::flush();

        $team = Team::factory()->create();
        $team->users()->detach();

        $valid = User::factory()->create([
            'plz' => '12345',
            'stadt' => 'Valid City',
            'lat' => 10.0,
            'lon' => 20.0,
            'current_team_id' => $team->id,
        ]);
        $anwaerter = User::factory()->create([
            'plz' => '54321',
            'stadt' => 'Skip City',
            'lat' => 30.0,
            'lon' => 40.0,
            'current_team_id' => $team->id,
        ]);
        $noPlz = User::factory()->create([
            'plz' => '',
            'stadt' => 'NoPlz City',
            'lat' => 50.0,
            'lon' => 60.0,
            'current_team_id' => $team->id,
        ]);

        $team->users()->attach($valid, ['role' => \App\Enums\Role::Mitglied->value]);
        $team->users()->attach($anwaerter, ['role' => 'Anwärter']);
        $team->users()->attach($noPlz, ['role' => \App\Enums\Role::Mitglied->value]);

        $service = new MemberMapCacheService();

        $data = $service->getMemberMapData($team);

        $this->assertCount(1, $data['memberData']);
        $member = $data['memberData'][0];
        $this->assertEquals('Valid City', $member['city']);
        $this->assertEquals(10.0, $data['centerLat']);
        $this->assertEquals(20.0, $data['centerLon']);
    }

    public function test_jitter_is_applied_deterministically(): void
    {
        Cache::flush();

        $team = Team::factory()->create();
        $team->users()->detach();

        $user = User::factory()->create([
            'plz' => '11111',
            'stadt' => 'Jitter City',
            'lat' => 10.0,
            'lon' => 20.0,
            'current_team_id' => $team->id,
        ]);

        $team->users()->attach($user, ['role' => \App\Enums\Role::Mitglied->value]);

        $service = new MemberMapCacheService();

        mt_srand(1234);
        $data = $service->getMemberMapData($team);

        mt_srand(1234);
        $expectedLat = 10.0 + (mt_rand(-50, 50) / 10000);
        $expectedLon = 20.0 + (mt_rand(-50, 50) / 10000);

        $this->assertEqualsWithDelta($expectedLat, $data['memberData'][0]['lat'], 0.000001);
        $this->assertEqualsWithDelta($expectedLon, $data['memberData'][0]['lon'], 0.000001);
    }

    public function test_refresh_generates_new_jitter(): void
    {
        Cache::flush();

        $team = Team::factory()->create();
        $team->users()->detach();

        $user = User::factory()->create([
            'plz' => '22222',
            'stadt' => 'Refresh City',
            'lat' => 10.0,
            'lon' => 20.0,
            'current_team_id' => $team->id,
        ]);

        $team->users()->attach($user, ['role' => \App\Enums\Role::Mitglied->value]);

        $service = new MemberMapCacheService();

        mt_srand(1);
        $first = $service->getMemberMapData($team);
        $firstLat = $first['memberData'][0]['lat'];
        $firstLon = $first['memberData'][0]['lon'];

        mt_srand(2);
        $second = $service->refresh($team);
        $secondLat = $second['memberData'][0]['lat'];
        $secondLon = $second['memberData'][0]['lon'];

        $this->assertNotEquals($firstLat, $secondLat);
        $this->assertNotEquals($firstLon, $secondLon);
    }
}

