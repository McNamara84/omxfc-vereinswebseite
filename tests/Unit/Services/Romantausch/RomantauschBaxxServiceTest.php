<?php

namespace Tests\Unit\Services\Romantausch;

use App\Models\BaxxEarningRule;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\Team;
use App\Models\User;
use App\Services\Romantausch\RomantauschBaxxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RomantauschBaxxServiceTest extends TestCase
{
    use RefreshDatabase;

    private RomantauschBaxxService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RomantauschBaxxService::class);
    }

    public function test_new_offers_are_awarded_in_members_wallet_even_if_current_team_differs(): void
    {
        $membersTeam = Team::membersTeam();
        $otherTeam = Team::factory()->create();
        $user = $this->createMemberWithOtherCurrentTeam($membersTeam, $otherTeam);

        BaxxEarningRule::where('action_key', 'romantausch_offer')->update([
            'points' => 4,
            'every_count' => 2,
            'is_active' => true,
        ]);

        $this->createOffer($user, 1);
        $this->createOffer($user, 2);

        $awardedPoints = $this->service->awardForNewOffers($user->id, 1);

        $this->assertSame(4, $awardedPoints);
        $this->assertDatabaseHas('user_points', [
            'user_id' => $user->id,
            'team_id' => $membersTeam->id,
            'points' => 4,
        ]);
        $this->assertDatabaseMissing('user_points', [
            'user_id' => $user->id,
            'team_id' => $otherTeam->id,
            'points' => 4,
        ]);
    }

    public function test_bundle_offer_counts_each_new_offer_for_thresholds(): void
    {
        $membersTeam = Team::membersTeam();
        $otherTeam = Team::factory()->create();
        $user = $this->createMemberWithOtherCurrentTeam($membersTeam, $otherTeam);

        BaxxEarningRule::where('action_key', 'romantausch_offer')->update([
            'points' => 5,
            'every_count' => 3,
            'is_active' => true,
        ]);

        $this->createOffer($user, 1);
        $this->createOffer($user, 2);
        $this->createOffer($user, 3);
        $this->createOffer($user, 4);

        $awardedPoints = $this->service->awardForNewOffers($user->id, 2);

        $this->assertSame(5, $awardedPoints);
    }

    public function test_new_requests_use_the_configured_rule(): void
    {
        $membersTeam = Team::membersTeam();
        $otherTeam = Team::factory()->create();
        $user = $this->createMemberWithOtherCurrentTeam($membersTeam, $otherTeam);

        BaxxEarningRule::where('action_key', 'romantausch_request')->update([
            'points' => 3,
            'every_count' => 1,
            'is_active' => true,
        ]);

        $this->createRequest($user, 7);

        $awardedPoints = $this->service->awardForNewRequests($user->id, 1);

        $this->assertSame(3, $awardedPoints);
        $this->assertDatabaseHas('user_points', [
            'user_id' => $user->id,
            'team_id' => $membersTeam->id,
            'points' => 3,
        ]);
    }

    public function test_completed_swap_rewards_both_participants(): void
    {
        $membersTeam = Team::membersTeam();
        $otherTeam = Team::factory()->create();
        $offerUser = $this->createMemberWithOtherCurrentTeam($membersTeam, $otherTeam);
        $requestUser = $this->createMemberWithOtherCurrentTeam($membersTeam, $otherTeam);

        BaxxEarningRule::where('action_key', 'romantausch_swap_complete')->update([
            'points' => 2,
            'every_count' => 1,
            'is_active' => true,
        ]);

        $offer = $this->createOffer($offerUser, 21);
        $request = $this->createRequest($requestUser, 21);
        $swap = BookSwap::create([
            'offer_id' => $offer->id,
            'request_id' => $request->id,
            'completed_at' => now(),
        ]);

        $awardedPoints = $this->service->awardForCompletedSwap($swap);

        $this->assertSame([
            'offer_user_points' => 2,
            'request_user_points' => 2,
        ], $awardedPoints);

        $this->assertDatabaseHas('user_points', [
            'user_id' => $offerUser->id,
            'team_id' => $membersTeam->id,
            'points' => 2,
        ]);
        $this->assertDatabaseHas('user_points', [
            'user_id' => $requestUser->id,
            'team_id' => $membersTeam->id,
            'points' => 2,
        ]);
    }

    private function createMemberWithOtherCurrentTeam(Team $membersTeam, Team $otherTeam): User
    {
        $user = User::factory()->create([
            'current_team_id' => $otherTeam->id,
        ]);

        $membersTeam->users()->attach($user, ['role' => 'Mitglied']);
        $otherTeam->users()->attach($user, ['role' => 'Mitglied']);

        return $user;
    }

    private function createOffer(User $user, int $bookNumber): BookOffer
    {
        return BookOffer::create([
            'user_id' => $user->id,
            'series' => 'Maddrax',
            'book_number' => $bookNumber,
            'book_title' => 'Band '.$bookNumber,
            'condition' => 'Z0',
            'photos' => [],
        ]);
    }

    private function createRequest(User $user, int $bookNumber): BookRequest
    {
        return BookRequest::create([
            'user_id' => $user->id,
            'series' => 'Maddrax',
            'book_number' => $bookNumber,
            'book_title' => 'Band '.$bookNumber,
            'condition' => 'Z0',
        ]);
    }
}