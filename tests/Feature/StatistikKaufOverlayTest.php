<?php

namespace Tests\Feature;

use App\Models\Reward;
use App\Models\RewardPurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class StatistikKaufOverlayTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    public function test_successful_purchase_sets_purchased_and_creates_record(): void
    {
        $user = $this->actingMemberWithPoints(10);
        $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'statistik-test']);

        Livewire::test(\App\Livewire\StatistikKaufOverlay::class, [
            'rewardId' => $reward->id,
            'costBaxx' => $reward->cost_baxx,
            'availableBaxx' => 10,
            'sectionId' => 'test-section',
        ])
            ->call('purchase')
            ->assertSet('purchased', true)
            ->assertSet('errorMessage', '');

        $this->assertDatabaseHas('reward_purchases', [
            'user_id' => $user->id,
            'reward_id' => $reward->id,
            'cost_baxx' => 5,
        ]);
    }

    public function test_insufficient_baxx_sets_error_message(): void
    {
        $this->actingMemberWithPoints(2);
        $reward = Reward::factory()->create(['cost_baxx' => 10, 'slug' => 'statistik-expensive']);

        Livewire::test(\App\Livewire\StatistikKaufOverlay::class, [
            'rewardId' => $reward->id,
            'costBaxx' => $reward->cost_baxx,
            'availableBaxx' => 2,
            'sectionId' => 'test-section',
        ])
            ->call('purchase')
            ->assertSet('purchased', false)
            ->assertNotSet('errorMessage', '');

        $this->assertDatabaseMissing('reward_purchases', [
            'reward_id' => $reward->id,
        ]);
    }

    public function test_already_purchased_sets_error_message(): void
    {
        $user = $this->actingMemberWithPoints(20);
        $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'statistik-owned']);

        RewardPurchase::factory()->create([
            'user_id' => $user->id,
            'reward_id' => $reward->id,
            'cost_baxx' => 5,
        ]);

        Livewire::test(\App\Livewire\StatistikKaufOverlay::class, [
            'rewardId' => $reward->id,
            'costBaxx' => $reward->cost_baxx,
            'availableBaxx' => 15,
            'sectionId' => 'test-section',
        ])
            ->call('purchase')
            ->assertSet('purchased', false)
            ->assertNotSet('errorMessage', '');

        $this->assertEquals(1, RewardPurchase::where('user_id', $user->id)
            ->where('reward_id', $reward->id)
            ->active()
            ->count());
    }

    public function test_inactive_reward_sets_error_message(): void
    {
        $this->actingMemberWithPoints(20);
        $reward = Reward::factory()->inactive()->create(['cost_baxx' => 5, 'slug' => 'statistik-inactive']);

        Livewire::test(\App\Livewire\StatistikKaufOverlay::class, [
            'rewardId' => $reward->id,
            'costBaxx' => $reward->cost_baxx,
            'availableBaxx' => 20,
            'sectionId' => 'test-section',
        ])
            ->call('purchase')
            ->assertSet('purchased', false)
            ->assertNotSet('errorMessage', '');

        $this->assertDatabaseMissing('reward_purchases', [
            'reward_id' => $reward->id,
        ]);
    }
}
