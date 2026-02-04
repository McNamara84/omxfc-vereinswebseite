<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Database\Seeders\DashboardSampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardSampleSeederCoordinatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_sample_seeder_sets_coordinates(): void
    {
        Cache::forget(Team::MEMBERS_TEAM_CACHE_KEY);

        Team::factory()->create([
            'name' => 'Mitglieder',
            'personal_team' => false,
        ]);

        $this->seed(DashboardSampleSeeder::class);

        $sampleUsers = User::whereIn('email', [
            'alex.beispiel@example.com',
            'bianca.beispiel@example.com',
            'chris.beispiel@example.com',
        ])->get();

        $this->assertCount(3, $sampleUsers);

        $membersTeam = Team::membersTeam();
        $this->assertNotNull($membersTeam);

        $membersTeam->loadMissing('users');
        $this->assertTrue(
            $sampleUsers->every(fn (User $user) => $membersTeam->users->contains($user)),
            'Sample users should belong to the Mitglieder team',
        );

        foreach ($sampleUsers as $user) {
            $this->assertNotNull($user->lat, "Lat missing for {$user->email}");
            $this->assertNotNull($user->lon, "Lon missing for {$user->email}");
        }
    }
}
