<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Romantausch;

use App\Models\BookSwap;
use App\Models\RomantauschBaxxSpecialOffer;
use App\Models\Team;
use App\Services\Romantausch\RomantauschBaxxService;
use Illuminate\Support\Facades\Log;
use LogicException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RomantauschBaxxServiceTestFixtures::class);

beforeEach(function (): void {
    $this->service = app(RomantauschBaxxService::class);
});

test('new offers are awarded in members wallet even if current team differs', function (): void {
    $membersTeam = Team::membersTeam();
    $otherTeam = Team::factory()->create();
    $user = $this->createMemberWithOtherCurrentTeam($membersTeam, $otherTeam);
    $this->configureRule('romantausch_offer', ['points' => 4, 'every_count' => 2, 'is_active' => true]);
    $this->createOffer($user, 1);
    $this->createOffer($user, 2);
    $awardedPoints = $this->service->awardForNewOffers($user->id, 1);
    expect($awardedPoints)->toBe(4);
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'team_id' => $membersTeam->id, 'points' => 4]);
    $this->assertDatabaseMissing('user_points', ['user_id' => $user->id, 'team_id' => $otherTeam->id, 'points' => 4]);
});

test('bundle offer counts each new offer for thresholds', function (): void {
    $membersTeam = Team::membersTeam();
    $otherTeam = Team::factory()->create();
    $user = $this->createMemberWithOtherCurrentTeam($membersTeam, $otherTeam);
    $this->configureRule('romantausch_offer', ['points' => 5, 'every_count' => 3, 'is_active' => true]);
    $this->createOffer($user, 1);
    $this->createOffer($user, 2);
    $this->createOffer($user, 3);
    $this->createOffer($user, 4);
    $awardedPoints = $this->service->awardForNewOffers($user->id, 2);
    expect($awardedPoints)->toBe(5);
});

test('new requests use the configured rule', function (): void {
    $membersTeam = Team::membersTeam();
    $otherTeam = Team::factory()->create();
    $user = $this->createMemberWithOtherCurrentTeam($membersTeam, $otherTeam);
    $this->configureRule('romantausch_request', ['points' => 3, 'every_count' => 1, 'is_active' => true]);
    $this->createRequest($user, 7);
    $awardedPoints = $this->service->awardForNewRequests($user->id, 1);
    expect($awardedPoints)->toBe(3);
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'team_id' => $membersTeam->id, 'points' => 3]);
});

test('active special offer overrides request base rule', function (): void {
    $membersTeam = Team::membersTeam();
    $user = $this->createMemberWithOtherCurrentTeam($membersTeam, Team::factory()->create());
    $this->configureRule('romantausch_request', ['points' => 1, 'every_count' => 5, 'is_active' => true]);
    RomantauschBaxxSpecialOffer::create(['action_key' => 'romantausch_request', 'points' => 4, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => true]);
    $this->createRequest($user, 8);
    $effectiveRule = $this->service->getEffectiveRule('romantausch_request');
    $awardedPoints = $this->service->awardForNewRequests($user->id, 1);
    expect($effectiveRule['is_special_offer'])->toBeTrue()
        ->and($effectiveRule['points'])->toBe(4)
        ->and($effectiveRule['every_count'])->toBe(1)
        ->and($awardedPoints)->toBe(4);
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'team_id' => $membersTeam->id, 'points' => 4]);
});

test('expired special offer falls back to offer base rule', function (): void {
    $membersTeam = Team::membersTeam();
    $user = $this->createMemberWithOtherCurrentTeam($membersTeam, Team::factory()->create());
    $this->configureRule('romantausch_offer', ['points' => 6, 'every_count' => 1, 'is_active' => true]);
    RomantauschBaxxSpecialOffer::create(['action_key' => 'romantausch_offer', 'points' => 9, 'every_count' => 1, 'ends_at' => now()->subMinute(), 'is_active' => true]);
    $this->createOffer($user, 15);
    $effectiveRule = $this->service->getEffectiveRule('romantausch_offer');
    $awardedPoints = $this->service->awardForNewOffers($user->id, 1);
    expect($effectiveRule['is_special_offer'])->toBeFalse()
        ->and($effectiveRule['points'])->toBe(6)
        ->and($awardedPoints)->toBe(6);
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'team_id' => $membersTeam->id, 'points' => 6]);
});

test('follow up actions below the next threshold do not award again', function (): void {
    $user = $this->createMemberWithOtherCurrentTeam(Team::membersTeam(), Team::factory()->create());
    $this->configureRule('romantausch_offer', ['points' => 5, 'every_count' => 3, 'is_active' => true]);
    $this->createOffer($user, 11);
    $this->createOffer($user, 12);
    $this->createOffer($user, 13);
    $firstAward = $this->service->awardForNewOffers($user->id, 1);
    $this->createOffer($user, 14);
    $secondAward = $this->service->awardForNewOffers($user->id, 1);
    expect($firstAward)->toBe(5)
        ->and($secondAward)->toBe(0);
    $this->assertDatabaseCount('user_points', 1);
    $this->assertDatabaseHas('baxx_earning_progress', ['user_id' => $user->id, 'action_key' => 'romantausch_offer', 'processed_count' => 4]);
});

test('stale total count after progress advance does not double award', function (): void {
    $user = $this->createMemberWithOtherCurrentTeam(Team::membersTeam(), Team::factory()->create());
    $this->configureRule('romantausch_offer', ['points' => 5, 'every_count' => 3, 'is_active' => true]);
    $this->createOffer($user, 51);
    $this->createOffer($user, 52);
    $this->createOffer($user, 53);
    $firstAward = $this->service->awardForNewOffers($user->id, 1);
    $secondAward = $this->service->awardForNewOffers($user->id, 1);
    expect($firstAward)->toBe(5)
        ->and($secondAward)->toBe(0);
    $this->assertDatabaseCount('user_points', 1);
    $this->assertDatabaseHas('baxx_earning_progress', ['user_id' => $user->id, 'action_key' => 'romantausch_offer', 'processed_count' => 3]);
});

test('existing history does not trigger retroactive threshold awards on first progress entry', function (): void {
    $membersTeam = Team::membersTeam();
    $user = $this->createMemberWithOtherCurrentTeam($membersTeam, Team::factory()->create());
    $this->configureRule('romantausch_offer', ['points' => 5, 'every_count' => 10, 'is_active' => true]);
    foreach (range(1, 21) as $bookNumber) {
        $this->createOffer($user, $bookNumber);
    }
    $awardedPoints = $this->service->awardForNewOffers($user->id, 1);
    expect($awardedPoints)->toBe(0);
    $this->assertDatabaseCount('user_points', 0);
    $this->assertDatabaseHas('baxx_earning_progress', ['user_id' => $user->id, 'action_key' => 'romantausch_offer', 'processed_count' => 21]);
});

test('inactive rules advance progress without retroactive awards after activation', function (): void {
    $user = $this->createMemberWithOtherCurrentTeam(Team::membersTeam(), Team::factory()->create());
    $this->configureRule('romantausch_request', ['points' => 3, 'every_count' => 1, 'is_active' => false]);
    $this->createRequest($user, 31);
    $initialAward = $this->service->awardForNewRequests($user->id, 1);
    expect($initialAward)->toBe(0);
    $this->assertDatabaseHas('baxx_earning_progress', ['user_id' => $user->id, 'action_key' => 'romantausch_request', 'processed_count' => 1]);
    $this->configureRule('romantausch_request', ['points' => 3, 'every_count' => 1, 'is_active' => true]);
    $this->createRequest($user, 32);
    $awardAfterActivation = $this->service->awardForNewRequests($user->id, 1);
    expect($awardAfterActivation)->toBe(3);
    $this->assertDatabaseCount('user_points', 1);
});

test('missing members team logs contextual failure details', function (): void {
    $membersTeam = Team::membersTeam();
    $user = $this->createMemberWithOtherCurrentTeam($membersTeam, Team::factory()->create());
    $this->configureRule('romantausch_offer', ['points' => 2, 'every_count' => 1, 'is_active' => true]);
    $this->createOffer($user, 41);
    $membersTeam?->delete();
    Log::shouldReceive('critical')->once()->with('Romantausch-Baxx konnten nicht vergeben werden, weil das Mitglieder-Team fehlt.', ['user_id' => $user->id, 'action_key' => 'romantausch_offer', 'members_team_id' => null]);
    expect(fn () => $this->service->awardForNewOffers($user->id, 1))->toThrow(LogicException::class, sprintf('Das Mitglieder-Team fehlt. Romantausch-Baxx können nicht vergeben werden (user_id: %d, action_key: %s).', $user->id, 'romantausch_offer'));
});

test('completed swap rewards both participants', function (): void {
    $membersTeam = Team::membersTeam();
    $otherTeam = Team::factory()->create();
    $offerUser = $this->createMemberWithOtherCurrentTeam($membersTeam, $otherTeam);
    $requestUser = $this->createMemberWithOtherCurrentTeam($membersTeam, $otherTeam);
    $this->configureRule('romantausch_swap_complete', ['points' => 2, 'every_count' => 1, 'is_active' => true]);
    $offer = $this->createOffer($offerUser, 21);
    $request = $this->createRequest($requestUser, 21);
    $swap = BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id, 'completed_at' => now()]);
    $awardedPoints = $this->service->awardForCompletedSwap($swap);
    expect($awardedPoints)->toBe(['offer_user_points' => 2, 'request_user_points' => 2]);
    $this->assertDatabaseHas('user_points', ['user_id' => $offerUser->id, 'team_id' => $membersTeam->id, 'points' => 2]);
    $this->assertDatabaseHas('user_points', ['user_id' => $requestUser->id, 'team_id' => $membersTeam->id, 'points' => 2]);
});

test('completed swap special offer overrides base rule for both participants', function (): void {
    $membersTeam = Team::membersTeam();
    $otherTeam = Team::factory()->create();
    $offerUser = $this->createMemberWithOtherCurrentTeam($membersTeam, $otherTeam);
    $requestUser = $this->createMemberWithOtherCurrentTeam($membersTeam, $otherTeam);
    $this->configureRule('romantausch_swap_complete', ['points' => 2, 'every_count' => 1, 'is_active' => true]);
    RomantauschBaxxSpecialOffer::create(['action_key' => 'romantausch_swap_complete', 'points' => 5, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => true]);
    $offer = $this->createOffer($offerUser, 22);
    $request = $this->createRequest($requestUser, 22);
    $swap = BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id, 'completed_at' => now()]);
    $awardedPoints = $this->service->awardForCompletedSwap($swap);
    expect($awardedPoints)->toBe(['offer_user_points' => 5, 'request_user_points' => 5]);
    $this->assertDatabaseHas('user_points', ['user_id' => $offerUser->id, 'team_id' => $membersTeam->id, 'points' => 5]);
    $this->assertDatabaseHas('user_points', ['user_id' => $requestUser->id, 'team_id' => $membersTeam->id, 'points' => 5]);
});
