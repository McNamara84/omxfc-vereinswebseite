<?php

namespace Tests\Integration;

use App\Actions\Fanfiction\PublishFanfiction;
use App\Enums\FanfictionStatus;
use App\Enums\Role;
use App\Enums\TodoStatus;
use App\Http\Controllers\FanfictionAdminController;
use App\Livewire\RezensionForm;
use App\Livewire\TodoForm;
use App\Models\BaxxEarningProgress;
use App\Models\BaxxEarningRule;
use App\Models\CoverRating;
use App\Models\Fanfiction;
use App\Models\MaddraxiversumBaxxSpecialOffer;
use App\Models\Mission;
use App\Models\ReviewBaxxSpecialOffer;
use App\Models\Reward;
use App\Models\RomantauschBaxxSpecialOffer;
use App\Models\Team;
use App\Models\Todo;
use App\Models\TodoCategory;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\Baxx\BaxxSpecialOfferManager;
use App\Services\CoverRatings\CoverRatingBaxxService;
use App\Services\Maddraxikon\MaddraxikonRewardService;
use App\Services\MaddraxiversumBaxxService;
use App\Services\MissionCompletionService;
use App\Services\ReviewBaxxService;
use App\Services\RewardService;
use App\Services\Romantausch\RomantauschBaxxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class BaxxBoundaryTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    public function test_rule_adapters_return_current_models_and_expired_offers_disappear(): void
    {
        $review = app(ReviewBaxxService::class);
        $mission = app(MaddraxiversumBaxxService::class);
        $swap = app(RomantauschBaxxService::class);
        $this->assertSame('rezension', $review->getBaseRule()->action_key);
        $this->assertSame('maddraxiversum_mission', $mission->getBaseRule()->action_key);
        $data = ['points' => 7, 'every_count' => 1, 'is_active' => true, 'ends_at' => now()->addMinute()];
        $reviewOffer = ReviewBaxxSpecialOffer::create($data);
        $missionOffer = MaddraxiversumBaxxSpecialOffer::create($data);
        $swapOffer = RomantauschBaxxSpecialOffer::create($data + ['action_key' => 'romantausch_offer']);
        $this->assertTrue($review->getActiveSpecialOffer()->is($reviewOffer));
        $this->assertTrue($mission->getActiveSpecialOffer()->is($missionOffer));
        $this->assertTrue($swap->getActiveSpecialOffer('romantausch_offer')->is($swapOffer));
        $this->assertNull($swap->getActiveSpecialOffer('romantausch_request'));
        $this->travel(1)->minutes();
        $this->assertNull($review->getActiveSpecialOffer());
        $this->assertNull($mission->getActiveSpecialOffer());
        $this->assertNull($swap->getActiveSpecialOffer('romantausch_offer'));
    }

    public function test_review_without_a_wallet_does_not_book_but_explicit_team_still_works(): void
    {
        $user = User::factory()->create();
        $user->setRelation('currentTeam', null);
        $review = app(ReviewBaxxService::class);
        $review->getBaseRule()->update(['points' => 4, 'every_count' => 1]);
        $this->assertSame(0, $review->awardPointsForReview($user, 1));
        $this->assertDatabaseCount('user_points', 0);
        $this->assertSame(4, $review->awardPointsForReview($user, 1, Team::membersTeam()->id));
        $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'team_id' => Team::membersTeam()->id, 'points' => 4]);
    }

    public function test_empty_romantausch_batches_do_not_create_progress_or_points(): void
    {
        $user = $this->actingMember();
        $swap = app(RomantauschBaxxService::class);
        $this->assertSame(0, $swap->awardForNewOffers($user->id, 0));
        $this->assertSame(0, $swap->awardForNewRequests($user->id, -1));
        $this->assertDatabaseCount('user_points', 0);
        $this->assertDatabaseCount('baxx_earning_progress', 0);
    }

    public function test_disabled_cover_reward_advances_progress_without_backpay_after_activation(): void
    {
        $user = $this->actingMember();
        CoverRating::factory()->for($user)->create();
        $service = app(CoverRatingBaxxService::class);
        $service->getRule()->update(['is_active' => false, 'every_count' => 1, 'points' => 3]);
        $this->assertSame(0, $service->awardForRating($user, true));
        $this->assertSame(1, BaxxEarningProgress::firstOrFail()->processed_count);
        $service->getRule()->update(['is_active' => true]);
        $this->assertSame(0, $service->awardForRating($user, false));
        $this->assertDatabaseCount('user_points', 0);
    }

    public function test_missing_members_team_rolls_back_cover_progress_instead_of_using_another_wallet(): void
    {
        $user = $this->actingMember();
        CoverRating::factory()->for($user)->create();
        $service = app(CoverRatingBaxxService::class);
        $service->getRule()->update(['every_count' => 1, 'points' => 3]);
        Team::membersTeam()->update(['name' => 'Kein Mitglieder-Team']);
        try {
            $service->awardForRating($user, true);
            $this->fail('Missing members team must fail before booking.');
        } catch (LogicException) {
            $this->assertDatabaseCount('user_points', 0);
            $this->assertDatabaseCount('baxx_earning_progress', 0);
        }
    }

    public function test_missing_members_team_prevents_maddraxikon_awards(): void
    {
        Team::membersTeam()->update(['name' => 'Kein Mitglieder-Team']);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Das Mitglieder-Team fehlt');
        app(MaddraxikonRewardService::class)->evaluate(force: true);
    }

    public function test_todo_form_redirects_foreign_team_challenges_without_loading_their_fields(): void
    {
        $admin = $this->actingAdmin();
        $todo = Todo::create(['team_id' => Team::factory()->create()->id, 'created_by' => $admin->id,
            'title' => 'Fremde Challenge', 'points' => 4, 'status' => TodoStatus::Open,
            'category_id' => TodoCategory::create(['name' => 'Test', 'slug' => 'test'])->id]);
        Livewire::test(TodoForm::class, ['todo' => $todo])->assertRedirect(route('todos.index'))->assertSet('todoId', null);
    }

    public function test_review_form_requires_an_existing_book_or_review(): void
    {
        $this->actingMember();
        Livewire::test(RezensionForm::class)->assertNotFound();
    }

    public function test_user_wallet_helpers_follow_purchase_and_refund(): void
    {
        $user = $this->actingMemberWithPoints(20);
        $admin = $this->createUserWithRole(Role::Admin);
        $reward = Reward::factory()->create(['cost_baxx' => 5]);
        $purchase = app(RewardService::class)->purchaseReward($user, $reward);
        $this->assertTrue($user->hasUnlockedReward($reward->slug));
        $this->assertSame(5, $user->getSpentBaxx());
        $this->assertSame(1, $user->rewardPurchases()->count());
        $this->assertSame(1, $user->activeRewardPurchases()->count());
        app(RewardService::class)->refundPurchase($purchase, $admin);
        $this->assertFalse($user->hasUnlockedReward($reward->slug));
        $this->assertSame(0, $user->getSpentBaxx());
        $this->assertSame(1, $user->rewardPurchases()->count());
        $this->assertSame(0, $user->activeRewardPurchases()->count());
    }

    public static function legacyPublications(): array
    {
        return ['draft member' => ['member', 'draft', 0], 'published member' => ['member', 'published', 11],
            'published guest' => ['external', 'published', 0]];
    }

    #[DataProvider('legacyPublications')]
    public function test_retained_controller_uses_the_same_publication_contract(string $type, string $status, int $points): void
    {
        $this->actingVorstand();
        $author = $this->createUserWithRole(Role::Mitglied);
        BaxxEarningRule::where('action_key', 'fanfiction_publish')->update(['points' => 11]);
        // The retained controller is an adapter; production creation routes use Livewire.
        Route::post('/test/legacy-fanfiction', [FanfictionAdminController::class, 'store'])->middleware('web');
        $this->post('/test/legacy-fanfiction', ['title' => 'Controllergeschichte', 'author_type' => $type,
            'user_id' => $author->id, 'author_name' => $author->name, 'content' => 'Eine Geschichte aus dem alten Einstieg.', 'status' => $status])
            ->assertRedirect(route('admin.fanfiction.index'))->assertSessionHasNoErrors();
        $story = Fanfiction::firstOrFail();
        $this->assertSame(FanfictionStatus::from($status), $story->status);
        $this->assertSame($points, (int) UserPoint::sum('points'));
        $this->assertSame($status === 'published', $story->reward()->exists());
    }

    public function test_retained_controller_rolls_back_story_creation_when_publication_fails(): void
    {
        $this->actingVorstand();
        $author = $this->createUserWithRole(Role::Mitglied);
        Route::post('/test/legacy-fanfiction', [FanfictionAdminController::class, 'store'])->middleware('web');
        UserPoint::creating(fn () => throw new RuntimeException('Award unavailable'));
        $this->withoutExceptionHandling();
        try {
            $this->post('/test/legacy-fanfiction', ['title' => 'Rollback', 'author_type' => 'member', 'user_id' => $author->id,
                'author_name' => $author->name, 'content' => 'Eine Geschichte mit fehlgeschlagener Buchung.', 'status' => 'published']);
            $this->fail('Expected publication failure.');
        } catch (RuntimeException $error) {
            $this->assertSame('Award unavailable', $error->getMessage());
            $this->assertDatabaseCount('fanfictions', 0);
            $this->assertDatabaseCount('user_points', 0);
        }
    }

    public function test_publication_invalidates_the_count_only_after_commit(): void
    {
        $author = $this->actingMember();
        $story = Fanfiction::factory()->draft()->create(['team_id' => Team::membersTeam()->id, 'user_id' => $author->id]);
        $key = 'fanfiction_count_'.$story->team_id;
        Cache::put($key, 12);
        try {
            DB::transaction(function () use ($story, $key): void {
                app(PublishFanfiction::class)->execute($story);
                $this->assertSame(12, Cache::get($key));
                throw new RuntimeException('Cancel outer transaction');
            });
        } catch (RuntimeException $error) {
            $this->assertSame('Cancel outer transaction', $error->getMessage());
        }
        $this->assertSame(12, Cache::get($key));
        $this->assertSame(FanfictionStatus::Draft, $story->fresh()->status);
        $this->assertDatabaseCount('user_points', 0);
        app(PublishFanfiction::class)->execute($story);
        $this->assertNull(Cache::get($key));
    }

    public static function unfinishedMissions(): array
    {
        return ['outbound' => [5, 'traveling'], 'at destination' => [15, 'in_mission']];
    }

    #[DataProvider('unfinishedMissions')]
    public function test_unfinished_missions_never_book_points(int $elapsedSeconds, string $status): void
    {
        $user = $this->actingMember();
        $started = now()->subSeconds($elapsedSeconds);
        $mission = Mission::create(['user_id' => $user->id, 'name' => 'Unterwegs', 'origin' => 'Waashton', 'destination' => 'Salem',
            'travel_duration' => 10, 'mission_duration' => 10, 'started_at' => $started,
            'arrival_at' => $started->copy()->addSeconds(10), 'mission_ends_at' => $started->copy()->addSeconds(20), 'completed' => false]);
        $this->assertSame($status, app(MissionCompletionService::class)->status($user));
        $this->assertFalse($mission->fresh()->completed);
        $this->assertDatabaseCount('user_points', 0);
    }

    public function test_special_offer_service_rejects_nonpositive_rewards_without_ui_validation(): void
    {
        $admin = $this->actingAdmin();
        try {
            app(BaxxSpecialOfferManager::class)->save($admin, 'rezension',
                ['points' => 0, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => false], null, 'review');
            $this->fail('A nonpositive special offer must not be persisted.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('reviewPoints', $error->errors());
        }
        $this->assertDatabaseCount('review_baxx_special_offers', 0);
    }
}
