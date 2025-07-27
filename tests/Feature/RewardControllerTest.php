<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Team;

class RewardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(int $points = 0): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Mitglied']);
        if ($points) {
            $user->incrementTeamPoints($points);
        }
        return $user;
    }

    public function test_index_displays_rewards_and_user_points(): void
    {
        $user = $this->actingMember(5);
        $this->actingAs($user);
        $rewards = config('rewards');

        $response = $this->get('/belohnungen');

        $response->assertOk();
        $response->assertViewIs('rewards.index');
        $response->assertViewHas('rewards', $rewards);
        $response->assertViewHas('userPoints', 5);
    }
}
