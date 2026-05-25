<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\BaxxEarningProgress;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPoint;
use Illuminate\Support\Facades\DB;

class BaxxMilestoneActivityService
{
    private const ACTION_KEY = 'dashboard_baxx_milestone';

    /**
     * @var array<int, int>
     */
    private const MILESTONES = [1, 25, 100, 250, 500];

    public function recordForUserPoint(int $userPointId): void
    {
        $userPoint = UserPoint::query()->find($userPointId);

        if (! $userPoint) {
            return;
        }

        $membersTeam = Team::membersTeam();

        if (! $membersTeam || $userPoint->team_id !== $membersTeam->id) {
            return;
        }

        DB::transaction(function () use ($userPoint, $membersTeam): void {
            $currentEarnedBaxx = (int) UserPoint::query()
                ->where('user_id', $userPoint->user_id)
                ->where('team_id', $membersTeam->id)
                ->sum('points');

            $baselineEarnedBaxx = $this->baselineEarnedBaxxBefore($userPoint, $membersTeam->id);
            $progress = $this->lockProgress($userPoint->user_id, $baselineEarnedBaxx);
            $processedEarnedBaxx = max($baselineEarnedBaxx, (int) $progress->processed_count);

            if ($currentEarnedBaxx <= $processedEarnedBaxx) {
                return;
            }

            foreach (self::MILESTONES as $milestone) {
                if ($milestone <= $processedEarnedBaxx || $milestone > $currentEarnedBaxx) {
                    continue;
                }

                Activity::query()->create([
                    'user_id' => $userPoint->user_id,
                    'subject_type' => User::class,
                    'subject_id' => $userPoint->user_id,
                    'action' => $this->milestoneAction($milestone),
                ]);
            }

            $this->markProcessedCount($progress, $currentEarnedBaxx);
        });
    }

    private function baselineEarnedBaxxBefore(UserPoint $userPoint, int $membersTeamId): int
    {
        return (int) UserPoint::query()
            ->where('user_id', $userPoint->user_id)
            ->where('team_id', $membersTeamId)
            ->where(function ($query) use ($userPoint) {
                $query->where('created_at', '<', $userPoint->created_at)
                    ->orWhere(function ($sameTimestampQuery) use ($userPoint) {
                        $sameTimestampQuery->where('created_at', $userPoint->created_at)
                            ->where('id', '<', $userPoint->id);
                    });
            })
            ->sum('points');
    }

    private function lockProgress(int $userId, int $initialProcessedCount): BaxxEarningProgress
    {
        $timestamp = now();

        BaxxEarningProgress::query()->upsert(
            [[
                'user_id' => $userId,
                'action_key' => self::ACTION_KEY,
                'processed_count' => $initialProcessedCount,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]],
            ['user_id', 'action_key'],
            ['updated_at']
        );

        return BaxxEarningProgress::query()
            ->where('user_id', $userId)
            ->where('action_key', self::ACTION_KEY)
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

    private function milestoneAction(int $milestone): string
    {
        return 'baxx_milestone_reached_'.$milestone;
    }
}