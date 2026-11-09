<?php

declare(strict_types=1);

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
use RuntimeException;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);
dataset('BaxxBoundaryTest.legacyPublications', static fn (): array => ['draft member' => ['member', 'draft', 0], 'published member' => ['member', 'published', 11], 'published guest' => ['external', 'published', 0]]);
dataset('BaxxBoundaryTest.unfinishedMissions', static fn (): array => ['outbound' => [5, 'traveling'], 'at destination' => [15, 'in_mission']]);
test('rule adapters return current models and expired offers disappear', function (): void {
    $review = app(ReviewBaxxService::class);
    $mission = app(MaddraxiversumBaxxService::class);
    $swap = app(RomantauschBaxxService::class);
    expect($review->getBaseRule()->action_key)->toBe('rezension')
        ->and($mission->getBaseRule()->action_key)->toBe('maddraxiversum_mission');
    $data = ['points' => 7, 'every_count' => 1, 'is_active' => true, 'ends_at' => now()->addMinute()];
    $reviewOffer = ReviewBaxxSpecialOffer::create($data);
    $missionOffer = MaddraxiversumBaxxSpecialOffer::create($data);
    $swapOffer = RomantauschBaxxSpecialOffer::create($data + ['action_key' => 'romantausch_offer']);
    expect($review->getActiveSpecialOffer()->is($reviewOffer))->toBeTrue()
        ->and($mission->getActiveSpecialOffer()->is($missionOffer))->toBeTrue()
        ->and($swap->getActiveSpecialOffer('romantausch_offer')->is($swapOffer))->toBeTrue()
        ->and($swap->getActiveSpecialOffer('romantausch_request'))->toBeNull();
    $this->travel(1)->minutes();
    expect($review->getActiveSpecialOffer())->toBeNull()
        ->and($mission->getActiveSpecialOffer())->toBeNull()
        ->and($swap->getActiveSpecialOffer('romantausch_offer'))->toBeNull();
});
test('review without a wallet does not book but explicit team still works', function (): void {
    $user = User::factory()->create();
    $user->setRelation('currentTeam', null);
    $review = app(ReviewBaxxService::class);
    $review->getBaseRule()->update(['points' => 4, 'every_count' => 1]);
    expect($review->awardPointsForReview($user, 1))->toBe(0);
    $this->assertDatabaseCount('user_points', 0);
    expect($review->awardPointsForReview($user, 1, Team::membersTeam()->id))->toBe(4);
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'team_id' => Team::membersTeam()->id, 'points' => 4]);
});
test('empty romantausch batches do not create progress or points', function (): void {
    $user = $this->actingMember();
    $swap = app(RomantauschBaxxService::class);
    expect($swap->awardForNewOffers($user->id, 0))->toBe(0)
        ->and($swap->awardForNewRequests($user->id, -1))->toBe(0);
    $this->assertDatabaseCount('user_points', 0);
    $this->assertDatabaseCount('baxx_earning_progress', 0);
});
test('disabled cover reward advances progress without backpay after activation', function (): void {
    $user = $this->actingMember();
    CoverRating::factory()->for($user)->create();
    $service = app(CoverRatingBaxxService::class);
    $service->getRule()->update(['is_active' => false, 'every_count' => 1, 'points' => 3]);
    expect($service->awardForRating($user, true))->toBe(0)
        ->and(BaxxEarningProgress::firstOrFail()->processed_count)->toBe(1);
    $service->getRule()->update(['is_active' => true]);
    expect($service->awardForRating($user, false))->toBe(0);
    $this->assertDatabaseCount('user_points', 0);
});
test('missing members team rolls back cover progress instead of using another wallet', function (): void {
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
});
test('missing members team prevents maddraxikon awards', function (): void {
    Team::membersTeam()->update(['name' => 'Kein Mitglieder-Team']);
    expect(fn () => app(MaddraxikonRewardService::class)->evaluate(force: true))->toThrow(LogicException::class, 'Das Mitglieder-Team fehlt');
});
test('todo form redirects foreign team challenges without loading their fields', function (): void {
    $admin = $this->actingAdmin();
    $todo = Todo::create(['team_id' => Team::factory()->create()->id, 'created_by' => $admin->id, 'title' => 'Fremde Challenge', 'points' => 4, 'status' => TodoStatus::Open, 'category_id' => TodoCategory::create(['name' => 'Test', 'slug' => 'test'])->id]);
    Livewire::test(TodoForm::class, ['todo' => $todo])->assertRedirect(route('todos.index'))->assertSet('todoId', null);
});
test('review form requires an existing book or review', function (): void {
    $this->actingMember();
    Livewire::test(RezensionForm::class)->assertNotFound();
});
test('user wallet helpers follow purchase and refund', function (): void {
    $user = $this->actingMemberWithPoints(20);
    $admin = $this->createUserWithRole(Role::Admin);
    $reward = Reward::factory()->create(['cost_baxx' => 5]);
    $purchase = app(RewardService::class)->purchaseReward($user, $reward);
    expect($user->hasUnlockedReward($reward->slug))->toBeTrue()
        ->and($user->getSpentBaxx())->toBe(5)
        ->and($user->rewardPurchases()->count())->toBe(1)
        ->and($user->activeRewardPurchases()->count())->toBe(1);
    app(RewardService::class)->refundPurchase($purchase, $admin);
    expect($user->hasUnlockedReward($reward->slug))->toBeFalse()
        ->and($user->getSpentBaxx())->toBe(0)
        ->and($user->rewardPurchases()->count())->toBe(1)
        ->and($user->activeRewardPurchases()->count())->toBe(0);
});
test('retained controller uses the same publication contract', function (string $type, string $status, int $points): void {
    $this->actingVorstand();
    $author = $this->createUserWithRole(Role::Mitglied);
    BaxxEarningRule::where('action_key', 'fanfiction_publish')->update(['points' => 11]);
    // The retained controller is an adapter; production creation routes use Livewire.
    Route::post('/test/legacy-fanfiction', [FanfictionAdminController::class, 'store'])->middleware('web');
    $this->post('/test/legacy-fanfiction', ['title' => 'Controllergeschichte', 'author_type' => $type, 'user_id' => $author->id, 'author_name' => $author->name, 'content' => 'Eine Geschichte aus dem alten Einstieg.', 'status' => $status])->assertRedirect(route('admin.fanfiction.index'))->assertSessionHasNoErrors();
    $story = Fanfiction::firstOrFail();
    expect($story->status)->toBe(FanfictionStatus::from($status))
        ->and((int) UserPoint::sum('points'))->toBe($points)
        ->and($story->reward()->exists())->toBe($status === 'published');
})->with('BaxxBoundaryTest.legacyPublications');
test('retained controller rolls back story creation when publication fails', function (): void {
    $this->actingVorstand();
    $author = $this->createUserWithRole(Role::Mitglied);
    Route::post('/test/legacy-fanfiction', [FanfictionAdminController::class, 'store'])->middleware('web');
    UserPoint::creating(fn () => throw new RuntimeException('Award unavailable'));
    $this->withoutExceptionHandling();
    try {
        $this->post('/test/legacy-fanfiction', ['title' => 'Rollback', 'author_type' => 'member', 'user_id' => $author->id, 'author_name' => $author->name, 'content' => 'Eine Geschichte mit fehlgeschlagener Buchung.', 'status' => 'published']);
        $this->fail('Expected publication failure.');
    } catch (RuntimeException $error) {
        expect($error->getMessage())->toBe('Award unavailable');
        $this->assertDatabaseCount('fanfictions', 0);
        $this->assertDatabaseCount('user_points', 0);
    }
});
test('publication invalidates the count only after commit', function (): void {
    $author = $this->actingMember();
    $story = Fanfiction::factory()->draft()->create(['team_id' => Team::membersTeam()->id, 'user_id' => $author->id]);
    $key = 'fanfiction_count_'.$story->team_id;
    Cache::put($key, 12);
    try {
        DB::transaction(function () use ($story, $key): void {
            app(PublishFanfiction::class)->execute($story);
            expect(Cache::get($key))->toBe(12);
            throw new RuntimeException('Cancel outer transaction');
        });
    } catch (RuntimeException $error) {
        expect($error->getMessage())->toBe('Cancel outer transaction');
    }
    expect(Cache::get($key))->toBe(12)
        ->and($story->fresh()->status)->toBe(FanfictionStatus::Draft);
    $this->assertDatabaseCount('user_points', 0);
    app(PublishFanfiction::class)->execute($story);
    expect(Cache::get($key))->toBeNull();
});
test('unfinished missions never book points', function (int $elapsedSeconds, string $status): void {
    $user = $this->actingMember();
    $started = now()->subSeconds($elapsedSeconds);
    $mission = Mission::create(['user_id' => $user->id, 'name' => 'Unterwegs', 'origin' => 'Waashton', 'destination' => 'Salem', 'travel_duration' => 10, 'mission_duration' => 10, 'started_at' => $started, 'arrival_at' => $started->copy()->addSeconds(10), 'mission_ends_at' => $started->copy()->addSeconds(20), 'completed' => false]);
    expect(app(MissionCompletionService::class)->status($user))->toBe($status)
        ->and($mission->fresh()->completed)->toBeFalse();
    $this->assertDatabaseCount('user_points', 0);
})->with('BaxxBoundaryTest.unfinishedMissions');
test('special offer service rejects nonpositive rewards without ui validation', function (): void {
    $admin = $this->actingAdmin();
    try {
        app(BaxxSpecialOfferManager::class)->save($admin, 'rezension', ['points' => 0, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => false], null, 'review');
        $this->fail('A nonpositive special offer must not be persisted.');
    } catch (ValidationException $error) {
        expect($error->errors())->toHaveKey('reviewPoints');
    }
    $this->assertDatabaseCount('review_baxx_special_offers', 0);
});
