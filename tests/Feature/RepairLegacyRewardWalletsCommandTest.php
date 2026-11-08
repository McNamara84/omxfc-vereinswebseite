<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('command backfills safe legacy purchases with apply', function (): void {
    $membersTeam = Team::membersTeam();
    expect($membersTeam)->not->toBeNull();
    $user = User::factory()->create();
    $user->teams()->attach($membersTeam, ['role' => Role::Mitglied->value]);
    $user->switchTeam($membersTeam);
    $reward = Reward::factory()->create(['slug' => 'safe-legacy-repair']);
    $purchase = RewardPurchase::create(['user_id' => $user->id, 'reward_id' => $reward->id, 'wallet_team_id' => null, 'cost_baxx' => 5, 'purchased_at' => now()]);
    $this->artisan('rewards:repair-legacy-wallets', ['--apply' => true])->assertExitCode(0);
    $purchase->refresh();
    expect($purchase->wallet_team_id)->toBe($membersTeam->id)
        ->and($purchase->updated_at)->not->toBeNull();
});

test('command can backfill selected ambiguous user after manual review', function (): void {
    $membersTeam = Team::membersTeam();
    expect($membersTeam)->not->toBeNull();
    $otherTeam = Team::factory()->create(['personal_team' => false, 'name' => 'AG Legacy']);
    $user = User::factory()->create();
    $user->teams()->attach($membersTeam, ['role' => Role::Mitglied->value]);
    $user->teams()->attach($otherTeam, ['role' => Role::Mitglied->value]);
    $user->switchTeam($membersTeam);
    $reward = Reward::factory()->create(['slug' => 'ambiguous-legacy-repair']);
    $purchase = RewardPurchase::create(['user_id' => $user->id, 'reward_id' => $reward->id, 'wallet_team_id' => null, 'cost_baxx' => 5, 'purchased_at' => now()]);
    $this->artisan('rewards:repair-legacy-wallets', ['--apply' => true])->assertExitCode(0);
    $purchase->refresh();
    expect($purchase->wallet_team_id)->toBeNull();
    $this->artisan('rewards:repair-legacy-wallets', ['--user' => [$user->id], '--apply' => true])->assertExitCode(0);
    $purchase->refresh();
    expect($purchase->wallet_team_id)->toBe($membersTeam->id)
        ->and($purchase->updated_at)->not->toBeNull();
});
