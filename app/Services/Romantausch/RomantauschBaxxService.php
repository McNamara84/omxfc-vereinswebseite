<?php

namespace App\Services\Romantausch;

use App\Models\BaxxEarningRule;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\Team;
use App\Models\UserPoint;
use Closure;

class RomantauschBaxxService
{
    public function awardForNewOffers(int $userId, int $newOfferCount): int
    {
        return $this->awardByRule(
            userId: $userId,
            actionKey: 'romantausch_offer',
            newActionCount: $newOfferCount,
            resolveTotalCount: fn (): int => (int) BookOffer::query()
                ->where('user_id', $userId)
                ->count(),
        );
    }

    public function awardForNewRequests(int $userId, int $newRequestCount = 1): int
    {
        return $this->awardByRule(
            userId: $userId,
            actionKey: 'romantausch_request',
            newActionCount: $newRequestCount,
            resolveTotalCount: fn (): int => (int) BookRequest::query()
                ->where('user_id', $userId)
                ->count(),
        );
    }

    /**
     * @return array{offer_user_points: int, request_user_points: int}
     */
    public function awardForCompletedSwap(BookSwap $swap): array
    {
        return [
            'offer_user_points' => $this->awardByRule(
                userId: $swap->offer->user_id,
                actionKey: 'romantausch_swap_complete',
                newActionCount: 1,
                resolveTotalCount: fn (): int => $this->completedOfferSwapCount($swap->offer->user_id),
            ),
            'request_user_points' => $this->awardByRule(
                userId: $swap->request->user_id,
                actionKey: 'romantausch_swap_complete',
                newActionCount: 1,
                resolveTotalCount: fn (): int => $this->completedRequestSwapCount($swap->request->user_id),
            ),
        ];
    }

    private function completedOfferSwapCount(int $userId): int
    {
        return (int) BookSwap::query()
            ->whereNotNull('completed_at')
            ->whereHas('offer', fn ($query) => $query->where('user_id', $userId))
            ->count();
    }

    private function completedRequestSwapCount(int $userId): int
    {
        return (int) BookSwap::query()
            ->whereNotNull('completed_at')
            ->whereHas('request', fn ($query) => $query->where('user_id', $userId))
            ->count();
    }

    private function awardByRule(int $userId, string $actionKey, int $newActionCount, Closure $resolveTotalCount): int
    {
        if ($newActionCount < 1) {
            return 0;
        }

        $rule = BaxxEarningRule::getActiveRuleFor($actionKey);

        if (! $rule || $rule->points <= 0) {
            return 0;
        }

        $walletTeam = Team::membersTeam();

        if (! $walletTeam) {
            return 0;
        }

        $totalCount = max(0, (int) $resolveTotalCount());
        $previousCount = max(0, $totalCount - $newActionCount);
        $everyCount = max(1, $rule->every_count);
        $thresholdCrossings = intdiv($totalCount, $everyCount) - intdiv($previousCount, $everyCount);
        $awardedPoints = $thresholdCrossings * $rule->points;

        if ($awardedPoints <= 0) {
            return 0;
        }

        UserPoint::query()->create([
            'user_id' => $userId,
            'team_id' => $walletTeam->id,
            'points' => $awardedPoints,
        ]);

        return $awardedPoints;
    }
}