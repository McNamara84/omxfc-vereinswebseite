<?php

namespace App\Services\Romantausch;

use App\Models\BaxxEarningProgress;
use App\Models\BaxxEarningRule;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\Team;
use App\Models\UserPoint;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

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

        return DB::transaction(function () use ($userId, $actionKey, $newActionCount, $resolveTotalCount): int {
            $resolvedCount = max(0, (int) $resolveTotalCount());
            $initialProcessedCount = max(0, $resolvedCount - $newActionCount);
            $progress = $this->lockProgress($userId, $actionKey, $initialProcessedCount);
            $processedCount = max($initialProcessedCount, max(0, $progress->processed_count));
            $currentCount = max($processedCount + $newActionCount, $resolvedCount);
            $rule = BaxxEarningRule::query()
                ->where('action_key', $actionKey)
                ->first();

            if (! $rule || ! $rule->is_active || $rule->points <= 0) {
                $this->markProcessedCount($progress, $currentCount);

                return 0;
            }

            $everyCount = max(1, $rule->every_count);
            $thresholdCrossings = intdiv($currentCount, $everyCount) - intdiv($processedCount, $everyCount);

            if ($thresholdCrossings <= 0) {
                $this->markProcessedCount($progress, $currentCount);

                return 0;
            }

            $walletTeam = $this->resolveMembersWalletTeam();
            $awardedPoints = $thresholdCrossings * $rule->points;

            UserPoint::query()->create([
                'user_id' => $userId,
                'team_id' => $walletTeam->id,
                'points' => $awardedPoints,
            ]);

            $this->markProcessedCount($progress, $currentCount);

            return $awardedPoints;
        });
    }

    private function lockProgress(int $userId, string $actionKey, int $initialProcessedCount): BaxxEarningProgress
    {
        $timestamp = now();

        BaxxEarningProgress::query()->upsert(
            [[
                'user_id' => $userId,
                'action_key' => $actionKey,
                'processed_count' => $initialProcessedCount,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]],
            ['user_id', 'action_key'],
            ['updated_at']
        );

        return BaxxEarningProgress::query()
            ->where('user_id', $userId)
            ->where('action_key', $actionKey)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function markProcessedCount(BaxxEarningProgress $progress, int $processedCount): void
    {
        if ($processedCount <= $progress->processed_count) {
            return;
        }

        $progress->update([
            'processed_count' => $processedCount,
        ]);
    }

    private function resolveMembersWalletTeam(): Team
    {
        $walletTeam = Team::membersTeam();

        if ($walletTeam) {
            return $walletTeam;
        }

        Log::critical('Romantausch-Baxx konnten nicht vergeben werden, weil das Mitglieder-Team fehlt.');

        throw new LogicException('Das Mitglieder-Team fehlt. Romantausch-Baxx können nicht vergeben werden.');
    }
}