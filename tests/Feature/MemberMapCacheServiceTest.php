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

        $team->users()->attach($user1, ['role' => 'Mitglied']);
        $team->users()->attach($user2, ['role' => 'Mitglied']);

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

        $team->users()->attach($user, ['role' => 'Mitglied']);

        $service = new MemberMapCacheService();

        $data = $service->getMemberMapData($team);
        $this->assertCount(1, $data['memberData']);
        $this->assertEquals((float) self::DEFAULT_LAT, $data['centerLat']);
        $this->assertEquals((float) self::DEFAULT_LON, $data['centerLon']);
    }
}

