<?php

namespace Tests\Feature;

use App\Models\Reward;
use App\Models\RewardPurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

/**
 * Tests für die Belohnungen-Seite (Livewire-basiert, aktives Kaufsystem).
 *
 * Dieser Test ersetzt den alten RewardControllerTest, der auf dem passiven
 * Schwellenwert-System basierte. Das neue System verwendet ein aktives Kaufmodell,
 * bei dem Nutzer Features mit Baxx freischalten.
 */
class RewardControllerTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    public function test_belohnungen_page_loads_for_member(): void
    {
        $this->actingMember();

        $this->get('/belohnungen')
            ->assertOk()
            ->assertSee('Belohnungen');
    }

    public function test_belohnungen_shows_user_baxx_balance(): void
    {
        $this->actingMemberWithPoints(15);

        $this->get('/belohnungen')
            ->assertOk()
            ->assertSee('15');
    }

    public function test_belohnungen_shows_seeded_rewards(): void
    {
        $this->actingMember();

        // RewardSeeder wird über DatabaseSeeder ausgeführt
        $this->get('/belohnungen')
            ->assertOk()
            ->assertSee('Statistiken');
    }

    public function test_purchase_reward_creates_purchase_record(): void
    {
        $user = $this->actingMemberWithPoints(10);
        $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'kauf-test']);

        Livewire::test(\App\Livewire\BelohnungenIndex::class)
            ->call('purchase', $reward->id);

        $this->assertDatabaseHas('reward_purchases', [
            'user_id' => $user->id,
            'reward_id' => $reward->id,
            'cost_baxx' => 5,
        ]);
    }

    public function test_purchase_reward_insufficient_baxx(): void
    {
        $this->actingMemberWithPoints(2);
        $reward = Reward::factory()->create(['cost_baxx' => 10, 'slug' => 'zu-teuer']);

        Livewire::test(\App\Livewire\BelohnungenIndex::class)
            ->call('purchase', $reward->id);

        $this->assertDatabaseMissing('reward_purchases', [
            'reward_id' => $reward->id,
        ]);
    }

    public function test_purchased_reward_shows_as_unlocked(): void
    {
        $user = $this->actingMemberWithPoints(10);
        $reward = Reward::factory()->create([
            'cost_baxx' => 5,
            'slug' => 'freigeschaltet',
            'title' => 'Mein Feature',
        ]);

        RewardPurchase::factory()->create([
            'user_id' => $user->id,
            'reward_id' => $reward->id,
            'cost_baxx' => 5,
        ]);

        $this->get('/belohnungen')
            ->assertOk()
            ->assertSee('Mein Feature')
            ->assertSee('Freigeschaltet');
    }

    public function test_cannot_purchase_same_reward_twice(): void
    {
        $user = $this->actingMemberWithPoints(20);
        $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'doppelt']);

        RewardPurchase::factory()->create([
            'user_id' => $user->id,
            'reward_id' => $reward->id,
            'cost_baxx' => 5,
        ]);

        Livewire::test(\App\Livewire\BelohnungenIndex::class)
            ->call('purchase', $reward->id);

        $this->assertEquals(1, RewardPurchase::where('user_id', $user->id)
            ->where('reward_id', $reward->id)
            ->active()
            ->count());
    }

    public function test_cannot_purchase_inactive_reward(): void
    {
        $this->actingMemberWithPoints(20);
        $reward = Reward::factory()->inactive()->create(['cost_baxx' => 5, 'slug' => 'inaktiv']);

        Livewire::test(\App\Livewire\BelohnungenIndex::class)
            ->call('purchase', $reward->id);

        $this->assertDatabaseMissing('reward_purchases', [
            'reward_id' => $reward->id,
        ]);
    }

    public function test_unauthenticated_redirect(): void
    {
        $this->get('/belohnungen')
            ->assertRedirect('/login');
    }
}
