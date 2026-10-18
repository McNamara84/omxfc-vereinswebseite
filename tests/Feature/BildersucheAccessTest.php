<?php

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\CreatesBildersucheCover;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class BildersucheAccessTest extends TestCase
{
    use CreatesBildersucheCover;
    use CreatesUserWithRole;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['bildersuche.enabled' => true, 'bildersuche.ag_team_id' => null, 'cover-ratings.enabled' => false]);
        Storage::fake('private');
    }

    private function joinAg(User $user): Team
    {
        $team = Team::factory()->create(['name' => 'AG Maddraxikon', 'personal_team' => false]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);
        config(['bildersuche.ag_team_id' => $team->id]);

        return $team;
    }

    public function test_member_without_purchase_sees_only_unlock_and_cannot_search_or_open_images(): void
    {
        $this->get(route('bildersuche.index'))->assertRedirect(route('login'));
        $this->actingMember();
        $cover = $this->searchCover(1, ['Aruula']);
        $this->get(route('bildersuche.index'))->assertOk()->assertSee('100 Baxx')->assertDontSee('bildersuche-query', false);
        Livewire::test(Bildersuche::class)->set('query', 'Aruula')->call('performSearch')->assertForbidden();
        Livewire::test(Bildersuche::class)->call('showCover', $cover->id)->assertForbidden();
        $this->get(route('bildersuche.cover', [$cover, 'small']))->assertForbidden();
    }

    public function test_ag_member_can_search_preview_and_follow_a_safe_source_link_without_purchase(): void
    {
        $user = $this->actingMember();
        $this->joinAg($user);
        [$cover] = $this->exampleCovers();
        $cover->update(['source_description_url' => 'javascript:alert(1)', 'source_artist' => '<script>alert(2)</script>']);
        Livewire::test(Bildersuche::class)->set('query', 'Aruula Schwert')->call('performSearch')
            ->assertSee('Testroman 1')->assertDontSee('Testroman 2')->call('showCover', $cover->id)
            ->assertSee('F-17 Alpha 2')->assertSee('Dateiseite im Maddraxikon')
            ->assertDontSee('href="javascript:', false)->assertDontSee('<script>alert(2)</script>', false)
            ->call('closePreview')->assertSet('previewId', null);
        $this->assertDatabaseCount('reward_purchases', 0);
    }

    #[TestWith([false, false, true])]
    #[TestWith([false, true, true])]
    #[TestWith([true, false, false])]
    #[TestWith([true, true, true])]
    public function test_ag_access_matches_the_applications_email_verification_requirement(bool $requiresVerification, bool $verified, bool $allowed): void
    {
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
        $this->assertSame($allowed, $access->isEligible($user));
        $this->assertSame($allowed, $access->hasAccess($user));
        $response = $this->get(route('bildersuche.index'));
        if (! $allowed) {
            $response->assertRedirect(route('verification.notice'));

            return;
        }

        $response->assertOk()->assertSee('bildersuche-query', false);
        $cover = $this->searchCover(1, ['Aruula', 'Schwert']);
        Storage::disk('private')->put($cover->small_path, 'webp');
        Livewire::test(Bildersuche::class)->set('query', 'Aruula Schwert')->call('performSearch')
            ->assertHasNoErrors()->assertSee('Testroman 1');
        $this->get(route('bildersuche.cover', [$cover, 'small']))->assertOk();
        $this->assertSame($verified, $user->fresh()->email_verified_at !== null);
        $this->assertDatabaseCount('reward_purchases', 0);
    }

    public function test_member_without_email_confirmation_can_purchase_bildersuche_access(): void
    {
        $user = $this->actingMemberWithPoints(100);
        $user->forceFill(['email_verified_at' => null])->save();

        Livewire::test(Bildersuche::class)->assertSee('100 Baxx')->call('purchase')
            ->assertHasNoErrors()->assertSee('bildersuche-query', false);

        $this->assertSame(0, app(RewardService::class)->getAvailableBaxx($user));
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertDatabaseCount('reward_purchases', 1);
    }

    public function test_purchase_unlocks_immediately_and_does_not_allow_a_second_charge(): void
    {
        $user = $this->actingMemberWithPoints(200);
        Livewire::test(Bildersuche::class)->call('purchase')->assertHasNoErrors()->assertSee('bildersuche-query', false)
            ->call('purchase')->assertHasErrors('reward');
        $this->assertSame(100, app(RewardService::class)->getAvailableBaxx($user));
        $this->assertDatabaseCount('reward_purchases', 1);
        $this->assertDatabaseHas('reward_purchases', ['wallet_team_id' => Team::membersTeam()->id, 'cost_baxx' => 100]);
    }

    public function test_general_rewards_purchase_also_unlocks_the_search(): void
    {
        $user = $this->actingMemberWithPoints(100);
        Livewire::test(BelohnungenIndex::class)->call('purchase', Reward::where('slug', 'bildersuche')->value('id'));
        $this->assertTrue(app(BildersucheAccessService::class)->hasAccess($user));
        Livewire::test(BelohnungenIndex::class)->assertSee('Zur Bildersuche');
    }

    public function test_ag_members_cannot_be_charged_through_either_purchase_path(): void
    {
        $user = $this->actingMemberWithPoints(200);
        $this->joinAg($user);
        $reward = Reward::where('slug', 'bildersuche')->firstOrFail();
        Livewire::test(Bildersuche::class)->call('purchase')->assertHasErrors('reward');
        Livewire::test(BelohnungenIndex::class)->assertSee('Zugang durch AG-Mitgliedschaft')->call('purchase', $reward->id);
        $this->assertSame(200, app(RewardService::class)->getAvailableBaxx($user));
        $this->assertDatabaseCount('reward_purchases', 0);
    }

    public function test_insufficient_baxx_and_disabled_rewards_do_not_create_purchases(): void
    {
        $this->actingMemberWithPoints(99);
        Livewire::test(Bildersuche::class)->call('purchase')->assertHasErrors('reward');
        Reward::where('slug', 'bildersuche')->update(['is_active' => false]);
        Livewire::test(Bildersuche::class)->assertSee('Freischaltung ist derzeit nicht verfügbar')->call('purchase')->assertHasErrors('reward');
        $this->assertDatabaseCount('reward_purchases', 0);
    }

    #[DataProvider('untrustedTeams')]
    public function test_same_name_and_personal_teams_do_not_grant_free_access(bool $personal, bool $configured, string $purchasePath): void
    {
        $user = $this->actingMemberWithPoints(100);
        $trustedAg = Team::factory()->create(['name' => 'AG Maddraxikon', 'personal_team' => false]);
        $otherTeam = Team::factory()->create(['user_id' => $user->id, 'name' => 'Unrelated team', 'personal_team' => $personal]);
        $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
        $otherTeam->update(['name' => 'AG Maddraxikon']);
        config(['bildersuche.ag_team_id' => $configured ? $otherTeam->id : $trustedAg->id]);
        $cover = $this->searchCover(1, ['Aruula']);
        $access = app(BildersucheAccessService::class);

        $this->assertFalse($access->hasAccess($user));
        $this->get(route('bildersuche.cover', [$cover, 'small']))->assertForbidden();
        Livewire::test(Bildersuche::class)->assertSee('100 Baxx')->call('performSearch')->assertForbidden();
        Livewire::test(BelohnungenIndex::class)->assertDontSee('Zugang durch AG-Mitgliedschaft')->assertDontSee('Zur Bildersuche');

        if ($purchasePath === 'rewards') {
            Livewire::test(BelohnungenIndex::class)->call('purchase', Reward::where('slug', 'bildersuche')->value('id'))
                ->assertSee('Zur Bildersuche')->assertDontSee('Zugang durch AG-Mitgliedschaft');
        } else {
            Livewire::test(Bildersuche::class)->call('purchase')->assertHasNoErrors()->assertSee('bildersuche-query', false);
        }
        $this->assertTrue($access->hasAccess($user));
        $this->assertSame(0, app(RewardService::class)->getAvailableBaxx($user));
        $this->assertDatabaseCount('reward_purchases', 1);
    }

    public static function untrustedTeams(): array
    {
        return [
            'unrelated team / search' => [false, false, 'search'],
            'unrelated team / rewards' => [false, false, 'rewards'],
            'personal team / search' => [true, false, 'search'],
            'personal team / rewards' => [true, false, 'rewards'],
            'configured personal team / search' => [true, true, 'search'],
            'configured personal team / rewards' => [true, true, 'rewards'],
        ];
    }

    public function test_trusted_ag_identity_survives_rename_but_not_membership_removal(): void
    {
        $user = $this->actingMemberWithPoints(100);
        $ag = $this->joinAg($user);
        $ag->update(['name' => 'Umbenannte Arbeitsgruppe']);
        config(['bildersuche.ag_team_id' => (string) $ag->id]);
        $this->assertTrue(app(BildersucheAccessService::class)->hasAccess($user));
        Livewire::test(BelohnungenIndex::class)->assertSee('Zugang durch AG-Mitgliedschaft');
        Livewire::test(Bildersuche::class)->call('purchase')->assertHasErrors('reward');
        $ag->users()->detach($user);
        $this->assertFalse(app(BildersucheAccessService::class)->hasAccess($user));
        Livewire::test(BelohnungenIndex::class)->assertDontSee('Zugang durch AG-Mitgliedschaft');
        $this->assertDatabaseCount('reward_purchases', 0);
    }

    #[DataProvider('invalidAgIdentities')]
    public function test_missing_or_invalid_ag_identity_never_falls_back_to_the_name(mixed $teamId): void
    {
        $user = $this->actingMember();
        $this->joinAg($user);
        config(['bildersuche.ag_team_id' => $teamId]);
        $this->assertFalse(app(BildersucheAccessService::class)->hasAccess($user));
    }

    public static function invalidAgIdentities(): array
    {
        return [[null], [''], [0], [-1], ['not-an-id'], ['1abc'], ['1.5'], [999999]];
    }

    public function test_existing_kompendium_purchase_does_not_unlock_bildersuche(): void
    {
        $user = $this->actingMemberWithPoints(200);
        $reward = Reward::where('slug', 'kompendium')->first()
            ?? Reward::factory()->create(['slug' => 'kompendium', 'cost_baxx' => 100]);
        app(RewardService::class)->purchaseReward($user, $reward);
        $this->assertFalse(app(BildersucheAccessService::class)->hasAccess($user));
    }

    public function test_purchase_rechecks_reward_state_after_acquiring_the_wallet_lock(): void
    {
        $user = $this->actingMemberWithPoints(100);
        $staleReward = Reward::where('slug', 'bildersuche')->firstOrFail();
        Reward::whereKey($staleReward->id)->update(['is_active' => false]);
        try {
            app(RewardService::class)->purchaseReward($user, $staleReward);
            $this->fail('A deactivated reward must not be purchased.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reward', $exception->errors());
        }
        $this->assertDatabaseCount('reward_purchases', 0);
        $this->assertSame(100, app(RewardService::class)->getAvailableBaxx($user));
    }

    public function test_inactive_reward_preserves_existing_access_but_refund_revokes_it(): void
    {
        $user = $this->actingMemberWithPoints(100);
        $reward = Reward::where('slug', 'bildersuche')->firstOrFail();
        $purchase = app(RewardService::class)->purchaseReward($user, $reward);
        $reward->update(['is_active' => false]);
        $this->assertTrue(app(BildersucheAccessService::class)->hasAccess($user));
        app(RewardService::class)->refundPurchase($purchase, $user);
        $this->assertFalse(app(BildersucheAccessService::class)->hasAccess($user));
        $ag = $this->joinAg($user);
        $this->assertTrue(app(BildersucheAccessService::class)->hasAccess($user));
        $ag->users()->detach($user);
        $this->assertFalse(app(BildersucheAccessService::class)->hasAccess($user));
    }

    public function test_revocation_is_checked_again_on_livewire_requests_and_conditional_images(): void
    {
        $user = $this->actingMember();
        $ag = $this->joinAg($user);
        $cover = $this->searchCover(1, ['Aruula']);
        Storage::disk('private')->put($cover->small_path, 'webp');
        $response = $this->get(route('bildersuche.cover', [$cover, 'small']))->assertOk();
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $etag = $response->headers->get('ETag');
        $this->withHeader('If-None-Match', $etag)->get(route('bildersuche.cover', [$cover, 'small']))->assertStatus(304);
        $component = Livewire::test(Bildersuche::class);
        $ag->users()->detach($user);
        $component->set('query', 'Aruula')->call('performSearch')->assertForbidden();
        $this->withHeader('If-None-Match', $etag)->get(route('bildersuche.cover', [$cover, 'small']))->assertForbidden();
    }

    public function test_images_validate_variant_file_and_source_mapping(): void
    {
        $user = $this->actingMember();
        $this->joinAg($user);
        $cover = $this->searchCover(1, ['Aruula']);
        $this->get(route('bildersuche.cover', [$cover, 'large']))->assertNotFound();
        Storage::disk('private')->put($cover->large_path, 'webp');
        $this->get(route('bildersuche.cover', [$cover, 'large']))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('bildersuche.cover', [$cover, 'original']))->assertNotFound();
        $cover->update(['source_file_title' => 'Datei:Other.jpg']);
        $this->get(route('bildersuche.cover', [$cover, 'large']))->assertNotFound();
    }

    public function test_url_queries_validation_and_pagination_are_preserved(): void
    {
        $user = $this->actingMember();
        $this->joinAg($user);
        foreach (range(1, 25) as $number) {
            $this->searchCover($number, ['Drax']);
        }
        Livewire::withQueryParams(['q' => 'Drax', 'page' => 2])->test(Bildersuche::class)
            ->assertSee('Testroman 25')->set('series', 'maddrax')->assertSet('paginators.page', 1)
            ->set('query', 'a')->call('performSearch')->assertHasErrors('query');
        Livewire::withQueryParams(['q' => 'Drax', 'series' => 'bad'])->test(Bildersuche::class)->assertHasErrors('series');
        Livewire::withQueryParams([])->test(Bildersuche::class)->set('query', 'Drax')->call('performSearch')
            ->set('query', str_repeat('x', 201))->assertHasErrors('query');
        Livewire::withQueryParams([])->test(Bildersuche::class)->set('query', 'Unbekannt')->call('performSearch')->assertSee('Keine Treffer');
    }

    public function test_empty_inventory_and_unavailable_preview_are_handled(): void
    {
        $user = $this->actingMember();
        $this->joinAg($user);
        Livewire::test(Bildersuche::class)->assertSee('Es sind noch keine Cover mit Schlagworten verfügbar')
            ->set('series', 'maddrax')->assertSet('hasSearched', false)
            ->call('showCover', 999999)->assertNotFound();
    }

    public function test_each_search_request_fetches_the_paginator_and_relationships_only_once(): void
    {
        $user = $this->actingMember();
        $this->joinAg($user);
        foreach (range(1, 25) as $number) {
            $this->searchCover($number, ['Matthew Drax', 'Schwert']);
        }

        $this->assertSingleSearch(fn () => Livewire::withQueryParams(['q' => 'Drax Schwert', 'page' => 2])
            ->test(Bildersuche::class)->assertSee('Testroman 25'));
        $component = Livewire::withQueryParams([])->test(Bildersuche::class)->set('query', 'Drax Schwert');
        $this->assertSingleSearch(fn () => $component->call('performSearch')->assertSee('Testroman 1'));
        $this->assertSingleSearch(fn () => $component->set('series', 'maddrax')->assertSee('Testroman 1'));
        $this->assertSingleSearch(fn () => $component->call('gotoPage', 2)->assertSee('Testroman 25'));
    }

    private function assertSingleSearch(callable $request): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $request();
            $queries = collect(DB::getQueryLog())->pluck('query');
            $searchQueries = $queries->filter(fn (string $sql) => str_contains($sql, 'normalized_keyword LIKE'));
            $this->assertCount(2, $searchQueries, 'Exactly one pagination count and one result query per request.');
            $this->assertCount(1, $searchQueries->filter(fn (string $sql) => str_contains($sql, 'count(*)')));
            foreach (['books', 'book_cover_keywords'] as $relation) {
                $this->assertCount(1, $queries->filter(fn (string $sql) => preg_match('/^select \* from [`"]'.$relation.'[`"] /i', $sql)),
                    'Load '.$relation.' only once per search.');
            }
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_preview_id_cannot_be_injected(): void
    {
        $user = $this->actingMember();
        $this->joinAg($user);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(Bildersuche::class)->set('previewId', 999);
    }

    public function test_ag_team_switch_preserves_member_access_and_excludes_applicants_and_outsiders(): void
    {
        $admin = $this->actingAdmin();
        $this->assertFalse(app(BildersucheAccessService::class)->hasAccess($admin));
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
    }

    public function test_menu_is_available_for_eligible_members_and_feature_switch_is_respected(): void
    {
        $user = $this->actingMember();
        $builder = app(NavigationBuilder::class);
        $this->assertStringContainsString(route('bildersuche.index'), json_encode($builder->build($user), JSON_UNESCAPED_SLASHES));
        $this->assertStringNotContainsString('bildersuche', json_encode($builder->build(null)));
        config(['bildersuche.enabled' => false]);
        $this->assertStringNotContainsString('bildersuche', json_encode($builder->build($user)));
        $this->get(route('bildersuche.index'))->assertNotFound();
        Livewire::test(BelohnungenIndex::class)->call('purchase', Reward::where('slug', 'bildersuche')->value('id'));
        $this->assertDatabaseCount('reward_purchases', 0);
    }

    public function test_reward_migration_reapplication_restores_availability_and_keeps_customizations_and_purchases(): void
    {
        $user = $this->actingMemberWithPoints(100);
        $reward = Reward::where('slug', 'bildersuche')->firstOrFail();
        app(RewardService::class)->purchaseReward($user, $reward);
        $reward->update(['cost_baxx' => 123, 'title' => 'Individuelle Bildersuche', 'description' => 'Eigene Beschreibung', 'sort_order' => 42]);
        $customized = $reward->only(['id', 'slug', 'title', 'description', 'category', 'cost_baxx', 'sort_order', 'created_at']);
        $migration = require database_path('migrations/2026_10_18_120100_add_bildersuche_reward.php');
        $migration->up();
        $this->assertSame(123, $reward->fresh()->cost_baxx);
        $migration->down();
        $migration->down();
        $this->assertFalse($reward->fresh()->is_active);
        $this->assertTrue(app(BildersucheAccessService::class)->hasAccess($user));
        $migration->up();
        $migration->up();
        $this->assertTrue($reward->fresh()->is_active);
        $this->assertEquals($customized, $reward->fresh()->only(array_keys($customized)));
        $this->assertSame(1, RewardPurchase::count());
        $this->assertDatabaseHas('reward_purchases', ['reward_id' => $reward->id, 'user_id' => $user->id, 'cost_baxx' => 100]);
        $newBuyer = $this->actingMemberWithPoints(123);
        app(RewardService::class)->purchaseReward($newBuyer, $reward->fresh());
        $this->assertSame(0, app(RewardService::class)->getAvailableBaxx($newBuyer));
    }

    public function test_reward_migration_preserves_an_existing_admin_deactivation(): void
    {
        $reward = Reward::where('slug', 'bildersuche')->firstOrFail();
        $reward->update(['is_active' => false, 'cost_baxx' => 123]);
        $migration = require database_path('migrations/2026_10_18_120100_add_bildersuche_reward.php');
        $migration->up();
        $migration->down();
        $migration->up();
        $this->assertFalse($reward->fresh()->is_active);
        $this->assertSame(123, $reward->fresh()->cost_baxx);
        $this->assertDatabaseHas('rewards', ['id' => $reward->id, 'disabled_by_rollback' => false]);
    }

    public function test_admin_saving_inactive_status_after_rollback_prevents_reactivation(): void
    {
        $this->actingAdmin();
        config(['app.testing_minimal_belohnungen_admin' => true]);
        $reward = Reward::where('slug', 'bildersuche')->firstOrFail();
        $migration = require database_path('migrations/2026_10_18_120100_add_bildersuche_reward.php');
        $migration->down();
        $this->assertDatabaseHas('rewards', ['id' => $reward->id, 'disabled_by_rollback' => true]);
        Livewire::test(BelohnungenAdmin::class)->call('openEditReward', $reward->id)
            ->set('rewardIsActive', false)->call('saveReward')->assertHasNoErrors();
        $migration->up();
        $this->assertFalse($reward->fresh()->is_active);
        $this->assertDatabaseHas('rewards', ['id' => $reward->id, 'disabled_by_rollback' => false]);
    }
}
