<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait MaddraxiversumControllerTestFixtures
{
    use RefreshDatabase;

    private function actingMember(string $role = 'Mitglied', int $points = 0): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role]);
        if ($points > 0) {
            $user->incrementTeamPoints($points);
        }

        return $user;
    }

    /**
     * Erstellt die Maddraxiversum-Belohnung und kauft sie für den User.
     */
    private function purchaseMaddraxiversumReward(User $user): void
    {
        $reward = Reward::firstOrCreate(['slug' => 'maddraxiversum'], ['title' => 'Maddraxiversum', 'description' => 'Zugang zum Maddraxiversum', 'category' => 'Minispiele', 'cost_baxx' => 9, 'is_active' => true, 'sort_order' => 0]);
        RewardPurchase::factory()->create(['user_id' => $user->id, 'reward_id' => $reward->id, 'cost_baxx' => $reward->cost_baxx]);
    }

    private function startMission(User $user): void
    {
        $this->postJson('/mission/starten', ['name' => 'Testmission', 'origin' => 'A', 'destination' => 'B', 'travel_duration' => 10, 'mission_duration' => 20]);
    }
}
