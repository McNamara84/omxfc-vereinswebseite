<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\BelohnungenIndex;
use App\Models\ReviewBaxxSpecialOffer;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\Team;
use App\Models\UserPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('belohnungen page loads for authenticated member', function (): void {
    $this->actingMember();
    $this->get('/belohnungen')->assertOk()->assertSee('Belohnungen');
});

test('belohnungen redirects unauthenticated users', function (): void {
    $this->get('/belohnungen')->assertRedirect('/login');
});

test('belohnungen shows help text', function (): void {
    $this->actingMember();
    $this->get('/belohnungen')->assertOk()->assertSee('So funktioniert das Belohnungssystem');
});

test('belohnungen shows available baxx', function (): void {
    $user = $this->actingMemberWithPoints(15);
    $this->get('/belohnungen')->assertOk()->assertSee('15 Baxx');
});

test('belohnungen shows rewards grouped by category', function (): void {
    $this->actingMember();
    // Seeded rewards should be present (from config/rewards.php via RewardSeeder)
    $this->get('/belohnungen')->assertOk()->assertSee('Statistiken');
});

test('bildersuche category obeys the same visibility as its reward', function (bool $enabled, Role $role, bool $verified, bool $active, bool $visible): void {
    config(['bildersuche.enabled' => $enabled]);
    $user = $this->createUserWithRole($role);
    $user->forceFill(['email_verified_at' => $verified ? now() : null])->save();
    $this->actingAs($user);
    $reward = Reward::where('slug', 'bildersuche')->firstOrFail();
    $reward->update(['is_active' => $active]);
    $component = Livewire::test(BelohnungenIndex::class);
    expect(in_array('Bildersuche', $component->get('categories'), true))->toBe($visible);
    $rewardIds = collect($component->get('rewards'))->flatten(1)->pluck('id')->all();
    expect(in_array($reward->id, $rewardIds, true))->toBe($visible);
    if ($visible) {
        $component->assertSee('value="Bildersuche"', false)->assertSee('Maddrax-Bildersuche');
    } else {
        $component->assertDontSee('value="Bildersuche"', false)->assertDontSee('Maddrax-Bildersuche');
    }
})->with([[false, Role::Mitglied, true, true, false], [true, Role::Mitglied, true, true, true], [true, Role::Anwaerter, true, true, false], [true, Role::Mitglied, false, true, true], [true, Role::Mitglied, true, false, false]]);

test('category remains available when it contains another visible reward', function (): void {
    config(['bildersuche.enabled' => false]);
    $this->actingMember();
    Reward::factory()->create(['category' => 'Bildersuche', 'title' => 'Weiteres sichtbares Angebot']);
    $component = Livewire::test(BelohnungenIndex::class)->set('categoryFilter', 'Bildersuche')->assertSee('Weiteres sichtbares Angebot')->assertDontSee('Maddrax-Bildersuche');
    expect($component->get('categories'))->toContain('Bildersuche');
});

test('category selection does not remove other visible categories', function (): void {
    config(['bildersuche.enabled' => true]);
    $this->actingMember();
    Reward::factory()->create(['category' => 'Andere Kategorie', 'title' => 'Weiteres Angebot']);
    $component = Livewire::test(BelohnungenIndex::class)->set('categoryFilter', 'Andere Kategorie')->assertSee('Weiteres Angebot')->assertDontSee('Maddrax-Bildersuche');
    expect($component->get('categories'))->toContain('Bildersuche')
        ->and($component->get('categories'))->toContain('Andere Kategorie');
    $component->set('categoryFilter', 'Bildersuche')->assertSee('Maddrax-Bildersuche')->assertDontSee('Weiteres Angebot');
});

test('purchase reward deducts baxx', function (): void {
    $user = $this->actingMemberWithPoints(10);
    $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'test-purchase']);
    Livewire::test(BelohnungenIndex::class)->call('purchase', $reward->id);
    $this->assertDatabaseHas('reward_purchases', ['user_id' => $user->id, 'reward_id' => $reward->id, 'cost_baxx' => 5]);
});

test('belohnungen uses members team wallet when current team differs', function (): void {
    $user = $this->actingMemberWithPoints(15);
    $otherTeam = Team::factory()->create(['personal_team' => false, 'name' => 'Nebenverein']);
    $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
    UserPoint::create(['user_id' => $user->id, 'team_id' => $otherTeam->id, 'todo_id' => null, 'points' => 40]);
    $user->forceFill(['current_team_id' => $otherTeam->id])->save();
    $reward = Reward::factory()->create(['cost_baxx' => 10, 'slug' => 'members-wallet-livewire']);
    Livewire::actingAs($user->fresh())->test(BelohnungenIndex::class)->assertSee('15 Baxx verdient')->assertSee('15 Baxx verfügbar')->call('purchase', $reward->id)->assertSee('5 Baxx verfügbar');
    $this->assertDatabaseHas('reward_purchases', ['user_id' => $user->id, 'reward_id' => $reward->id, 'cost_baxx' => 10]);
});

test('belohnungen shows wallet warning for ambiguous legacy purchase', function (): void {
    $user = $this->actingMemberWithPoints(15);
    $reward = Reward::factory()->create(['cost_baxx' => 10, 'slug' => 'legacy-baxx-warning']);
    RewardPurchase::factory()->create(['user_id' => $user->id, 'reward_id' => $reward->id, 'wallet_team_id' => null, 'cost_baxx' => 5]);
    $this->get('/belohnungen')->assertOk()->assertSee('Guthaben wird geprüft')->assertSee('ältere Käufe ohne eindeutige Wallet-Zuordnung');
});

test('purchase with insufficient baxx fails', function (): void {
    $this->actingMemberWithPoints(2);
    $reward = Reward::factory()->create(['cost_baxx' => 10, 'slug' => 'expensive']);
    Livewire::test(BelohnungenIndex::class)->call('purchase', $reward->id);
    $this->assertDatabaseMissing('reward_purchases', ['reward_id' => $reward->id]);
});

test('purchase already owned reward fails', function (): void {
    $user = $this->actingMemberWithPoints(20);
    $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'owned']);
    $membersTeam = Team::membersTeam();
    expect($membersTeam)->not->toBeNull();
    RewardPurchase::factory()->create(['user_id' => $user->id, 'reward_id' => $reward->id, 'wallet_team_id' => $membersTeam->id, 'cost_baxx' => 5]);
    Livewire::test(BelohnungenIndex::class)->call('purchase', $reward->id);
    // Should still only have 1 purchase
    expect(RewardPurchase::where('user_id', $user->id)->where('reward_id', $reward->id)->active()->count())->toEqual(1);
});

test('purchase inactive reward fails', function (): void {
    $this->actingMemberWithPoints(20);
    $reward = Reward::factory()->inactive()->create(['cost_baxx' => 5, 'slug' => 'inactive']);
    Livewire::test(BelohnungenIndex::class)->call('purchase', $reward->id);
    $this->assertDatabaseMissing('reward_purchases', ['reward_id' => $reward->id]);
});

test('purchased rewards shown as unlocked', function (): void {
    $user = $this->actingMemberWithPoints(10);
    $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'unlocked-test', 'title' => 'Freigeschaltetes Feature']);
    $membersTeam = Team::membersTeam();
    expect($membersTeam)->not->toBeNull();
    RewardPurchase::factory()->create(['user_id' => $user->id, 'reward_id' => $reward->id, 'wallet_team_id' => $membersTeam->id, 'cost_baxx' => 5]);
    $this->get('/belohnungen')->assertOk()->assertSee('Freigeschaltetes Feature')->assertSee('Freigeschaltet');
});

test('legacy kompendium purchase marks current reward as unlocked', function (): void {
    $user = $this->actingMemberWithPoints(120);
    $membersTeam = Team::membersTeam();
    expect($membersTeam)->not->toBeNull();
    Reward::updateOrCreate(['slug' => 'kompendium'], ['title' => 'Maddrax-Kompendium', 'description' => 'Aktueller Zugang zum Maddrax-Kompendium.', 'category' => 'Kompendium', 'cost_baxx' => 100, 'is_active' => true, 'sort_order' => 0]);
    $legacyReward = Reward::updateOrCreate(['slug' => 'kompendium-suche'], ['title' => 'Kompendium-Suche', 'description' => 'Legacy-Zugang zur Kompendium-Suche.', 'category' => 'Kompendium', 'cost_baxx' => 100, 'is_active' => false, 'sort_order' => 1]);
    RewardPurchase::create(['user_id' => $user->id, 'reward_id' => $legacyReward->id, 'wallet_team_id' => $membersTeam->id, 'cost_baxx' => 100, 'purchased_at' => now()]);
    Livewire::actingAs($user)->test(BelohnungenIndex::class)->set('filter', 'freigeschaltet')->assertSee('Maddrax-Kompendium')->assertSee('Freigeschaltet');
});

test('belohnungen shows prominent review special offer', function (): void {
    $this->actingMember();
    ReviewBaxxSpecialOffer::create(['points' => 2, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => true]);
    $this->get('/belohnungen')->assertOk()->assertSee('Sonderaktion')->assertSee('2 Baxx pro Rezension');
});
