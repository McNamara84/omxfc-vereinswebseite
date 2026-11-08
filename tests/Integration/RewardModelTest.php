<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\BaxxEarningRule;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('reward active scope filters inactive', function (): void {
    Reward::factory()->create(['is_active' => true, 'title' => 'Aktiv']);
    Reward::factory()->create(['is_active' => false, 'title' => 'Inaktiv']);
    $active = Reward::active()->get();
    expect($active)->toHaveCount(Reward::where('is_active', true)->count())
        ->and($active->contains('title', 'Aktiv'))->toBeTrue();
});

test('reward by category scope', function (): void {
    Reward::factory()->create(['category' => 'Statistiken', 'title' => 'Stat-Test']);
    Reward::factory()->create(['category' => 'Downloads', 'title' => 'DL-Test']);
    $stats = Reward::byCategory('Statistiken')->get();
    expect($stats->contains('title', 'Stat-Test'))->toBeTrue()
        ->and($stats->contains('title', 'DL-Test'))->toBeFalse();
});

test('reward has purchases relationship', function (): void {
    $reward = Reward::factory()->create();
    $user = User::factory()->create();
    RewardPurchase::factory()->create(['user_id' => $user->id, 'reward_id' => $reward->id]);
    expect($reward->purchases)->toHaveCount(1)
        ->and($reward->activePurchases)->toHaveCount(1);
});

test('active purchases excludes refunded', function (): void {
    $reward = Reward::factory()->create();
    $user = User::factory()->create();
    RewardPurchase::factory()->create(['user_id' => $user->id, 'reward_id' => $reward->id, 'refunded_at' => now()]);
    expect($reward->purchases)->toHaveCount(1)
        ->and($reward->activePurchases)->toHaveCount(0);
});

test('reward slug is auto generated', function (): void {
    $reward = Reward::factory()->create(['title' => 'Mein tolles Feature', 'slug' => '']);
    expect($reward->slug)->toEqual('mein-tolles-feature');
});

test('reward purchase is refunded check', function (): void {
    $purchase = RewardPurchase::factory()->create(['refunded_at' => null]);
    expect($purchase->isRefunded())->toBeFalse();
    $purchase->update(['refunded_at' => now()]);
    expect($purchase->isRefunded())->toBeTrue();
});

test('reward purchase active scope', function (): void {
    RewardPurchase::factory()->create(['refunded_at' => null]);
    RewardPurchase::factory()->create(['refunded_at' => now()]);
    expect(RewardPurchase::active()->get())->toHaveCount(1)
        ->and(RewardPurchase::refunded()->get())->toHaveCount(1);
});

test('baxx earning rule get points for returns correct points', function (): void {
    // 'rezension' should be seeded with 1 point
    $points = BaxxEarningRule::getPointsFor('rezension');
    expect($points)->toEqual(1);
});

test('baxx earning rule get every count for returns seeded interval', function (): void {
    $everyCount = BaxxEarningRule::getEveryCountFor('rezension');
    expect($everyCount)->toEqual(10);
});

test('baxx earning rule get points for unknown action returns zero', function (): void {
    $points = BaxxEarningRule::getPointsFor('nonexistent_action');
    expect($points)->toEqual(0);
});

test('baxx earning rule ignores legacy cached model payloads', function (): void {
    $legacyRule = BaxxEarningRule::query()->where('action_key', 'rezension')->firstOrFail();
    Cache::forever('baxx_earning_rule_model_rezension', $legacyRule);
    expect(BaxxEarningRule::getPointsFor('rezension'))->toEqual(1)
        ->and(BaxxEarningRule::getEveryCountFor('rezension'))->toEqual(10);
});

test('romantausch rules are seeded for offer request and completed swap', function (): void {
    $this->assertDatabaseHas('baxx_earning_rules', ['action_key' => 'romantausch_offer']);
    $this->assertDatabaseHas('baxx_earning_rules', ['action_key' => 'romantausch_request', 'is_active' => false]);
    $this->assertDatabaseHas('baxx_earning_rules', ['action_key' => 'romantausch_swap_complete']);
});

test('baxx earning rule get points for inactive rule returns zero', function (): void {
    $rule = BaxxEarningRule::where('action_key', 'rezension')->first();
    $rule->update(['is_active' => false]);
    $points = BaxxEarningRule::getPointsFor('rezension');
    expect($points)->toEqual(0)
        ->and(BaxxEarningRule::getEveryCountFor('rezension'))->toEqual(1);
});

test('baxx earning rule active scope', function (): void {
    $active = BaxxEarningRule::active()->count();
    expect($active)->toEqual(BaxxEarningRule::where('is_active', true)->count());
    BaxxEarningRule::active()->firstOrFail()->update(['is_active' => false]);
    expect(BaxxEarningRule::active()->count())->toEqual($active - 1);
});
