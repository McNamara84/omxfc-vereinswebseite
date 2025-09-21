<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Database\Seeders\DashboardSampleSeeder;
use Database\Seeders\TodoPlaywrightSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSampleSeederCoordinatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_sample_seeder_sets_coordinates(): void
    {
        $this->seed(TodoPlaywrightSeeder::class);
        $this->seed(DashboardSampleSeeder::class);

        $team = Team::membersTeam();
        $this->assertNotNull($team);

        $sampleUsers = User::whereIn('email', [
            'alex.beispiel@example.com',
            'bianca.beispiel@example.com',
            'chris.beispiel@example.com',
        ])->get();

        $this->assertCount(3, $sampleUsers);

        foreach ($sampleUsers as $user) {
            $this->assertNotNull($user->lat, "Lat missing for {$user->email}");
            $this->assertNotNull($user->lon, "Lon missing for {$user->email}");
        }
    }
}
