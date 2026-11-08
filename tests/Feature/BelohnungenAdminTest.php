<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\BelohnungenAdmin;
use App\Models\BaxxEarningRule;
use App\Models\Download;
use App\Models\MaddraxikonRewardEvent;
use App\Models\MaddraxiversumBaxxSpecialOffer;
use App\Models\ReviewBaxxSpecialOffer;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\RomantauschBaxxSpecialOffer;
use App\Services\RewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mary\View\Components\Badge;
use RuntimeException;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('app.testing_minimal_layout', true);
    Config::set('app.testing_minimal_belohnungen_admin', true);
});

test('admin page loads for admin', function (): void {
    $this->actingAdmin();
    $this->get('/belohnungen/admin')->assertOk()->assertSee('Belohnungen - Admin');
});

test('admin page links to maddraxikon dashboard', function (): void {
    $this->actingAdmin();
    Config::set('app.testing_minimal_belohnungen_admin', false);
    $this->get(route('rewards.admin'))->assertOk()->assertSee('Maddraxikon-Baxx')->assertSee(route('rewards.admin.maddraxikon'), escape: false);
});

test('tab badges summarize admin sections', function (): void {
    $this->actingAdmin();
    Reward::factory()->count(2)->create();
    $download = Download::factory()->create();
    $rewardWithDownload = Reward::factory()->create(['download_id' => $download->id]);
    RewardPurchase::factory()->count(2)->create(['reward_id' => $rewardWithDownload->id]);
    ReviewBaxxSpecialOffer::create(['points' => 2, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => true]);
    MaddraxiversumBaxxSpecialOffer::create(['points' => 3, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => true]);
    RomantauschBaxxSpecialOffer::create(['action_key' => 'romantausch_offer', 'points' => 4, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => true]);
    $component = Livewire::test(BelohnungenAdmin::class);
    $expectedRulesBadge = BaxxEarningRule::count() + ReviewBaxxSpecialOffer::count() + MaddraxiversumBaxxSpecialOffer::count() + RomantauschBaxxSpecialOffer::count();
    expect($component->instance()->tabBadges())->toBe(['rewards' => Reward::count(), 'rules' => $expectedRulesBadge, 'purchases' => RewardPurchase::count(), 'statistics' => Reward::count(), 'downloads' => Download::count()]);
});

test('purchase tab badge counts all filtered purchases beyond table limit', function (): void {
    $this->actingAdmin();
    $reward = Reward::factory()->create(['slug' => 'badge-counted-reward']);
    $otherReward = Reward::factory()->create(['slug' => 'badge-filtered-out-reward']);
    RewardPurchase::factory()->count(101)->create(['reward_id' => $reward->id]);
    RewardPurchase::factory()->count(3)->create(['reward_id' => $otherReward->id]);
    $component = Livewire::test(BelohnungenAdmin::class)->set('purchaseRewardFilter', (string) $reward->id);
    expect($component->instance()->purchases()->count())->toBe(100)
        ->and($component->instance()->tabBadges()['purchases'])->toBe(101);
});

test('tab badges do not load admin statistics', function (): void {
    $this->actingAdmin();
    app()->instance(RewardService::class, new class
    {
        public function getAdminStatistics(): array
        {
            throw new RuntimeException('tabBadges() should use direct count queries.');
        }
    });
    Reward::factory()->count(2)->create();
    $component = app(BelohnungenAdmin::class);
    expect($component->tabBadges()['statistics'])->toBe(Reward::count());
});

test('mary tab badges render with upstream alias', function (): void {
    $this->actingAdmin();
    Config::set('app.testing_minimal_belohnungen_admin', false);
    expect(app('blade.compiler')->getClassComponentAliases()['mary-badge'] ?? null)->toBe(Badge::class);
    $component = Livewire::test(BelohnungenAdmin::class);
    $html = $component->html();
    $badge = (string) $component->instance()->tabBadges()['rewards'];
    expect($html)->toContain('Belohnungen')
        ->toContain('badge-primary')
        ->toContain('badge-sm')
        ->toMatch('/badge-primary[^>]*>.*'.preg_quote($badge, '/').'.*<\/div>/s');
});

test('admin page forbidden for regular member', function (): void {
    $this->actingMember();
    $this->get('/belohnungen/admin')->assertForbidden();
});

test('admin page redirects unauthenticated', function (): void {
    $this->get('/belohnungen/admin')->assertRedirect('/login');
});

test('create reward', function (): void {
    $this->actingAdmin();
    Livewire::test(BelohnungenAdmin::class)->set('rewardTitle', 'Neues Feature')->set('rewardDescription', 'Beschreibung des Features')->set('rewardCategory', 'Sonstiges')->set('rewardCostBaxx', 10)->set('rewardIsActive', true)->set('rewardSortOrder', 99)->call('saveReward');
    $this->assertDatabaseHas('rewards', ['title' => 'Neues Feature', 'category' => 'Sonstiges', 'cost_baxx' => 10, 'slug' => 'neues-feature']);
});

test('update reward', function (): void {
    $this->actingAdmin();
    $reward = Reward::factory()->create(['title' => 'Alt', 'cost_baxx' => 5, 'slug' => 'alt']);
    Livewire::test(BelohnungenAdmin::class)->call('openEditReward', $reward->id)->set('rewardTitle', 'Aktualisiert')->set('rewardCostBaxx', 15)->call('saveReward');
    $this->assertDatabaseHas('rewards', ['id' => $reward->id, 'title' => 'Aktualisiert', 'cost_baxx' => 15]);
});

test('direct member map admin entry restores missing reward and updates price', function (): void {
    $this->actingAdmin();
    Reward::query()->where('slug', 'mitgliederkarte')->delete();
    Livewire::test(BelohnungenAdmin::class)->call('openEditMitgliederkarteReward')->assertSet('rewardTitle', 'Mitgliederkarte')->assertSet('rewardCostBaxx', 1)->set('rewardCostBaxx', 7)->call('saveReward');
    $reward = Reward::query()->where('slug', 'mitgliederkarte')->first();
    expect($reward)->not->toBeNull()
        ->and($reward->title)->toBe('Mitgliederkarte')
        ->and($reward->cost_baxx)->toBe(7);
});

test('toggle reward active status', function (): void {
    $this->actingAdmin();
    $reward = Reward::factory()->create(['is_active' => true, 'slug' => 'toggle-test']);
    Livewire::test(BelohnungenAdmin::class)->call('toggleRewardActive', $reward->id);
    $this->assertDatabaseHas('rewards', ['id' => $reward->id, 'is_active' => false]);
});

test('update earning rule', function (): void {
    $this->actingAdmin();
    $rule = BaxxEarningRule::create(['action_key' => 'test_rule', 'label' => 'Test Regel', 'points' => 3, 'is_active' => true]);
    Livewire::test(BelohnungenAdmin::class)->call('openEditRule', $rule->id)->set('ruleLabel', 'Geänderter Name')->set('rulePoints', 7)->set('ruleEveryCount', 3)->call('saveRule');
    $this->assertDatabaseHas('baxx_earning_rules', ['id' => $rule->id, 'label' => 'Geänderter Name', 'points' => 7, 'every_count' => 3]);
});

test('maddraxikon legacy rules cannot be changed in generic editor', function (): void {
    $this->actingAdmin();
    $rule = BaxxEarningRule::query()->where('action_key', MaddraxikonRewardEvent::ACTION_EDIT_SESSION)->firstOrFail();
    $originalPoints = $rule->points;
    $originalEveryCount = $rule->every_count;
    Livewire::test(BelohnungenAdmin::class)->call('openEditRule', $rule->id)->assertNotFound();
    expect($rule->fresh()->points)->toBe($originalPoints)
        ->and($rule->fresh()->every_count)->toBe($originalEveryCount)
        ->and($originalPoints)->toBe(1)
        ->and($originalEveryCount)->toBe(5);
});

test('create review special offer', function (): void {
    $this->actingAdmin();
    Livewire::test(BelohnungenAdmin::class)->set('reviewSpecialOfferPoints', 2)->set('reviewSpecialOfferEveryCount', 1)->set('reviewSpecialOfferEndsAt', now()->addDay()->format('Y-m-d\TH:i'))->set('reviewSpecialOfferIsActive', true)->call('saveReviewSpecialOffer');
    $this->assertDatabaseHas('review_baxx_special_offers', ['points' => 2, 'every_count' => 1, 'is_active' => true]);
});

test('second active review special offer is rejected', function (): void {
    $this->actingAdmin();
    ReviewBaxxSpecialOffer::create(['points' => 2, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => true]);
    Livewire::test(BelohnungenAdmin::class)->set('reviewSpecialOfferPoints', 3)->set('reviewSpecialOfferEveryCount', 1)->set('reviewSpecialOfferEndsAt', now()->addDays(2)->format('Y-m-d\TH:i'))->set('reviewSpecialOfferIsActive', true)->call('saveReviewSpecialOffer')->assertHasErrors(['reviewSpecialOfferIsActive']);
    expect(ReviewBaxxSpecialOffer::count())->toBe(1);
});

test('create maddraxiversum special offer', function (): void {
    $this->actingAdmin();
    Livewire::test(BelohnungenAdmin::class)->set('maddraxiversumSpecialOfferPoints', 3)->set('maddraxiversumSpecialOfferEveryCount', 1)->set('maddraxiversumSpecialOfferEndsAt', now()->addDay()->format('Y-m-d\TH:i'))->set('maddraxiversumSpecialOfferIsActive', true)->call('saveMaddraxiversumSpecialOffer');
    $this->assertDatabaseHas('maddraxiversum_baxx_special_offers', ['points' => 3, 'every_count' => 1, 'is_active' => true]);
});

test('second active maddraxiversum special offer is rejected', function (): void {
    $this->actingAdmin();
    MaddraxiversumBaxxSpecialOffer::create(['points' => 2, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => true]);
    Livewire::test(BelohnungenAdmin::class)->set('maddraxiversumSpecialOfferPoints', 4)->set('maddraxiversumSpecialOfferEveryCount', 1)->set('maddraxiversumSpecialOfferEndsAt', now()->addDays(2)->format('Y-m-d\TH:i'))->set('maddraxiversumSpecialOfferIsActive', true)->call('saveMaddraxiversumSpecialOffer')->assertHasErrors(['maddraxiversumSpecialOfferIsActive']);
    expect(MaddraxiversumBaxxSpecialOffer::count())->toBe(1);
});

test('toggle maddraxiversum special offer active status', function (): void {
    $this->actingAdmin();
    $offer = MaddraxiversumBaxxSpecialOffer::create(['points' => 5, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => false]);
    Livewire::test(BelohnungenAdmin::class)->call('toggleMaddraxiversumSpecialOfferActive', $offer->id);
    $this->assertDatabaseHas('maddraxiversum_baxx_special_offers', ['id' => $offer->id, 'is_active' => true]);
});

test('rules tab shows maddraxiversum configuration', function (): void {
    $this->actingAdmin();
    MaddraxiversumBaxxSpecialOffer::create(['points' => 2, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => true]);
    Livewire::test(BelohnungenAdmin::class)->set('activeTab', 'rules')->assertSee('Maddraxiversum')->assertSee('2 Baxx pro Mission');
});

test('create romantausch special offer', function (): void {
    $this->actingAdmin();
    Livewire::test(BelohnungenAdmin::class)->set('romantauschSpecialOfferActionKey', 'romantausch_request')->set('romantauschSpecialOfferPoints', 4)->set('romantauschSpecialOfferEveryCount', 1)->set('romantauschSpecialOfferEndsAt', now()->addDay()->format('Y-m-d\TH:i'))->set('romantauschSpecialOfferIsActive', true)->call('saveRomantauschSpecialOffer');
    $this->assertDatabaseHas('romantausch_baxx_special_offers', ['action_key' => 'romantausch_request', 'points' => 4, 'every_count' => 1, 'is_active' => true]);
});

test('second active romantausch special offer is rejected for same action', function (): void {
    $this->actingAdmin();
    RomantauschBaxxSpecialOffer::create(['action_key' => 'romantausch_offer', 'points' => 2, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => true]);
    Livewire::test(BelohnungenAdmin::class)->set('romantauschSpecialOfferActionKey', 'romantausch_offer')->set('romantauschSpecialOfferPoints', 5)->set('romantauschSpecialOfferEveryCount', 1)->set('romantauschSpecialOfferEndsAt', now()->addDays(2)->format('Y-m-d\TH:i'))->set('romantauschSpecialOfferIsActive', true)->call('saveRomantauschSpecialOffer')->assertHasErrors(['romantauschSpecialOfferIsActive']);
    expect(RomantauschBaxxSpecialOffer::count())->toBe(1);
});

test('active romantausch special offers are allowed for different actions', function (): void {
    $this->actingAdmin();
    RomantauschBaxxSpecialOffer::create(['action_key' => 'romantausch_offer', 'points' => 2, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => true]);
    Livewire::test(BelohnungenAdmin::class)->set('romantauschSpecialOfferActionKey', 'romantausch_request')->set('romantauschSpecialOfferPoints', 3)->set('romantauschSpecialOfferEveryCount', 1)->set('romantauschSpecialOfferEndsAt', now()->addDays(2)->format('Y-m-d\TH:i'))->set('romantauschSpecialOfferIsActive', true)->call('saveRomantauschSpecialOffer');
    $this->assertDatabaseCount('romantausch_baxx_special_offers', 2);
    $this->assertDatabaseHas('romantausch_baxx_special_offers', ['action_key' => 'romantausch_request', 'points' => 3, 'is_active' => true]);
});

test('toggle romantausch special offer active status', function (): void {
    $this->actingAdmin();
    $offer = RomantauschBaxxSpecialOffer::create(['action_key' => 'romantausch_swap_complete', 'points' => 5, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => false]);
    Livewire::test(BelohnungenAdmin::class)->call('toggleRomantauschSpecialOfferActive', $offer->id);
    $this->assertDatabaseHas('romantausch_baxx_special_offers', ['id' => $offer->id, 'is_active' => true]);
});

test('expired active romantausch special offer uses deactivate toggle ui and can be deactivated', function (): void {
    $this->actingAdmin();
    $offer = RomantauschBaxxSpecialOffer::create(['action_key' => 'romantausch_swap_complete', 'points' => 5, 'every_count' => 1, 'ends_at' => now()->subDay(), 'is_active' => true]);
    $component = app(BelohnungenAdmin::class);
    expect($component->specialOfferToggleAction($offer->is_active, $offer->ends_at))->toBe(['icon' => 'o-eye-slash', 'tooltip' => 'Deaktivieren', 'disabled' => false]);
    Livewire::test(BelohnungenAdmin::class)->call('toggleRomantauschSpecialOfferActive', $offer->id);
    $this->assertDatabaseHas('romantausch_baxx_special_offers', ['id' => $offer->id, 'is_active' => false]);
});

test('expired inactive special offer uses disabled toggle ui', function (): void {
    $this->actingAdmin();
    $offer = RomantauschBaxxSpecialOffer::create(['action_key' => 'romantausch_swap_complete', 'points' => 5, 'every_count' => 1, 'ends_at' => now()->subDay(), 'is_active' => false]);
    $component = app(BelohnungenAdmin::class);
    expect($component->specialOfferToggleAction($offer->is_active, $offer->ends_at))->toBe(['icon' => 'o-clock', 'tooltip' => 'Abgelaufen und nicht erneut aktivierbar', 'disabled' => true]);
});

test('refund purchase', function (): void {
    $admin = $this->actingAdmin();
    $member = $this->createUserWithRole(Role::Mitglied);
    $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'refund-test']);
    $purchase = RewardPurchase::factory()->create(['user_id' => $member->id, 'reward_id' => $reward->id, 'cost_baxx' => 5]);
    Livewire::test(BelohnungenAdmin::class)->call('refundPurchase', $purchase->id);
    $purchase->refresh();
    expect($purchase->refunded_at)->not->toBeNull()
        ->and($purchase->refunded_by)->toEqual($admin->id);
});

test('refund already refunded purchase fails', function (): void {
    $admin = $this->actingAdmin();
    $member = $this->createUserWithRole(Role::Mitglied);
    $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'already-refunded']);
    $purchase = RewardPurchase::factory()->refunded()->create(['user_id' => $member->id, 'reward_id' => $reward->id, 'cost_baxx' => 5, 'refunded_by' => $admin->id]);
    $originalRefundedAt = $purchase->refunded_at;
    Livewire::test(BelohnungenAdmin::class)->call('refundPurchase', $purchase->id)->assertDispatched('toast', type: 'error', title: 'Fehler');
    // refunded_at darf sich nicht geändert haben
    $purchase->refresh();
    expect($purchase->refunded_by)->toEqual($admin->id)
        ->and($purchase->refunded_at->toDateTimeString())->toEqual($originalRefundedAt->toDateTimeString());
});

test('statistics tab shows data', function (): void {
    $admin = $this->actingAdmin();
    $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'stat-test']);
    RewardPurchase::factory()->count(3)->create(['reward_id' => $reward->id, 'cost_baxx' => 5]);
    Livewire::test(BelohnungenAdmin::class)->set('activeTab', 'statistics')->assertSee('15');
    // 3 × 5 Baxx total spent
});

test('create download with file upload', function (): void {
    Storage::fake('private');
    $this->actingAdmin();
    $file = UploadedFile::fake()->create('anleitung.pdf', 1024, 'application/pdf');
    Livewire::test(BelohnungenAdmin::class)->set('downloadTitle', 'Test Anleitung')->set('downloadDescription', 'Eine Testbeschreibung')->set('downloadCategory', 'Klemmbaustein-Anleitungen')->set('downloadSortOrder', 5)->set('downloadIsActive', true)->set('downloadFile', $file)->call('saveDownload');
    $this->assertDatabaseHas('downloads', ['title' => 'Test Anleitung', 'category' => 'Klemmbaustein-Anleitungen', 'sort_order' => 5]);
    $download = Download::where('title', 'Test Anleitung')->first();
    Storage::disk('private')->assertExists($download->file_path);
});

test('create download fails without file', function (): void {
    $this->actingAdmin();
    Livewire::test(BelohnungenAdmin::class)->set('downloadTitle', 'Ohne Datei')->set('downloadCategory', 'Test')->call('saveDownload')->assertHasErrors('downloadFile');
});

test('update download without replacing file', function (): void {
    $this->actingAdmin();
    $download = Download::factory()->create(['title' => 'Alt', 'category' => 'Alt']);
    Livewire::test(BelohnungenAdmin::class)->call('openEditDownload', $download->id)->set('downloadTitle', 'Neu')->set('downloadCategory', 'Neu')->call('saveDownload');
    $this->assertDatabaseHas('downloads', ['id' => $download->id, 'title' => 'Neu', 'category' => 'Neu']);
});

test('toggle download active', function (): void {
    $this->actingAdmin();
    $download = Download::factory()->create(['is_active' => true]);
    Livewire::test(BelohnungenAdmin::class)->call('toggleDownloadActive', $download->id);
    $this->assertDatabaseHas('downloads', ['id' => $download->id, 'is_active' => false]);
});

test('delete download succeeds without active purchases', function (): void {
    Storage::fake('private');
    $this->actingAdmin();
    $download = Download::factory()->create(['file_path' => 'downloads/to-delete.pdf']);
    Storage::disk('private')->put('downloads/to-delete.pdf', 'dummy');
    Livewire::test(BelohnungenAdmin::class)->call('deleteDownload', $download->id);
    $this->assertDatabaseMissing('downloads', ['id' => $download->id]);
    Storage::disk('private')->assertMissing('downloads/to-delete.pdf');
});

test('delete download fails with active reward', function (): void {
    $this->actingAdmin();
    $download = Download::factory()->create();
    Reward::factory()->create(['download_id' => $download->id, 'is_active' => true]);
    Livewire::test(BelohnungenAdmin::class)->call('deleteDownload', $download->id)->assertDispatched('toast', type: 'error');
    $this->assertDatabaseHas('downloads', ['id' => $download->id]);
});

test('delete download fails with active purchases', function (): void {
    $this->actingAdmin();
    $download = Download::factory()->create();
    $reward = Reward::factory()->create(['download_id' => $download->id]);
    $user = $this->createUserWithRole(Role::Mitglied);
    RewardPurchase::factory()->create(['user_id' => $user->id, 'reward_id' => $reward->id]);
    Livewire::test(BelohnungenAdmin::class)->call('deleteDownload', $download->id)->assertDispatched('toast', type: 'error');
    $this->assertDatabaseHas('downloads', ['id' => $download->id]);
});

test('create reward with download link', function (): void {
    $this->actingAdmin();
    $download = Download::factory()->create();
    Livewire::test(BelohnungenAdmin::class)->set('rewardTitle', 'Download-Belohnung')->set('rewardDescription', 'Ein Download')->set('rewardCategory', 'Downloads')->set('rewardCostBaxx', 5)->set('rewardDownloadId', $download->id)->call('saveReward');
    $this->assertDatabaseHas('rewards', ['title' => 'Download-Belohnung', 'download_id' => $download->id]);
});

test('create reward with invalid download id fails', function (): void {
    $this->actingAdmin();
    Livewire::test(BelohnungenAdmin::class)->set('rewardTitle', 'Ungültig')->set('rewardDescription', 'Test')->set('rewardCategory', 'Test')->set('rewardCostBaxx', 5)->set('rewardDownloadId', 99999)->call('saveReward')->assertHasErrors('rewardDownloadId');
});

test('create reward with already linked download fails', function (): void {
    $this->actingAdmin();
    $download = Download::factory()->create();
    Reward::factory()->create(['download_id' => $download->id]);
    Livewire::test(BelohnungenAdmin::class)->set('rewardTitle', 'Duplikat')->set('rewardDescription', 'Test')->set('rewardCategory', 'Test')->set('rewardCostBaxx', 5)->set('rewardDownloadId', $download->id)->call('saveReward')->assertHasErrors('rewardDownloadId');
});

test('create download sanitizes original filename', function (): void {
    Storage::fake('private');
    $this->actingAdmin();
    // Simuliere einen Upload mit problematischem Dateinamen
    $file = UploadedFile::fake()->create("test\x00file\x1f.pdf", 512, 'application/pdf');
    Livewire::test(BelohnungenAdmin::class)->set('downloadTitle', 'Sanitize Test')->set('downloadCategory', 'Test')->set('downloadFile', $file)->call('saveDownload');
    $download = Download::where('title', 'Sanitize Test')->first();
    expect($download)->not->toBeNull();
    // Steuerzeichen müssen entfernt sein
    expect($download->original_filename)->not->toContain("\x00");
    expect($download->original_filename)->not->toContain("\x1f");
});

test('downloads tab shows downloads', function (): void {
    $this->actingAdmin();
    Download::factory()->create(['title' => 'Mein Download']);
    Livewire::test(BelohnungenAdmin::class)->set('activeTab', 'downloads')->assertSee('Mein Download');
});

test('purchase and download tables keep independent sort state', function (): void {
    $this->actingAdmin();
    $reward = Reward::factory()->create();
    $older = RewardPurchase::factory()->create(['reward_id' => $reward->id, 'cost_baxx' => 9, 'purchased_at' => now()->subDay()]);
    $newer = RewardPurchase::factory()->create(['reward_id' => $reward->id, 'cost_baxx' => 2, 'purchased_at' => now()]);
    $alpha = Download::factory()->create(['title' => 'Alpha', 'category' => 'Zeta']);
    $zulu = Download::factory()->create(['title' => 'Zulu', 'category' => 'Alpha']);
    $component = Livewire::test(BelohnungenAdmin::class)->set('purchaseSortBy', ['column' => 'cost_baxx', 'direction' => 'asc'])->set('downloadSortBy', ['column' => 'title', 'direction' => 'desc']);
    expect($component->instance()->purchases()->pluck('id')->all())->toBe([$newer->id, $older->id])
        ->and($component->instance()->downloads()->whereIn('id', [$alpha->id, $zulu->id])->pluck('id')->all())->toBe([$zulu->id, $alpha->id]);
    $component->assertSet('purchaseSortBy.column', 'cost_baxx')->assertSet('downloadSortBy.column', 'title');
});

test('table sorting falls back for untrusted columns and directions', function (): void {
    $this->actingAdmin();
    $reward = Reward::factory()->create();
    $older = RewardPurchase::factory()->create(['reward_id' => $reward->id, 'purchased_at' => now()->subDay()]);
    $newer = RewardPurchase::factory()->create(['reward_id' => $reward->id, 'purchased_at' => now()]);
    $component = Livewire::test(BelohnungenAdmin::class)->set('purchaseSortBy', ['column' => 'purchased_at desc; drop table users', 'direction' => 'sideways']);
    expect($component->instance()->purchases()->pluck('id')->all())->toBe([$newer->id, $older->id])
        ->and(RewardPurchase::query()->whereKey($older->id)->exists())->toBeTrue();
});
