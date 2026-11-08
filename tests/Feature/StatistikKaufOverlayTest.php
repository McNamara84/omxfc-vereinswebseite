<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\StatistikKaufOverlay;
use App\Models\Reward;
use App\Models\RewardPurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('successful purchase sets purchased and creates record', function (): void {
    $user = $this->actingMemberWithPoints(10);
    $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'statistik-test']);
    Livewire::test(StatistikKaufOverlay::class, ['rewardId' => $reward->id, 'sectionId' => 'test-section'])->call('purchase')->assertSet('purchased', true)->assertSet('errorMessage', '');
    $this->assertDatabaseHas('reward_purchases', ['user_id' => $user->id, 'reward_id' => $reward->id, 'cost_baxx' => 5]);
});

test('insufficient baxx sets error message', function (): void {
    $this->actingMemberWithPoints(2);
    $reward = Reward::factory()->create(['cost_baxx' => 10, 'slug' => 'statistik-expensive']);
    Livewire::test(StatistikKaufOverlay::class, ['rewardId' => $reward->id, 'sectionId' => 'test-section'])->call('purchase')->assertSet('purchased', false)->assertNotSet('errorMessage', '');
    $this->assertDatabaseMissing('reward_purchases', ['reward_id' => $reward->id]);
});

test('already purchased sets error message', function (): void {
    $user = $this->actingMemberWithPoints(20);
    $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'statistik-owned']);
    RewardPurchase::factory()->create(['user_id' => $user->id, 'reward_id' => $reward->id, 'cost_baxx' => 5]);
    Livewire::test(StatistikKaufOverlay::class, ['rewardId' => $reward->id, 'sectionId' => 'test-section'])->call('purchase')->assertSet('purchased', false)->assertNotSet('errorMessage', '');
    expect(RewardPurchase::where('user_id', $user->id)->where('reward_id', $reward->id)->active()->count())->toEqual(1);
});

test('inactive reward sets error message', function (): void {
    $this->actingMemberWithPoints(20);
    $reward = Reward::factory()->inactive()->create(['cost_baxx' => 5, 'slug' => 'statistik-inactive']);
    Livewire::test(StatistikKaufOverlay::class, ['rewardId' => $reward->id, 'sectionId' => 'test-section'])->call('purchase')->assertSet('purchased', false)->assertNotSet('errorMessage', '');
    $this->assertDatabaseMissing('reward_purchases', ['reward_id' => $reward->id]);
});
