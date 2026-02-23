<?php

namespace Tests\Feature;

use App\Models\Reward;
use App\Models\RewardPurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class BelohnungenIndexTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    public function test_belohnungen_page_loads_for_authenticated_member(): void
    {
        $this->actingMember();

        $this->get('/belohnungen')
            ->assertOk()
            ->assertSee('Belohnungen');
    }

    public function test_belohnungen_redirects_unauthenticated_users(): void
    {
        $this->get('/belohnungen')
            ->assertRedirect('/login');
    }

    public function test_belohnungen_shows_help_text(): void
    {
        $this->actingMember();

        $this->get('/belohnungen')
            ->assertOk()
            ->assertSee('So funktioniert das Belohnungssystem');
    }

    public function test_belohnungen_shows_available_baxx(): void
    {
        $user = $this->actingMemberWithPoints(15);

        $this->get('/belohnungen')
            ->assertOk()
            ->assertSee('15 Baxx');
    }

    public function test_belohnungen_shows_rewards_grouped_by_category(): void
    {
        $this->actingMember();

        // Seeded rewards should be present (from config/rewards.php via RewardSeeder)
        $this->get('/belohnungen')
            ->assertOk()
            ->assertSee('Statistiken');
    }

    public function test_purchase_reward_deducts_baxx(): void
    {
        $user = $this->actingMemberWithPoints(10);
        $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'test-purchase']);

        Livewire::test(\App\Livewire\BelohnungenIndex::class)
            ->call('purchase', $reward->id);

        $this->assertDatabaseHas('reward_purchases', [
            'user_id' => $user->id,
            'reward_id' => $reward->id,
            'cost_baxx' => 5,
        ]);
    }

    public function test_purchase_with_insufficient_baxx_fails(): void
    {
        $this->actingMemberWithPoints(2);
        $reward = Reward::factory()->create(['cost_baxx' => 10, 'slug' => 'expensive']);

        Livewire::test(\App\Livewire\BelohnungenIndex::class)
            ->call('purchase', $reward->id);

        $this->assertDatabaseMissing('reward_purchases', [
            'reward_id' => $reward->id,
        ]);
    }

    public function test_purchase_already_owned_reward_fails(): void
    {
        $user = $this->actingMemberWithPoints(20);
        $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'owned']);

        RewardPurchase::factory()->create([
            'user_id' => $user->id,
            'reward_id' => $reward->id,
            'cost_baxx' => 5,
        ]);

        Livewire::test(\App\Livewire\BelohnungenIndex::class)
            ->call('purchase', $reward->id);

        // Should still only have 1 purchase
        $this->assertEquals(1, RewardPurchase::where('user_id', $user->id)
            ->where('reward_id', $reward->id)
            ->active()
            ->count());
    }

    public function test_purchase_inactive_reward_fails(): void
    {
        $this->actingMemberWithPoints(20);
        $reward = Reward::factory()->inactive()->create(['cost_baxx' => 5, 'slug' => 'inactive']);

        Livewire::test(\App\Livewire\BelohnungenIndex::class)
            ->call('purchase', $reward->id);

        $this->assertDatabaseMissing('reward_purchases', [
            'reward_id' => $reward->id,
        ]);
    }

    public function test_purchased_rewards_shown_as_unlocked(): void
    {
        $user = $this->actingMemberWithPoints(10);
        $reward = Reward::factory()->create([
            'cost_baxx' => 5,
            'slug' => 'unlocked-test',
            'title' => 'Freigeschaltetes Feature',
        ]);

        RewardPurchase::factory()->create([
            'user_id' => $user->id,
            'reward_id' => $reward->id,
            'cost_baxx' => 5,
        ]);

        $this->get('/belohnungen')
            ->assertOk()
            ->assertSee('Freigeschaltetes Feature')
            ->assertSee('Freigeschaltet');
    }
}
