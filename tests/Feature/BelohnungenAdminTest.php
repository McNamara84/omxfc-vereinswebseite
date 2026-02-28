<?php

namespace Tests\Feature;

use App\Livewire\BelohnungenAdmin;
use App\Models\BaxxEarningRule;
use App\Models\Download;
use App\Models\Reward;
use App\Models\RewardPurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        $originalRefundedAt = $purchase->refunded_at;

        Livewire::test(BelohnungenAdmin::class)
            ->call('refundPurchase', $purchase->id)
            ->assertDispatched('toast', type: 'error', title: 'Fehler');

        // refunded_at darf sich nicht geändert haben
        $purchase->refresh();
        $this->assertEquals($admin->id, $purchase->refunded_by);
        $this->assertEquals($originalRefundedAt->toDateTimeString(), $purchase->refunded_at->toDateTimeString());
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

    // ── Download CRUD ──────────────────────────────────────

    public function test_create_download_with_file_upload(): void
    {
        Storage::fake('private');
        $this->actingAdmin();

        $file = UploadedFile::fake()->create('anleitung.pdf', 1024, 'application/pdf');

        Livewire::test(BelohnungenAdmin::class)
            ->set('downloadTitle', 'Test Anleitung')
            ->set('downloadDescription', 'Eine Testbeschreibung')
            ->set('downloadCategory', 'Klemmbaustein-Anleitungen')
            ->set('downloadSortOrder', 5)
            ->set('downloadIsActive', true)
            ->set('downloadFile', $file)
            ->call('saveDownload');

        $this->assertDatabaseHas('downloads', [
            'title' => 'Test Anleitung',
            'category' => 'Klemmbaustein-Anleitungen',
            'sort_order' => 5,
        ]);

        $download = Download::where('title', 'Test Anleitung')->first();
        Storage::disk('private')->assertExists($download->file_path);
    }

    public function test_create_download_fails_without_file(): void
    {
        $this->actingAdmin();

        Livewire::test(BelohnungenAdmin::class)
            ->set('downloadTitle', 'Ohne Datei')
            ->set('downloadCategory', 'Test')
            ->call('saveDownload')
            ->assertHasErrors('downloadFile');
    }

    public function test_update_download_without_replacing_file(): void
    {
        $this->actingAdmin();

        $download = Download::factory()->create([
            'title' => 'Alt',
            'category' => 'Alt',
        ]);

        Livewire::test(BelohnungenAdmin::class)
            ->call('openEditDownload', $download->id)
            ->set('downloadTitle', 'Neu')
            ->set('downloadCategory', 'Neu')
            ->call('saveDownload');

        $this->assertDatabaseHas('downloads', [
            'id' => $download->id,
            'title' => 'Neu',
            'category' => 'Neu',
        ]);
    }

    public function test_toggle_download_active(): void
    {
        $this->actingAdmin();

        $download = Download::factory()->create(['is_active' => true]);

        Livewire::test(BelohnungenAdmin::class)
            ->call('toggleDownloadActive', $download->id);

        $this->assertDatabaseHas('downloads', [
            'id' => $download->id,
            'is_active' => false,
        ]);
    }

    public function test_delete_download_succeeds_without_active_purchases(): void
    {
        Storage::fake('private');
        $this->actingAdmin();

        $download = Download::factory()->create([
            'file_path' => 'downloads/to-delete.pdf',
        ]);
        Storage::disk('private')->put('downloads/to-delete.pdf', 'dummy');

        Livewire::test(BelohnungenAdmin::class)
            ->call('deleteDownload', $download->id);

        $this->assertDatabaseMissing('downloads', ['id' => $download->id]);
        Storage::disk('private')->assertMissing('downloads/to-delete.pdf');
    }

    public function test_delete_download_fails_with_active_reward(): void
    {
        $this->actingAdmin();

        $download = Download::factory()->create();
        Reward::factory()->create([
            'download_id' => $download->id,
            'is_active' => true,
        ]);

        Livewire::test(BelohnungenAdmin::class)
            ->call('deleteDownload', $download->id)
            ->assertDispatched('toast', type: 'error');

        $this->assertDatabaseHas('downloads', ['id' => $download->id]);
    }

    public function test_delete_download_fails_with_active_purchases(): void
    {
        $this->actingAdmin();

        $download = Download::factory()->create();
        $reward = Reward::factory()->create(['download_id' => $download->id]);
        $user = $this->createUserWithRole(\App\Enums\Role::Mitglied);

        RewardPurchase::factory()->create([
            'user_id' => $user->id,
            'reward_id' => $reward->id,
        ]);

        Livewire::test(BelohnungenAdmin::class)
            ->call('deleteDownload', $download->id)
            ->assertDispatched('toast', type: 'error');

        $this->assertDatabaseHas('downloads', ['id' => $download->id]);
    }

    // ── Reward ↔ Download Verknüpfung ──────────────────────

    public function test_create_reward_with_download_link(): void
    {
        $this->actingAdmin();

        $download = Download::factory()->create();

        Livewire::test(BelohnungenAdmin::class)
            ->set('rewardTitle', 'Download-Belohnung')
            ->set('rewardDescription', 'Ein Download')
            ->set('rewardCategory', 'Downloads')
            ->set('rewardCostBaxx', 5)
            ->set('rewardDownloadId', $download->id)
            ->call('saveReward');

        $this->assertDatabaseHas('rewards', [
            'title' => 'Download-Belohnung',
            'download_id' => $download->id,
        ]);
    }

    public function test_create_reward_with_invalid_download_id_fails(): void
    {
        $this->actingAdmin();

        Livewire::test(BelohnungenAdmin::class)
            ->set('rewardTitle', 'Ungültig')
            ->set('rewardDescription', 'Test')
            ->set('rewardCategory', 'Test')
            ->set('rewardCostBaxx', 5)
            ->set('rewardDownloadId', 99999)
            ->call('saveReward')
            ->assertHasErrors('rewardDownloadId');
    }

    public function test_create_reward_with_already_linked_download_fails(): void
    {
        $this->actingAdmin();

        $download = Download::factory()->create();
        Reward::factory()->create(['download_id' => $download->id]);

        Livewire::test(BelohnungenAdmin::class)
            ->set('rewardTitle', 'Duplikat')
            ->set('rewardDescription', 'Test')
            ->set('rewardCategory', 'Test')
            ->set('rewardCostBaxx', 5)
            ->set('rewardDownloadId', $download->id)
            ->call('saveReward')
            ->assertHasErrors('rewardDownloadId');
    }

    public function test_downloads_tab_shows_downloads(): void
    {
        $this->actingAdmin();

        Download::factory()->create(['title' => 'Mein Download']);

        Livewire::test(BelohnungenAdmin::class)
            ->set('activeTab', 'downloads')
            ->assertSee('Mein Download');
    }
}
