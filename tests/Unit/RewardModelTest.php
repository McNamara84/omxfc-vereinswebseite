<?php

namespace Tests\Unit;

use App\Models\BaxxEarningRule;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_reward_active_scope_filters_inactive(): void
    {
        Reward::factory()->create(['is_active' => true, 'title' => 'Aktiv']);
        Reward::factory()->create(['is_active' => false, 'title' => 'Inaktiv']);

        $active = Reward::active()->get();

        $this->assertCount(
            Reward::where('is_active', true)->count(),
            $active
        );
        $this->assertTrue($active->contains('title', 'Aktiv'));
    }

    public function test_reward_by_category_scope(): void
    {
        Reward::factory()->create(['category' => 'Statistiken', 'title' => 'Stat-Test']);
        Reward::factory()->create(['category' => 'Downloads', 'title' => 'DL-Test']);

        $stats = Reward::byCategory('Statistiken')->get();

        $this->assertTrue($stats->contains('title', 'Stat-Test'));
        $this->assertFalse($stats->contains('title', 'DL-Test'));
    }

    public function test_reward_has_purchases_relationship(): void
    {
        $reward = Reward::factory()->create();
        $user = User::factory()->create();

        RewardPurchase::factory()->create([
            'user_id' => $user->id,
            'reward_id' => $reward->id,
        ]);

        $this->assertCount(1, $reward->purchases);
        $this->assertCount(1, $reward->activePurchases);
    }

    public function test_active_purchases_excludes_refunded(): void
    {
        $reward = Reward::factory()->create();
        $user = User::factory()->create();

        RewardPurchase::factory()->create([
            'user_id' => $user->id,
            'reward_id' => $reward->id,
            'refunded_at' => now(),
        ]);

        $this->assertCount(1, $reward->purchases);
        $this->assertCount(0, $reward->activePurchases);
    }

    public function test_reward_slug_is_auto_generated(): void
    {
        $reward = Reward::factory()->create([
            'title' => 'Mein tolles Feature',
            'slug' => '',
        ]);

        $this->assertEquals('mein-tolles-feature', $reward->slug);
    }

    public function test_reward_purchase_is_refunded_check(): void
    {
        $purchase = RewardPurchase::factory()->create(['refunded_at' => null]);
        $this->assertFalse($purchase->isRefunded());

        $purchase->update(['refunded_at' => now()]);
        $this->assertTrue($purchase->isRefunded());
    }

    public function test_reward_purchase_active_scope(): void
    {
        RewardPurchase::factory()->create(['refunded_at' => null]);
        RewardPurchase::factory()->create(['refunded_at' => now()]);

        $this->assertCount(1, RewardPurchase::active()->get());
        $this->assertCount(1, RewardPurchase::refunded()->get());
    }

    public function test_baxx_earning_rule_get_points_for_returns_correct_points(): void
    {
        // 'rezension' should be seeded with 1 point
        $points = BaxxEarningRule::getPointsFor('rezension');
        $this->assertEquals(1, $points);
    }

    public function test_baxx_earning_rule_get_points_for_unknown_action_returns_zero(): void
    {
        $points = BaxxEarningRule::getPointsFor('nonexistent_action');
        $this->assertEquals(0, $points);
    }

    public function test_baxx_earning_rule_get_points_for_inactive_rule_returns_zero(): void
    {
        $rule = BaxxEarningRule::where('action_key', 'rezension')->first();
        $rule->update(['is_active' => false]);

        $points = BaxxEarningRule::getPointsFor('rezension');
        $this->assertEquals(0, $points);
    }

    public function test_baxx_earning_rule_active_scope(): void
    {
        $total = BaxxEarningRule::count();
        $active = BaxxEarningRule::active()->count();

        $this->assertEquals($total, $active);

        BaxxEarningRule::first()->update(['is_active' => false]);
        $this->assertEquals($total - 1, BaxxEarningRule::active()->count());
    }
}
