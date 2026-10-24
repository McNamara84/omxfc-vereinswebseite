<?php

namespace App\Services\CoverRatings;

use App\Models\BaxxEarningProgress;
use App\Models\BaxxEarningRule;
use App\Models\CoverRating;
use App\Models\Team;
use App\Models\User;
use App\Services\Baxx\BaxxBookingWriter;
use App\Services\Baxx\BaxxProgressStore;
use App\Services\Baxx\BaxxRuleResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

class CoverRatingBaxxService
{
    public const ACTION_KEY = 'cover_rating';

    public function awardForRating(User $user, bool $newUniqueRating): int
    {
        return DB::transaction(function () use ($user, $newUniqueRating): int {
            $lifetimeCount = CoverRating::query()
                ->withTrashed()
                ->where('user_id', $user->id)
                ->count();
            $initialProcessedCount = max(0, $lifetimeCount - ($newUniqueRating ? 1 : 0));
            $progress = $this->lockProgress($user->id, $initialProcessedCount);
            $processedCount = max($initialProcessedCount, max(0, $progress->processed_count));
            $currentCount = max($lifetimeCount, $processedCount);
            $rule = $this->getRule();

            if (! $rule->is_active || $rule->points <= 0) {
                $this->markProcessedCount($progress, $currentCount);

                return 0;
            }

            $everyCount = max(1, $rule->every_count);
            $crossings = intdiv($currentCount, $everyCount) - intdiv($processedCount, $everyCount);

            if ($crossings <= 0) {
                $this->markProcessedCount($progress, $currentCount);

                return 0;
            }

            $membersTeam = Team::membersTeam();

            if (! $membersTeam) {
                Log::critical('Cover-Bewertungs-Baxx konnten nicht vergeben werden: Mitglieder-Team fehlt.', [
                    'user_id' => $user->id,
                    'action_key' => self::ACTION_KEY,
                ]);

                throw new LogicException('Das Mitglieder-Team fehlt. Cover-Bewertungs-Baxx können nicht vergeben werden.');
            }

            $points = $crossings * $rule->points;
            app(BaxxBookingWriter::class)->record($user->id, $membersTeam->id, $points);
            $this->markProcessedCount($progress, $currentCount);

            return $points;
        });
    }

    /**
     * @return array{lifetime_count: int, every_count: int, completed_in_step: int, remaining: int, points: int, is_active: bool}
     */
    public function progress(User $user): array
    {
        $rule = $this->getRule();
        $everyCount = max(1, $rule->every_count);
        $lifetimeCount = CoverRating::query()
            ->withTrashed()
            ->where('user_id', $user->id)
            ->count();
        $completedInStep = $lifetimeCount % $everyCount;

        return [
            'lifetime_count' => $lifetimeCount,
            'every_count' => $everyCount,
            'completed_in_step' => $completedInStep,
            'remaining' => $completedInStep === 0 && $lifetimeCount > 0
                ? $everyCount
                : $everyCount - $completedInStep,
            'points' => (int) $rule->points,
            'is_active' => (bool) $rule->is_active && $rule->points > 0,
        ];
    }

    public function getRule(): BaxxEarningRule
    {
        return app(BaxxRuleResolver::class)->base(self::ACTION_KEY);
    }

    private function lockProgress(int $userId, int $initialProcessedCount): BaxxEarningProgress
    {
        return app(BaxxProgressStore::class)->lock($userId, self::ACTION_KEY, $initialProcessedCount);
    }

    private function markProcessedCount(BaxxEarningProgress $progress, int $processedCount): void
    {
        app(BaxxProgressStore::class)->advance($progress, $processedCount);
    }
}
