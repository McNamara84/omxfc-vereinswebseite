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

    /** @var list<int> */
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
            ['baseline' => $baselineEarnedBaxx, 'current' => $currentEarnedBaxx] = $this->earnedBaxxSnapshot($userPoint, $membersTeam->id);
            $progress = $this->lockProgress($userPoint->user_id, $baselineEarnedBaxx);
            $processedEarnedBaxx = max($baselineEarnedBaxx, (int) $progress->processed_count);
            $effectiveMilestoneFloor = $processedEarnedBaxx;
            $existingMilestoneActions = [];

            if ((int) $progress->processed_count > $baselineEarnedBaxx) {
                $effectiveMilestoneFloor = $baselineEarnedBaxx;
                $existingMilestoneActions = $this->existingMilestoneActions($userPoint->user_id);
            }

            foreach (self::MILESTONES as $milestone) {
                $action = $this->milestoneAction($milestone);

                if ($milestone <= $effectiveMilestoneFloor || $milestone > $currentEarnedBaxx || isset($existingMilestoneActions[$action])) {
                    continue;
                }

                Activity::query()->create([
                    'user_id' => $userPoint->user_id,
                    'subject_type' => User::class,
                    'subject_id' => $userPoint->user_id,
                    'action' => $action,
                ]);

                $existingMilestoneActions[$action] = true;
            }

            $this->markProcessedCount($progress, $currentEarnedBaxx);
        });
    }

    /**
     * @return array{baseline: int, current: int}
     */
    private function earnedBaxxSnapshot(UserPoint $userPoint, int $membersTeamId): array
    {
        $createdAt = $userPoint->getRawOriginal('created_at') ?? $userPoint->created_at;

        $snapshot = UserPoint::query()
            ->where('user_id', $userPoint->user_id)
            ->where('team_id', $membersTeamId)
            ->selectRaw('COALESCE(SUM(points), 0) as current_earned_baxx')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN created_at < ? OR (created_at = ? AND id < ?) THEN points ELSE 0 END), 0) as baseline_earned_baxx',
                [$createdAt, $createdAt, $userPoint->id]
            )
            ->toBase()
            ->first();

        return [
            'baseline' => (int) ($snapshot?->baseline_earned_baxx ?? 0),
            'current' => (int) ($snapshot?->current_earned_baxx ?? 0),
        ];
    }

    private function lockProgress(int $userId, int $initialProcessedCount): BaxxEarningProgress
    {
        $progress = BaxxEarningProgress::query()
            ->where('user_id', $userId)
            ->where('action_key', self::ACTION_KEY)
            ->lockForUpdate()
            ->first();

        if ($progress) {
            return $progress;
        }

        $timestamp = now();

        BaxxEarningProgress::query()->insertOrIgnore(
            [
                'user_id' => $userId,
                'action_key' => self::ACTION_KEY,
                'processed_count' => $initialProcessedCount,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]
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

    /**
     * @return array<string, true>
     */
    private function existingMilestoneActions(int $userId): array
    {
        /** @var list<string> $milestoneActions */
        $milestoneActions = collect(self::MILESTONES)
            ->map(fn (int $milestone): string => $this->milestoneAction($milestone))
            ->all();

        if ($milestoneActions === []) {
            return [];
        }

        return DB::table((new Activity)->getTable())
            ->where('subject_type', User::class)
            ->where('subject_id', $userId)
            ->whereIn('action', $milestoneActions)
            ->pluck('action')
            ->mapWithKeys(fn (string $action): array => [$action => true])
            ->all();
    }
}