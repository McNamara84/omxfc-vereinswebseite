<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\BelohnungenAdmin;
use App\Livewire\BelohnungenIndex;
use App\Livewire\Bildersuche;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\Team;
use App\Models\User;
use App\Services\BildersucheAccessService;
use App\Services\RewardService;
use App\Support\Navigation\NavigationBuilder;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(BildersucheAccessTestFixtures::class);
dataset('BildersucheAccessTest.untrustedTeams', static fn (): array => ['unrelated team / search' => [false, false, 'search'], 'unrelated team / rewards' => [false, false, 'rewards'], 'personal team / search' => [true, false, 'search'], 'personal team / rewards' => [true, false, 'rewards'], 'configured personal team / search' => [true, true, 'search'], 'configured personal team / rewards' => [true, true, 'rewards']]);
dataset('BildersucheAccessTest.invalidAgIdentities', static fn (): array => [[null], [''], [0], [-1], ['not-an-id'], ['1abc'], ['1.5'], [999999]]);
beforeEach(function (): void {
    config(['bildersuche.enabled' => true, 'bildersuche.ag_team_id' => null, 'cover-ratings.enabled' => false]);
    Storage::fake('private');
});
test('member without purchase sees only unlock and cannot search or open images', function (): void {
    $this->get(route('bildersuche.index'))->assertRedirect(route('login'));
    $this->actingMember();
    $cover = $this->searchCover(1, ['Aruula']);
    $this->get(route('bildersuche.index'))->assertOk()->assertSee('100 Baxx')->assertDontSee('bildersuche-query', false);
    Livewire::test(Bildersuche::class)->set('query', 'Aruula')->call('performSearch')->assertForbidden();
    Livewire::test(Bildersuche::class)->call('showCover', $cover->id)->assertForbidden();
    $this->get(route('bildersuche.cover', [$cover, 'small']))->assertForbidden();
});
test('ag member can search preview and follow a safe source link without purchase', function (): void {
    $user = $this->actingMember();
    $this->joinAg($user);
    [$cover] = $this->exampleCovers();
    $cover->update(['source_description_url' => 'javascript:alert(1)', 'source_artist' => '<script>alert(2)</script>']);
    Livewire::test(Bildersuche::class)->set('query', 'Aruula Schwert')->call('performSearch')->assertSee('Testroman 1')->assertDontSee('Testroman 2')->call('showCover', $cover->id)->assertSee('F-17 Alpha 2')->assertSee('Dateiseite im Maddraxikon')->assertDontSee('href="javascript:', false)->assertDontSee('<script>alert(2)</script>', false)->call('closePreview')->assertSet('previewId', null);
    $this->assertDatabaseCount('reward_purchases', 0);
});
test('ag access matches the applications email verification requirement', function (bool $requiresVerification, bool $verified, bool $allowed): void {
    $user = $this->actingAdmin();
    $ag = $this->joinAg($user);
    $user->forceFill(['current_team_id' => $ag->id, 'email_verified_at' => $verified ? now() : null])->save();
    if ($requiresVerification) {
        $model = new class extends User implements MustVerifyEmail
        {
            protected $table = 'users';

            public function getForeignKey(): string
            {
                return 'user_id';
            }
        };
        $user = $model->newFromBuilder($user->getAttributes());
    }
    $this->actingAs($user);
    $access = app(BildersucheAccessService::class);
    expect($access->isEligible($user))->toBe($allowed)
        ->and($access->hasAccess($user))->toBe($allowed);
    $response = $this->get(route('bildersuche.index'));
    if (! $allowed) {
        $response->assertRedirect(route('verification.notice'));

        return;
    }
    $response->assertOk()->assertSee('bildersuche-query', false);
    $cover = $this->searchCover(1, ['Aruula', 'Schwert']);
    Storage::disk('private')->put($cover->small_path, 'webp');
    Livewire::test(Bildersuche::class)->set('query', 'Aruula Schwert')->call('performSearch')->assertHasNoErrors()->assertSee('Testroman 1');
    $this->get(route('bildersuche.cover', [$cover, 'small']))->assertOk();
    expect($user->fresh()->email_verified_at !== null)->toBe($verified);
    $this->assertDatabaseCount('reward_purchases', 0);
})->with([[false, false, true], [false, true, true], [true, false, false], [true, true, true]]);
test('member without email confirmation can purchase bildersuche access', function (): void {
    $user = $this->actingMemberWithPoints(100);
    $user->forceFill(['email_verified_at' => null])->save();
    Livewire::test(Bildersuche::class)->assertSee('100 Baxx')->call('purchase')->assertHasNoErrors()->assertSee('bildersuche-query', false);
    expect(app(RewardService::class)->getAvailableBaxx($user))->toBe(0)
        ->and($user->fresh()->email_verified_at)->toBeNull();
    $this->assertDatabaseCount('reward_purchases', 1);
});
test('purchase unlocks immediately and does not allow a second charge', function (): void {
    $user = $this->actingMemberWithPoints(200);
    Livewire::test(Bildersuche::class)->call('purchase')->assertHasNoErrors()->assertSee('bildersuche-query', false)->call('purchase')->assertHasErrors('reward');
    expect(app(RewardService::class)->getAvailableBaxx($user))->toBe(100);
    $this->assertDatabaseCount('reward_purchases', 1);
    $this->assertDatabaseHas('reward_purchases', ['wallet_team_id' => Team::membersTeam()->id, 'cost_baxx' => 100]);
});
test('general rewards purchase also unlocks the search', function (): void {
    $user = $this->actingMemberWithPoints(100);
    Livewire::test(BelohnungenIndex::class)->call('purchase', Reward::where('slug', 'bildersuche')->value('id'));
    expect(app(BildersucheAccessService::class)->hasAccess($user))->toBeTrue();
    Livewire::test(BelohnungenIndex::class)->assertSee('Zur Bildersuche');
});
test('ag members cannot be charged through either purchase path', function (): void {
    $user = $this->actingMemberWithPoints(200);
    $this->joinAg($user);
    $reward = Reward::where('slug', 'bildersuche')->firstOrFail();
    Livewire::test(Bildersuche::class)->call('purchase')->assertHasErrors('reward');
    Livewire::test(BelohnungenIndex::class)->assertSee('Zugang durch AG-Mitgliedschaft')->call('purchase', $reward->id);
    expect(app(RewardService::class)->getAvailableBaxx($user))->toBe(200);
    $this->assertDatabaseCount('reward_purchases', 0);
});
test('insufficient baxx and disabled rewards do not create purchases', function (): void {
    $this->actingMemberWithPoints(99);
    Livewire::test(Bildersuche::class)->call('purchase')->assertHasErrors('reward');
    Reward::where('slug', 'bildersuche')->update(['is_active' => false]);
    Livewire::test(Bildersuche::class)->assertSee('Freischaltung ist derzeit nicht verfügbar')->call('purchase')->assertHasErrors('reward');
    $this->assertDatabaseCount('reward_purchases', 0);
});
test('same name and personal teams do not grant free access', function (bool $personal, bool $configured, string $purchasePath): void {
    $user = $this->actingMemberWithPoints(100);
    $trustedAg = Team::factory()->create(['name' => 'AG Maddraxikon', 'personal_team' => false]);
    $otherTeam = Team::factory()->create(['user_id' => $user->id, 'name' => 'Unrelated team', 'personal_team' => $personal]);
    $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
    $otherTeam->update(['name' => 'AG Maddraxikon']);
    config(['bildersuche.ag_team_id' => $configured ? $otherTeam->id : $trustedAg->id]);
    $cover = $this->searchCover(1, ['Aruula']);
    $access = app(BildersucheAccessService::class);
    expect($access->hasAccess($user))->toBeFalse();
    $this->get(route('bildersuche.cover', [$cover, 'small']))->assertForbidden();
    Livewire::test(Bildersuche::class)->assertSee('100 Baxx')->call('performSearch')->assertForbidden();
    Livewire::test(BelohnungenIndex::class)->assertDontSee('Zugang durch AG-Mitgliedschaft')->assertDontSee('Zur Bildersuche');
    if ($purchasePath === 'rewards') {
        Livewire::test(BelohnungenIndex::class)->call('purchase', Reward::where('slug', 'bildersuche')->value('id'))->assertSee('Zur Bildersuche')->assertDontSee('Zugang durch AG-Mitgliedschaft');
    } else {
        Livewire::test(Bildersuche::class)->call('purchase')->assertHasNoErrors()->assertSee('bildersuche-query', false);
    }
    expect($access->hasAccess($user))->toBeTrue()
        ->and(app(RewardService::class)->getAvailableBaxx($user))->toBe(0);
    $this->assertDatabaseCount('reward_purchases', 1);
})->with('BildersucheAccessTest.untrustedTeams');
test('trusted ag identity survives rename but not membership removal', function (): void {
    $user = $this->actingMemberWithPoints(100);
    $ag = $this->joinAg($user);
    $ag->update(['name' => 'Umbenannte Arbeitsgruppe']);
    config(['bildersuche.ag_team_id' => (string) $ag->id]);
    expect(app(BildersucheAccessService::class)->hasAccess($user))->toBeTrue();
    Livewire::test(BelohnungenIndex::class)->assertSee('Zugang durch AG-Mitgliedschaft');
    Livewire::test(Bildersuche::class)->call('purchase')->assertHasErrors('reward');
    $ag->users()->detach($user);
    expect(app(BildersucheAccessService::class)->hasAccess($user))->toBeFalse();
    Livewire::test(BelohnungenIndex::class)->assertDontSee('Zugang durch AG-Mitgliedschaft');
    $this->assertDatabaseCount('reward_purchases', 0);
});
test('missing or invalid ag identity never falls back to the name', function (mixed $teamId): void {
    $user = $this->actingMember();
    $this->joinAg($user);
    config(['bildersuche.ag_team_id' => $teamId]);
    expect(app(BildersucheAccessService::class)->hasAccess($user))->toBeFalse();
})->with('BildersucheAccessTest.invalidAgIdentities');
test('existing kompendium purchase does not unlock bildersuche', function (): void {
    $user = $this->actingMemberWithPoints(200);
    $reward = Reward::where('slug', 'kompendium')->first() ?? Reward::factory()->create(['slug' => 'kompendium', 'cost_baxx' => 100]);
    app(RewardService::class)->purchaseReward($user, $reward);
    expect(app(BildersucheAccessService::class)->hasAccess($user))->toBeFalse();
});
test('purchase rechecks reward state after acquiring the wallet lock', function (): void {
    $user = $this->actingMemberWithPoints(100);
    $staleReward = Reward::where('slug', 'bildersuche')->firstOrFail();
    Reward::whereKey($staleReward->id)->update(['is_active' => false]);
    try {
        app(RewardService::class)->purchaseReward($user, $staleReward);
        $this->fail('A deactivated reward must not be purchased.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('reward');
    }
    $this->assertDatabaseCount('reward_purchases', 0);
    expect(app(RewardService::class)->getAvailableBaxx($user))->toBe(100);
});
test('inactive reward preserves existing access but refund revokes it', function (): void {
    $user = $this->actingMemberWithPoints(100);
    $reward = Reward::where('slug', 'bildersuche')->firstOrFail();
    $purchase = app(RewardService::class)->purchaseReward($user, $reward);
    $reward->update(['is_active' => false]);
    expect(app(BildersucheAccessService::class)->hasAccess($user))->toBeTrue();
    app(RewardService::class)->refundPurchase($purchase, $user);
    expect(app(BildersucheAccessService::class)->hasAccess($user))->toBeFalse();
    $ag = $this->joinAg($user);
    expect(app(BildersucheAccessService::class)->hasAccess($user))->toBeTrue();
    $ag->users()->detach($user);
    expect(app(BildersucheAccessService::class)->hasAccess($user))->toBeFalse();
});
test('revocation is checked again on livewire requests and conditional images', function (): void {
    $user = $this->actingMember();
    $ag = $this->joinAg($user);
    $cover = $this->searchCover(1, ['Aruula']);
    Storage::disk('private')->put($cover->small_path, 'webp');
    $response = $this->get(route('bildersuche.cover', [$cover, 'small']))->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-cache');
    $etag = $response->headers->get('ETag');
    $this->withHeader('If-None-Match', $etag)->get(route('bildersuche.cover', [$cover, 'small']))->assertStatus(304);
    $component = Livewire::test(Bildersuche::class);
    $ag->users()->detach($user);
    $component->set('query', 'Aruula')->call('performSearch')->assertForbidden();
    $this->withHeader('If-None-Match', $etag)->get(route('bildersuche.cover', [$cover, 'small']))->assertForbidden();
});
test('images validate variant file and source mapping', function (): void {
    $user = $this->actingMember();
    $this->joinAg($user);
    $cover = $this->searchCover(1, ['Aruula']);
    $this->get(route('bildersuche.cover', [$cover, 'large']))->assertNotFound();
    Storage::disk('private')->put($cover->large_path, 'webp');
    $this->get(route('bildersuche.cover', [$cover, 'large']))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get(route('bildersuche.cover', [$cover, 'original']))->assertNotFound();
    $cover->update(['source_file_title' => 'Datei:Other.jpg']);
    $this->get(route('bildersuche.cover', [$cover, 'large']))->assertNotFound();
});
test('url queries validation and pagination are preserved', function (): void {
    $user = $this->actingMember();
    $this->joinAg($user);
    foreach (range(1, 25) as $number) {
        $this->searchCover($number, ['Drax']);
    }
    Livewire::withQueryParams(['q' => 'Drax', 'page' => 2])->test(Bildersuche::class)->assertSee('Testroman 25')->set('series', 'maddrax')->assertSet('paginators.page', 1)->set('query', 'a')->call('performSearch')->assertHasErrors('query');
    Livewire::withQueryParams(['q' => 'Drax', 'series' => 'bad'])->test(Bildersuche::class)->assertHasErrors('series');
    Livewire::withQueryParams([])->test(Bildersuche::class)->set('query', 'Drax')->call('performSearch')->set('query', str_repeat('x', 201))->assertHasErrors('query');
    Livewire::withQueryParams([])->test(Bildersuche::class)->set('query', 'Unbekannt')->call('performSearch')->assertSee('Keine Treffer');
});
test('empty inventory and unavailable preview are handled', function (): void {
    $user = $this->actingMember();
    $this->joinAg($user);
    Livewire::test(Bildersuche::class)->assertSee('Es sind noch keine Cover mit Schlagworten verfügbar')->set('series', 'maddrax')->assertSet('hasSearched', false)->call('showCover', 999999)->assertNotFound();
});
test('each search request fetches the paginator and relationships only once', function (): void {
    $user = $this->actingMember();
    $this->joinAg($user);
    foreach (range(1, 25) as $number) {
        $this->searchCover($number, ['Matthew Drax', 'Schwert']);
    }
    $this->assertSingleSearch(fn () => Livewire::withQueryParams(['q' => 'Drax Schwert', 'page' => 2])->test(Bildersuche::class)->assertSee('Testroman 25'));
    $component = Livewire::withQueryParams([])->test(Bildersuche::class)->set('query', 'Drax Schwert');
    $this->assertSingleSearch(fn () => $component->call('performSearch')->assertSee('Testroman 1'));
    $this->assertSingleSearch(fn () => $component->set('series', 'maddrax')->assertSee('Testroman 1'));
    $this->assertSingleSearch(fn () => $component->call('gotoPage', 2)->assertSee('Testroman 25'));
});
test('preview id cannot be injected', function (): void {
    $user = $this->actingMember();
    $this->joinAg($user);
    expect(fn () => Livewire::test(Bildersuche::class)->set('previewId', 999))->toThrow(CannotUpdateLockedPropertyException::class);
});
test('ag team switch preserves member access and excludes applicants and outsiders', function (): void {
    $admin = $this->actingAdmin();
    expect(app(BildersucheAccessService::class)->hasAccess($admin))->toBeFalse();
    $ag = $this->joinAg($admin);
    $admin->forceFill(['current_team_id' => $ag->id])->save();
    Livewire::test(Bildersuche::class)->assertSee('bildersuche-query', false);
    $applicant = $this->createUserWithRole(Role::Anwaerter);
    $this->joinAg($applicant);
    $applicant->forceFill(['current_team_id' => $ag->id])->save();
    $this->actingAs($applicant);
    Livewire::test(Bildersuche::class)->assertForbidden();
    $outsider = User::factory()->create();
    $this->joinAg($outsider);
    $this->actingAs($outsider);
    Livewire::test(Bildersuche::class)->assertForbidden();
});
test('menu is available for eligible members and feature switch is respected', function (): void {
    $user = $this->actingMember();
    $builder = app(NavigationBuilder::class);
    expect(json_encode($builder->build($user), JSON_UNESCAPED_SLASHES))->toContain(route('bildersuche.index'))
        ->and(json_encode($builder->build(null)))->not->toContain('bildersuche');
    config(['bildersuche.enabled' => false]);
    expect(json_encode($builder->build($user)))->not->toContain('bildersuche');
    $this->get(route('bildersuche.index'))->assertNotFound();
    Livewire::test(BelohnungenIndex::class)->call('purchase', Reward::where('slug', 'bildersuche')->value('id'));
    $this->assertDatabaseCount('reward_purchases', 0);
});
test('reward migration reapplication restores availability and keeps customizations and purchases', function (): void {
    $user = $this->actingMemberWithPoints(100);
    $reward = Reward::where('slug', 'bildersuche')->firstOrFail();
    app(RewardService::class)->purchaseReward($user, $reward);
    $reward->update(['cost_baxx' => 123, 'title' => 'Individuelle Bildersuche', 'description' => 'Eigene Beschreibung', 'sort_order' => 42]);
    $customized = $reward->only(['id', 'slug', 'title', 'description', 'category', 'cost_baxx', 'sort_order', 'created_at']);
    $migration = require database_path('migrations/2026_10_18_120100_add_bildersuche_reward.php');
    $migration->up();
    expect($reward->fresh()->cost_baxx)->toBe(123);
    $migration->down();
    $migration->down();
    expect($reward->fresh()->is_active)->toBeFalse()
        ->and(app(BildersucheAccessService::class)->hasAccess($user))->toBeTrue();
    $migration->up();
    $migration->up();
    expect($reward->fresh()->is_active)->toBeTrue()
        ->and($reward->fresh()->only(array_keys($customized)))->toEqual($customized)
        ->and(RewardPurchase::count())->toBe(1);
    $this->assertDatabaseHas('reward_purchases', ['reward_id' => $reward->id, 'user_id' => $user->id, 'cost_baxx' => 100]);
    $newBuyer = $this->actingMemberWithPoints(123);
    app(RewardService::class)->purchaseReward($newBuyer, $reward->fresh());
    expect(app(RewardService::class)->getAvailableBaxx($newBuyer))->toBe(0);
});
test('reward migration preserves an existing admin deactivation', function (): void {
    $reward = Reward::where('slug', 'bildersuche')->firstOrFail();
    $reward->update(['is_active' => false, 'cost_baxx' => 123]);
    $migration = require database_path('migrations/2026_10_18_120100_add_bildersuche_reward.php');
    $migration->up();
    $migration->down();
    $migration->up();
    expect($reward->fresh()->is_active)->toBeFalse()
        ->and($reward->fresh()->cost_baxx)->toBe(123);
    $this->assertDatabaseHas('rewards', ['id' => $reward->id, 'disabled_by_rollback' => false]);
});
test('admin saving inactive status after rollback prevents reactivation', function (): void {
    $this->actingAdmin();
    config(['app.testing_minimal_belohnungen_admin' => true]);
    $reward = Reward::where('slug', 'bildersuche')->firstOrFail();
    $migration = require database_path('migrations/2026_10_18_120100_add_bildersuche_reward.php');
    $migration->down();
    $this->assertDatabaseHas('rewards', ['id' => $reward->id, 'disabled_by_rollback' => true]);
    Livewire::test(BelohnungenAdmin::class)->call('openEditReward', $reward->id)->set('rewardIsActive', false)->call('saveReward')->assertHasNoErrors();
    $migration->up();
    expect($reward->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseHas('rewards', ['id' => $reward->id, 'disabled_by_rollback' => false]);
});
