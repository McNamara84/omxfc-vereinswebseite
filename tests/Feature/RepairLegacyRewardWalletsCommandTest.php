<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairLegacyRewardWalletsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_backfills_safe_legacy_purchases_with_apply(): void
    {
        $membersTeam = Team::membersTeam();

        $this->assertNotNull($membersTeam);

        $user = User::factory()->create();
        $user->teams()->attach($membersTeam, ['role' => Role::Mitglied->value]);
        $user->switchTeam($membersTeam);

        $reward = Reward::factory()->create(['slug' => 'safe-legacy-repair']);
        $purchase = RewardPurchase::create([
            'user_id' => $user->id,
            'reward_id' => $reward->id,
            'wallet_team_id' => null,
            'cost_baxx' => 5,
            'purchased_at' => now(),
        ]);

        $this->artisan('rewards:repair-legacy-wallets', ['--apply' => true])
            ->assertExitCode(0);

        $purchase->refresh();

        $this->assertSame($membersTeam->id, $purchase->wallet_team_id);
        $this->assertNotNull($purchase->updated_at);
    }

    public function test_command_can_backfill_selected_ambiguous_user_after_manual_review(): void
    {
        $membersTeam = Team::membersTeam();

        $this->assertNotNull($membersTeam);

        $otherTeam = Team::factory()->create([
            'personal_team' => false,
            'name' => 'AG Legacy',
        ]);

        $user = User::factory()->create();
        $user->teams()->attach($membersTeam, ['role' => Role::Mitglied->value]);
        $user->teams()->attach($otherTeam, ['role' => Role::Mitglied->value]);
        $user->switchTeam($membersTeam);

        $reward = Reward::factory()->create(['slug' => 'ambiguous-legacy-repair']);
        $purchase = RewardPurchase::create([
            'user_id' => $user->id,
            'reward_id' => $reward->id,
            'wallet_team_id' => null,
            'cost_baxx' => 5,
            'purchased_at' => now(),
        ]);

        $this->artisan('rewards:repair-legacy-wallets', ['--apply' => true])
            ->assertExitCode(0);

        $purchase->refresh();
        $this->assertNull($purchase->wallet_team_id);

        $this->artisan('rewards:repair-legacy-wallets', ['--user' => [$user->id], '--apply' => true])
            ->assertExitCode(0);

        $purchase->refresh();
        $this->assertSame($membersTeam->id, $purchase->wallet_team_id);
        $this->assertNotNull($purchase->updated_at);
    }
}
