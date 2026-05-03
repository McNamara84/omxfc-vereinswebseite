<?php

namespace App\Services;

use App\Models\Fanfiction;
use App\Models\RewardPurchase;
use App\Models\User;
use Illuminate\Support\Collection;

class FanfictionAccessService
{
    public function __construct(
        private readonly RewardService $rewardService,
    ) {}

    public function isOwnContribution(User $user, Fanfiction $fanfiction): bool
    {
        return $fanfiction->user_id === $user->id;
    }

    public function hasUnlocked(User $user, Fanfiction $fanfiction, ?array $unlockedRewardIds = null): bool
    {
        if ($this->isOwnContribution($user, $fanfiction)) {
            return true;
        }

        if (! $fanfiction->reward_id) {
            return true;
        }

        if (is_array($unlockedRewardIds)) {
            return in_array($fanfiction->reward_id, $unlockedRewardIds, true);
        }

        return $this->rewardService->hasUnlockedRewardId($user, $fanfiction->reward_id);
    }

    /**
     * @param  Collection<int, Fanfiction>  $fanfictions
     * @param  array<int>|null  $unlockedRewardIds
     * @return array<int>
     */
    public function getUnlockedFanfictionIds(User $user, Collection $fanfictions, ?array $unlockedRewardIds = null): array
    {
        $resolvedRewardIds = $unlockedRewardIds ?? $this->rewardService->getUnlockedRewardIds($user);

        return $fanfictions
            ->filter(fn (Fanfiction $fanfiction) => $this->hasUnlocked($user, $fanfiction, $resolvedRewardIds))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function refundOwnPurchases(User $user): int
    {
        $purchaseIds = RewardPurchase::query()
            ->where('user_id', $user->id)
            ->active()
            ->whereHas('reward.fanfiction', fn ($query) => $query->where('user_id', $user->id))
            ->pluck('id');

        if ($purchaseIds->isEmpty()) {
            return 0;
        }

        return RewardPurchase::query()
            ->whereIn('id', $purchaseIds)
            ->update([
                'refunded_at' => now(),
                'refunded_by' => null,
            ]);
    }
}