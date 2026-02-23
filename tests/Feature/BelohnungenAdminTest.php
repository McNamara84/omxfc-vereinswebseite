<?php

namespace Tests\Feature;

use App\Livewire\BelohnungenAdmin;
use App\Models\BaxxEarningRule;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class BelohnungenAdminTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    // ── Zugriffskontrolle ──────────────────────────────────

    public function test_admin_page_loads_for_admin(): void
    {
        $this->actingAdmin();

        $this->get('/belohnungen/admin')
            ->assertOk()
            ->assertSee('Belohnungen - Admin');
    }

    public function test_admin_page_forbidden_for_regular_member(): void
    {
        $this->actingMember();

        $this->get('/belohnungen/admin')
            ->assertForbidden();
    }

    public function test_admin_page_redirects_unauthenticated(): void
    {
        $this->get('/belohnungen/admin')
            ->assertRedirect('/login');
    }

    // ── Belohnungen CRUD ───────────────────────────────────

    public function test_create_reward(): void
    {
        $this->actingAdmin();

        Livewire::test(BelohnungenAdmin::class)
            ->set('rewardTitle', 'Neues Feature')
            ->set('rewardDescription', 'Beschreibung des Features')
            ->set('rewardCategory', 'Sonstiges')
            ->set('rewardCostBaxx', 10)
            ->set('rewardIsActive', true)
            ->set('rewardSortOrder', 99)
            ->call('saveReward');

        $this->assertDatabaseHas('rewards', [
            'title' => 'Neues Feature',
            'category' => 'Sonstiges',
            'cost_baxx' => 10,
            'slug' => 'neues-feature',
        ]);
    }

    public function test_update_reward(): void
    {
        $this->actingAdmin();
        $reward = Reward::factory()->create([
            'title' => 'Alt',
            'cost_baxx' => 5,
            'slug' => 'alt',
        ]);

        Livewire::test(BelohnungenAdmin::class)
            ->call('openEditReward', $reward->id)
            ->set('rewardTitle', 'Aktualisiert')
            ->set('rewardCostBaxx', 15)
            ->call('saveReward');

        $this->assertDatabaseHas('rewards', [
            'id' => $reward->id,
            'title' => 'Aktualisiert',
            'cost_baxx' => 15,
        ]);
    }

    public function test_toggle_reward_active_status(): void
    {
        $this->actingAdmin();
        $reward = Reward::factory()->create(['is_active' => true, 'slug' => 'toggle-test']);

        Livewire::test(BelohnungenAdmin::class)
            ->call('toggleRewardActive', $reward->id);

        $this->assertDatabaseHas('rewards', [
            'id' => $reward->id,
            'is_active' => false,
        ]);
    }

    // ── Vergaberegeln ──────────────────────────────────────

    public function test_update_earning_rule(): void
    {
        $this->actingAdmin();
        $rule = BaxxEarningRule::create([
            'action_key' => 'test_rule',
            'label' => 'Test Regel',
            'points' => 3,
            'is_active' => true,
        ]);

        Livewire::test(BelohnungenAdmin::class)
            ->call('openEditRule', $rule->id)
            ->set('ruleLabel', 'Geänderter Name')
            ->set('rulePoints', 7)
            ->call('saveRule');

        $this->assertDatabaseHas('baxx_earning_rules', [
            'id' => $rule->id,
            'label' => 'Geänderter Name',
            'points' => 7,
        ]);
    }

    // ── Freischaltungen / Refund ───────────────────────────

    public function test_refund_purchase(): void
    {
        $admin = $this->actingAdmin();
        $member = $this->createUserWithRole(\App\Enums\Role::Mitglied);
        $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'refund-test']);

        $purchase = RewardPurchase::factory()->create([
            'user_id' => $member->id,
            'reward_id' => $reward->id,
            'cost_baxx' => 5,
        ]);

        Livewire::test(BelohnungenAdmin::class)
            ->call('refundPurchase', $purchase->id);

        $purchase->refresh();
        $this->assertNotNull($purchase->refunded_at);
        $this->assertEquals($admin->id, $purchase->refunded_by);
    }

    public function test_refund_already_refunded_purchase_fails(): void
    {
        $admin = $this->actingAdmin();
        $member = $this->createUserWithRole(\App\Enums\Role::Mitglied);
        $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'already-refunded']);

        $purchase = RewardPurchase::factory()->refunded()->create([
            'user_id' => $member->id,
            'reward_id' => $reward->id,
            'cost_baxx' => 5,
            'refunded_by' => $admin->id,
        ]);

        Livewire::test(BelohnungenAdmin::class)
            ->call('refundPurchase', $purchase->id);

        // Refunded_by should still be admin (not changed)
        $purchase->refresh();
        $this->assertEquals($admin->id, $purchase->refunded_by);
    }

    // ── Statistiken ────────────────────────────────────────

    public function test_statistics_tab_shows_data(): void
    {
        $admin = $this->actingAdmin();
        $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'stat-test']);

        RewardPurchase::factory()->count(3)->create([
            'reward_id' => $reward->id,
            'cost_baxx' => 5,
        ]);

        Livewire::test(BelohnungenAdmin::class)
            ->set('activeTab', 'statistics')
            ->assertSee('15'); // 3 × 5 Baxx total spent
    }
}
