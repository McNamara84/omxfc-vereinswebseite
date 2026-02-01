<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use App\Models\Mission;
use Illuminate\Support\Carbon;

class MaddraxiversumMissionTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesUserWithRole;

    public function test_start_mission_creates_record(): void
    {
        Carbon::setTestNow('2025-01-01 12:00:00');
        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->postJson('/mission/starten', [
            'name' => 'Testmission',
            'origin' => 'A',
            'destination' => 'B',
            'travel_duration' => 10,
            'mission_duration' => 20,
        ]);

        $response->assertOk()->assertJsonStructure([
            'message', 'arrival_at', 'mission_ends_at'
        ]);

        $this->assertDatabaseHas('missions', [
            'user_id' => $user->id,
            'name' => 'Testmission',
            'origin' => 'A',
            'destination' => 'B',
            'travel_duration' => 10,
            'mission_duration' => 20,
        ]);
    }

    public function test_check_mission_status_completes_and_rewards_points(): void
    {
        Carbon::setTestNow('2025-01-01 12:00:00');
        $user = $this->actingMember();
        $this->actingAs($user);

        $this->postJson('/mission/starten', [
            'name' => 'Testmission',
            'origin' => 'A',
            'destination' => 'B',
            'travel_duration' => 10,
            'mission_duration' => 20,
        ]);

        $mission = Mission::first();
        Carbon::setTestNow('2025-01-01 12:01:00');

        $this->postJson('/mission/status-pruefen')
            ->assertOk()
            ->assertJson(['status' => 'completed']);

        $mission->refresh();
        $this->assertTrue($mission->completed);
        $this->assertDatabaseCount('user_points', 1);
    }
}
