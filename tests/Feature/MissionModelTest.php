<?php

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_mission_can_be_created_with_mass_assignment(): void
    {
        Carbon::setTestNow('2025-01-01 12:00:00');
        $user = User::factory()->create();

        $mission = Mission::create([
            'user_id' => $user->id,
            'name' => 'Test Mission',
            'origin' => 'A',
            'destination' => 'B',
            'travel_duration' => 10,
            'mission_duration' => 20,
            'started_at' => Carbon::now(),
            'arrival_at' => Carbon::now()->addMinutes(10),
            'mission_ends_at' => Carbon::now()->addMinutes(40),
        ]);

        $this->assertDatabaseHas('missions', [
            'id' => $mission->id,
            'user_id' => $user->id,
            'name' => 'Test Mission',
            'origin' => 'A',
            'destination' => 'B',
            'travel_duration' => 10,
            'mission_duration' => 20,
            'completed' => false,
        ]);
    }

    public function test_mission_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $mission = Mission::create([
            'user_id' => $user->id,
            'name' => 'Test Mission',
            'origin' => 'A',
            'destination' => 'B',
            'travel_duration' => 10,
            'mission_duration' => 20,
        ]);

        $this->assertTrue($mission->user->is($user));
    }

    public function test_completed_attribute_can_be_updated(): void
    {
        $user = User::factory()->create();
        $mission = Mission::create([
            'user_id' => $user->id,
            'name' => 'Test Mission',
            'origin' => 'A',
            'destination' => 'B',
            'travel_duration' => 10,
            'mission_duration' => 20,
        ]);

        $this->assertFalse((bool) $mission->completed);

        $mission->update(['completed' => true]);

        $this->assertTrue($mission->fresh()->completed);
    }

    public function test_date_attributes_are_cast_to_carbon_instances(): void
    {
        Carbon::setTestNow('2025-01-01 12:00:00');
        $user = User::factory()->create();

        $mission = Mission::create([
            'user_id' => $user->id,
            'name' => 'Date Mission',
            'origin' => 'A',
            'destination' => 'B',
            'travel_duration' => 5,
            'mission_duration' => 10,
            'started_at' => Carbon::now(),
            'arrival_at' => Carbon::now()->addMinutes(5),
            'mission_ends_at' => Carbon::now()->addMinutes(20),
        ]);

        $mission->refresh();

        $this->assertInstanceOf(Carbon::class, $mission->started_at);
        $this->assertInstanceOf(Carbon::class, $mission->arrival_at);
        $this->assertInstanceOf(Carbon::class, $mission->mission_ends_at);
        $this->assertTrue($mission->started_at->equalTo(Carbon::now()));
        $this->assertTrue($mission->arrival_at->equalTo(Carbon::now()->addMinutes(5)));
        $this->assertTrue($mission->mission_ends_at->equalTo(Carbon::now()->addMinutes(20)));
    }

    public function test_query_for_active_missions_only_returns_uncompleted_ones(): void
    {
        $user = User::factory()->create();
        Mission::create([
            'user_id' => $user->id,
            'name' => 'Active Mission',
            'origin' => 'A',
            'destination' => 'B',
            'travel_duration' => 10,
            'mission_duration' => 20,
        ]);
        Mission::create([
            'user_id' => $user->id,
            'name' => 'Completed Mission',
            'origin' => 'A',
            'destination' => 'B',
            'travel_duration' => 5,
            'mission_duration' => 5,
            'completed' => true,
        ]);

        $activeMissions = Mission::where('user_id', $user->id)
            ->where('completed', false)
            ->get();

        $this->assertCount(1, $activeMissions);
        $this->assertEquals('Active Mission', $activeMissions->first()->name);
    }
}
