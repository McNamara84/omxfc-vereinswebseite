<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Database\Seeders\TodoPlaywrightSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaywrightSeederCoordinatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_playwright_seeder_populates_coordinates_for_map(): void
    {
        $this->seed(TodoPlaywrightSeeder::class);

        $admin = User::firstWhere('email', 'info@maddraxikon.com');
        $member = User::firstWhere('email', 'playwright-member@example.com');
        $mapMember = User::firstWhere('email', 'playwright-map-locked@example.com');
        $team = Team::membersTeam();

        $this->assertNotNull($admin);
        $this->assertNotNull($member);
        $this->assertNotNull($mapMember);
        $this->assertNotNull($team);

        $this->assertEqualsWithDelta(48.137154, (float) $admin->lat, 0.000001);
        $this->assertEqualsWithDelta(11.576124, (float) $admin->lon, 0.000001);
        $this->assertEqualsWithDelta(50.9767, (float) $member->lat, 0.0001);
        $this->assertEqualsWithDelta(6.8868, (float) $member->lon, 0.0001);
        $this->assertEqualsWithDelta(50.9787, (float) $mapMember->lat, 0.0001);
        $this->assertEqualsWithDelta(11.0328, (float) $mapMember->lon, 0.0001);
        $this->assertDatabaseMissing('user_points', ['user_id' => $mapMember->id]);
        $this->assertDatabaseMissing('reward_purchases', ['user_id' => $mapMember->id]);
    }
}
