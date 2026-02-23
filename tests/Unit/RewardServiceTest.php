<?php

namespace Tests\Unit;

use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\User;
use App\Services\RewardService;
use App\Services\TeamPointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class RewardServiceTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private RewardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RewardService::class);
    }

    public function test_purchase_reward_succeeds_with_enough_baxx(): void
    {
        $user = $this->actingMemberWithPoints(10);
        $reward = Reward::factory()->create(['cost_baxx' => 5]);

        $purchase = $this->service->purchaseReward($user, $reward);

        $this->assertNotNull($purchase);
        $this->assertEquals($user->id, $purchase->user_id);
        $this->assertEquals($reward->id, $purchase->reward_id);
        $this->assertEquals(5, $purchase->cost_baxx);
        $this->assertNotNull($purchase->purchased_at);
        $this->assertNull($purchase->refunded_at);
    }

    public function test_purchase_reward_fails_with_insufficient_baxx(): void
    {
        $user = $this->actingMemberWithPoints(3);
        $reward = Reward::factory()->create(['cost_baxx' => 10]);

        $this->expectException(ValidationException::class);
        $this->service->purchaseReward($user, $reward);
    }

    public function test_purchase_reward_fails_when_already_purchased(): void
    {
        $user = $this->actingMemberWithPoints(20);
        $reward = Reward::factory()->create(['cost_baxx' => 5]);

        $this->service->purchaseReward($user, $reward);

        $this->expectException(ValidationException::class);
        $this->service->purchaseReward($user, $reward);
    }

    public function test_purchase_reward_fails_when_reward_inactive(): void
    {
        $user = $this->actingMemberWithPoints(20);
        $reward = Reward::factory()->inactive()->create(['cost_baxx' => 5]);

        $this->expectException(ValidationException::class);
        $this->service->purchaseReward($user, $reward);
    }

    public function test_purchase_after_refund_allows_repurchase(): void
    {
        $admin = $this->createUserWithRole(\App\Enums\Role::Admin);
        $user = $this->actingMemberWithPoints(20);
        $reward = Reward::factory()->create(['cost_baxx' => 5]);

        $purchase = $this->service->purchaseReward($user, $reward);
        $this->service->refundPurchase($purchase, $admin);

        // Should be able to repurchase after refund
        $newPurchase = $this->service->purchaseReward($user, $reward);
        $this->assertNotNull($newPurchase);
    }

    public function test_refund_purchase_sets_refunded_fields(): void
    {
        $admin = $this->createUserWithRole(\App\Enums\Role::Admin);
        $user = $this->actingMemberWithPoints(10);
        $reward = Reward::factory()->create(['cost_baxx' => 5]);

        $purchase = $this->service->purchaseReward($user, $reward);
        $this->service->refundPurchase($purchase, $admin);

        $purchase->refresh();
        $this->assertNotNull($purchase->refunded_at);
        $this->assertEquals($admin->id, $purchase->refunded_by);
    }

    public function test_refund_already_refunded_throws_exception(): void
    {
        $admin = $this->createUserWithRole(\App\Enums\Role::Admin);
        $user = $this->actingMemberWithPoints(10);
        $reward = Reward::factory()->create(['cost_baxx' => 5]);

        $purchase = $this->service->purchaseReward($user, $reward);
        $this->service->refundPurchase($purchase, $admin);

        $this->expectException(ValidationException::class);
        $this->service->refundPurchase($purchase, $admin);
    }

    public function test_get_available_baxx_subtracts_spent(): void
    {
        $user = $this->actingMemberWithPoints(20);
        $reward = Reward::factory()->create(['cost_baxx' => 7]);
        $this->service->purchaseReward($user, $reward);

        $available = $this->service->getAvailableBaxx($user);
        $this->assertEquals(13, $available);
    }

    public function test_get_available_baxx_refund_restores_balance(): void
    {
        $admin = $this->createUserWithRole(\App\Enums\Role::Admin);
        $user = $this->actingMemberWithPoints(20);
        $reward = Reward::factory()->create(['cost_baxx' => 7]);

        $purchase = $this->service->purchaseReward($user, $reward);
        $this->assertEquals(13, $this->service->getAvailableBaxx($user));

        $this->service->refundPurchase($purchase, $admin);
        $this->assertEquals(20, $this->service->getAvailableBaxx($user));
    }

    public function test_get_spent_baxx_excludes_refunded(): void
    {
        $admin = $this->createUserWithRole(\App\Enums\Role::Admin);
        $user = $this->actingMemberWithPoints(30);
        $reward1 = Reward::factory()->create(['cost_baxx' => 5]);
        $reward2 = Reward::factory()->create(['cost_baxx' => 10]);

        $purchase1 = $this->service->purchaseReward($user, $reward1);
        $this->service->purchaseReward($user, $reward2);

        $this->assertEquals(15, $this->service->getSpentBaxx($user));

        $this->service->refundPurchase($purchase1, $admin);
        $this->assertEquals(10, $this->service->getSpentBaxx($user));
    }

    public function test_has_unlocked_reward_returns_true_for_purchased(): void
    {
        $user = $this->actingMemberWithPoints(10);
        $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'test-feature']);
        $this->service->purchaseReward($user, $reward);

        $this->assertTrue($this->service->hasUnlockedReward($user, 'test-feature'));
    }

    public function test_has_unlocked_reward_returns_false_for_not_purchased(): void
    {
        $user = $this->actingMemberWithPoints(10);
        Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'test-feature']);

        $this->assertFalse($this->service->hasUnlockedReward($user, 'test-feature'));
    }

    public function test_has_unlocked_reward_returns_false_for_refunded(): void
    {
        $admin = $this->createUserWithRole(\App\Enums\Role::Admin);
        $user = $this->actingMemberWithPoints(10);
        $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'test-feature']);

        $purchase = $this->service->purchaseReward($user, $reward);
        $this->service->refundPurchase($purchase, $admin);

        $this->assertFalse($this->service->hasUnlockedReward($user, 'test-feature'));
    }

    public function test_has_unlocked_reward_returns_false_for_unknown_slug(): void
    {
        $user = $this->actingMemberWithPoints(10);
        $this->assertFalse($this->service->hasUnlockedReward($user, 'nonexistent-slug'));
    }

    public function test_get_admin_statistics_returns_correct_totals(): void
    {
        $user1 = $this->actingMemberWithPoints(50);
        $user2 = $this->createUserWithRole(\App\Enums\Role::Mitglied);
        $user2->incrementTeamPoints(30);

        $reward1 = Reward::factory()->create(['cost_baxx' => 5]);
        $reward2 = Reward::factory()->create(['cost_baxx' => 10]);
        $reward3 = Reward::factory()->create(['cost_baxx' => 3]);

        $this->service->purchaseReward($user1, $reward1);
        $this->service->purchaseReward($user1, $reward2);
        $this->service->purchaseReward($user2, $reward1);

        $stats = $this->service->getAdminStatistics();

        $this->assertEquals(20, $stats['total_spent_baxx']); // 5 + 10 + 5
        $this->assertTrue($stats['never_purchased_rewards']->contains('id', $reward3->id));
    }
}
