<?php

namespace Tests\Unit\Services\Romantausch;

use App\Enums\Role;
use App\Models\BaxxEarningRule;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\Team;
use App\Models\User;
use App\Services\Romantausch\RomantauschBaxxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use LogicException;
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

        $this->configureRule('romantausch_offer', [
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

        $this->configureRule('romantausch_offer', [
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

        $this->configureRule('romantausch_request', [
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

    public function test_follow_up_actions_below_the_next_threshold_do_not_award_again(): void
    {
        $user = $this->createMemberWithOtherCurrentTeam(Team::membersTeam(), Team::factory()->create());

        $this->configureRule('romantausch_offer', [
            'points' => 5,
            'every_count' => 3,
            'is_active' => true,
        ]);

        $this->createOffer($user, 11);
        $this->createOffer($user, 12);
        $this->createOffer($user, 13);

        $firstAward = $this->service->awardForNewOffers($user->id, 1);

        $this->createOffer($user, 14);

        $secondAward = $this->service->awardForNewOffers($user->id, 1);

        $this->assertSame(5, $firstAward);
        $this->assertSame(0, $secondAward);
        $this->assertDatabaseCount('user_points', 1);
        $this->assertDatabaseHas('baxx_earning_progress', [
            'user_id' => $user->id,
            'action_key' => 'romantausch_offer',
            'processed_count' => 4,
        ]);
    }

    public function test_existing_history_does_not_trigger_retroactive_threshold_awards_on_first_progress_entry(): void
    {
        $membersTeam = Team::membersTeam();
        $user = $this->createMemberWithOtherCurrentTeam($membersTeam, Team::factory()->create());

        $this->configureRule('romantausch_offer', [
            'points' => 5,
            'every_count' => 10,
            'is_active' => true,
        ]);

        foreach (range(1, 21) as $bookNumber) {
            $this->createOffer($user, $bookNumber);
        }

        $awardedPoints = $this->service->awardForNewOffers($user->id, 1);

        $this->assertSame(0, $awardedPoints);
        $this->assertDatabaseCount('user_points', 0);
        $this->assertDatabaseHas('baxx_earning_progress', [
            'user_id' => $user->id,
            'action_key' => 'romantausch_offer',
            'processed_count' => 21,
        ]);
    }

    public function test_inactive_rules_advance_progress_without_retroactive_awards_after_activation(): void
    {
        $user = $this->createMemberWithOtherCurrentTeam(Team::membersTeam(), Team::factory()->create());

        $this->configureRule('romantausch_request', [
            'points' => 3,
            'every_count' => 1,
            'is_active' => false,
        ]);

        $this->createRequest($user, 31);

        $initialAward = $this->service->awardForNewRequests($user->id, 1);

        $this->assertSame(0, $initialAward);
        $this->assertDatabaseHas('baxx_earning_progress', [
            'user_id' => $user->id,
            'action_key' => 'romantausch_request',
            'processed_count' => 1,
        ]);

        $this->configureRule('romantausch_request', [
            'points' => 3,
            'every_count' => 1,
            'is_active' => true,
        ]);

        $this->createRequest($user, 32);

        $awardAfterActivation = $this->service->awardForNewRequests($user->id, 1);

        $this->assertSame(3, $awardAfterActivation);
        $this->assertDatabaseCount('user_points', 1);
    }

    public function test_missing_members_team_logs_contextual_failure_details(): void
    {
        $membersTeam = Team::membersTeam();
        $user = $this->createMemberWithOtherCurrentTeam($membersTeam, Team::factory()->create());

        $this->configureRule('romantausch_offer', [
            'points' => 2,
            'every_count' => 1,
            'is_active' => true,
        ]);

        $this->createOffer($user, 41);
        $membersTeam?->delete();

        Log::shouldReceive('critical')
            ->once()
            ->with(
                'Romantausch-Baxx konnten nicht vergeben werden, weil das Mitglieder-Team fehlt.',
                [
                    'user_id' => $user->id,
                    'action_key' => 'romantausch_offer',
                    'members_team_id' => null,
                ]
            );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'Das Mitglieder-Team fehlt. Romantausch-Baxx können nicht vergeben werden (user_id: %d, action_key: %s).',
            $user->id,
            'romantausch_offer',
        ));

        $this->service->awardForNewOffers($user->id, 1);
    }

    public function test_completed_swap_rewards_both_participants(): void
    {
        $membersTeam = Team::membersTeam();
        $otherTeam = Team::factory()->create();
        $offerUser = $this->createMemberWithOtherCurrentTeam($membersTeam, $otherTeam);
        $requestUser = $this->createMemberWithOtherCurrentTeam($membersTeam, $otherTeam);

        $this->configureRule('romantausch_swap_complete', [
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

        $membersTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
        $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);

        return $user;
    }

    private function configureRule(string $actionKey, array $attributes): void
    {
        BaxxEarningRule::query()
            ->where('action_key', $actionKey)
            ->firstOrFail()
            ->update($attributes);
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