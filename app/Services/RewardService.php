<?php

namespace App\Services;

use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RewardService
{
    public function __construct(
        private readonly TeamPointService $teamPointService
    ) {}

    /**
     * Purchase a reward for a user.
     *
     * @throws ValidationException
     */
    public function purchaseReward(User $user, Reward $reward): RewardPurchase
    {
        if (! $reward->is_active) {
            throw ValidationException::withMessages([
                'reward' => 'Diese Belohnung ist derzeit nicht verfügbar.',
            ]);
        }

        return DB::transaction(function () use ($user, $reward) {
            // Lock ALL purchases of this user to prevent concurrent double-spending.
            // This ensures getSpentBaxx sees a consistent snapshot and no two
            // concurrent requests can both pass the balance check.
            RewardPurchase::where('user_id', $user->id)
                ->lockForUpdate()
                ->count();

            $existingPurchase = RewardPurchase::where('user_id', $user->id)
                ->where('reward_id', $reward->id)
                ->active()
                ->first();

            if ($existingPurchase) {
                throw ValidationException::withMessages([
                    'reward' => 'Du hast diese Belohnung bereits freigeschaltet.',
                ]);
            }

            $availableBaxx = $this->getAvailableBaxx($user);
            if ($availableBaxx < $reward->cost_baxx) {
                throw ValidationException::withMessages([
                    'reward' => "Du benötigst {$reward->cost_baxx} Baxx, hast aber nur {$availableBaxx} verfügbar.",
                ]);
            }

            return RewardPurchase::create([
                'user_id' => $user->id,
                'reward_id' => $reward->id,
                'cost_baxx' => $reward->cost_baxx,
                'purchased_at' => now(),
            ]);
        });
    }

    /**
     * Refund a reward purchase (admin action).
     * The user gets their Baxx back automatically since available Baxx is calculated.
     */
    public function refundPurchase(RewardPurchase $purchase, User $admin): void
    {
        if ($purchase->isRefunded()) {
            throw ValidationException::withMessages([
                'purchase' => 'Dieser Kauf wurde bereits erstattet.',
            ]);
        }

        $purchase->update([
            'refunded_at' => now(),
            'refunded_by' => $admin->id,
        ]);
    }

    /**
     * Get the available (spendable) Baxx for a user.
     * Available = Earned - Spent (on active, non-refunded purchases).
     */
    public function getAvailableBaxx(User $user): int
    {
        $earnedBaxx = $this->teamPointService->getUserPoints($user);
        $spentBaxx = $this->getSpentBaxx($user);

        return max(0, $earnedBaxx - $spentBaxx);
    }

    /**
     * Get the total Baxx spent by a user on active purchases.
     */
    public function getSpentBaxx(User $user): int
    {
        return (int) RewardPurchase::where('user_id', $user->id)
            ->active()
            ->sum('cost_baxx');
    }

    /**
     * Check if a user has unlocked a specific reward by slug.
     */
    public function hasUnlockedReward(User $user, string $slug): bool
    {
        $reward = Reward::where('slug', $slug)->first();

        if (! $reward) {
            return false;
        }

        return RewardPurchase::where('user_id', $user->id)
            ->where('reward_id', $reward->id)
            ->active()
            ->exists();
    }

    /**
     * Check if a user has unlocked a specific reward by ID.
     *
     * More efficient than hasUnlockedReward() when the Reward model is already loaded,
     * since it skips the extra slug-based lookup.
     */
    public function hasUnlockedRewardId(User $user, int $rewardId): bool
    {
        return RewardPurchase::where('user_id', $user->id)
            ->where('reward_id', $rewardId)
            ->active()
            ->exists();
    }

    /**
     * Get all reward IDs the user has actively purchased (single query).
     *
     * Useful in list views to avoid N+1 queries when checking unlock status
     * for multiple rewards at once.
     *
     * @return array<int>
     */
    public function getUnlockedRewardIds(User $user): array
    {
        return RewardPurchase::where('user_id', $user->id)
            ->active()
            ->pluck('reward_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Ensure the authenticated user has unlocked a reward by slug.
     *
     * @throws AuthorizationException
     */
    public function assertRewardUnlocked(string $rewardSlug): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            throw new AuthorizationException('Nicht authentifiziert.');
        }

        if (! $this->hasUnlockedReward($user, $rewardSlug)) {
            throw new AuthorizationException('Du musst diese Belohnung zuerst unter /belohnungen freischalten.');
        }
    }

    /**
     * Get admin statistics for the rewards system.
     *
     * @return array{
     *     total_spent_baxx: int,
     *     rewards_stats: \Illuminate\Support\Collection,
     *     never_purchased_rewards: \Illuminate\Support\Collection,
     *     recent_purchases: \Illuminate\Support\Collection
     * }
     */
    public function getAdminStatistics(): array
    {
        $totalSpentBaxx = (int) RewardPurchase::active()->sum('cost_baxx');

        $rewardsStats = Reward::query()
            ->withCount(['activePurchases as purchase_count'])
            ->withSum('activePurchases as total_baxx_earned', 'cost_baxx')
            ->orderByDesc('purchase_count')
            ->get();

        $neverPurchased = Reward::query()
            ->whereDoesntHave('activePurchases')
            ->where('is_active', true)
            ->get();

        $recentPurchases = RewardPurchase::with(['user', 'reward', 'refundedByUser'])
            ->latest('purchased_at')
            ->limit(50)
            ->get();

        return [
            'total_spent_baxx' => $totalSpentBaxx,
            'rewards_stats' => $rewardsStats,
            'never_purchased_rewards' => $neverPurchased,
            'recent_purchases' => $recentPurchases,
        ];
    }
}
