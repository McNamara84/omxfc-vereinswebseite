<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\RpgCharacter;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RpgCharacterSlotService
{
    public const SLOT_REWARD_SLUG = 'rpg-character-slot';

    private const SLOT_REWARD_TITLE = 'RPG-Charakter-Speicherplatz';

    public function __construct(
        private readonly RewardService $rewardService
    ) {}

    public function baseSlots(): int
    {
        return 1;
    }

    public function slotCostBaxx(): int
    {
        return max(1, (int) config('rewards.rpg_character_slot_cost_baxx', 5));
    }

    /**
     * @return array{
     *     base_slots:int,
     *     purchased_slots:int,
     *     used_slots:int,
     *     total_slots:int,
     *     free_slots:int,
     *     slot_cost_baxx:int,
     *     available_baxx:int|null,
     *     wallet_warning:string|null,
     *     can_purchase_slot:bool
     * }
     */
    public function summary(User $user): array
    {
        $walletState = $this->rewardService->getWalletState($user);
        $availableBaxx = $walletState['availableBaxx'];
        $purchasedSlots = $this->purchasedSlots($user);
        $usedSlots = $this->usedSlots($user);
        $totalSlots = $this->baseSlots() + $purchasedSlots;
        $freeSlots = max(0, $totalSlots - $usedSlots);
        $slotCost = $this->slotCostBaxx();

        return [
            'base_slots' => $this->baseSlots(),
            'purchased_slots' => $purchasedSlots,
            'used_slots' => $usedSlots,
            'total_slots' => $totalSlots,
            'free_slots' => $freeSlots,
            'slot_cost_baxx' => $slotCost,
            'available_baxx' => is_int($availableBaxx) ? $availableBaxx : null,
            'wallet_warning' => $walletState['warning'],
            'can_purchase_slot' => is_int($availableBaxx) && $availableBaxx >= $slotCost,
        ];
    }

    public function purchasedSlots(User $user): int
    {
        $rewardId = $this->slotRewardId();

        if (! $rewardId) {
            return 0;
        }

        return RewardPurchase::query()
            ->where('user_id', $user->id)
            ->where('reward_id', $rewardId)
            ->active()
            ->count();
    }

    public function usedSlots(User $user): int
    {
        return RpgCharacter::query()
            ->where('user_id', $user->id)
            ->count();
    }

    public function totalSlots(User $user): int
    {
        return $this->baseSlots() + $this->purchasedSlots($user);
    }

    public function freeSlots(User $user): int
    {
        return max(0, $this->totalSlots($user) - $this->usedSlots($user));
    }

    public function canStoreCharacter(User $user): bool
    {
        return $this->freeSlots($user) > 0;
    }

    public function purchaseSlot(User $user): RewardPurchase
    {
        return DB::transaction(function () use ($user) {
            $this->lockUserSlotState($user);

            return $this->createSlotPurchase($user);
        });
    }

    public function ensureFreeSlotForStore(User $user, bool $purchaseIfNeeded = false): ?RewardPurchase
    {
        return DB::transaction(function () use ($user, $purchaseIfNeeded): ?RewardPurchase {
            $this->lockUserSlotState($user);

            if ($this->freeSlots($user) > 0) {
                return null;
            }

            if (! $purchaseIfNeeded) {
                $slotCost = $this->slotCostBaxx();

                throw ValidationException::withMessages([
                    'slot' => "Du hast keinen freien Speicher-Slot. Kaufe einen weiteren Slot für {$slotCost} Baxx, um diesen Charakter zu speichern.",
                ]);
            }

            return $this->createSlotPurchase($user);
        });
    }

    public function resolveSlotReward(): Reward
    {
        $defaults = [
            'title' => self::SLOT_REWARD_TITLE,
            'description' => 'System-Buchung für zusätzliche RPG-Charakter-Speicherplätze.',
            'category' => 'System',
            'slug' => self::SLOT_REWARD_SLUG,
            'cost_baxx' => $this->slotCostBaxx(),
            'is_active' => false,
            'sort_order' => 0,
        ];

        $reward = Reward::query()
            ->where('slug', self::SLOT_REWARD_SLUG)
            ->first();

        if (! $reward) {
            return Reward::query()->create($defaults);
        }

        $updates = [];

        foreach ($defaults as $key => $value) {
            if ($reward->{$key} !== $value) {
                $updates[$key] = $value;
            }
        }

        if ($updates !== []) {
            $reward->update($updates);
        }

        return $reward->fresh();
    }

    private function slotRewardId(): ?int
    {
        $id = Reward::query()
            ->where('slug', self::SLOT_REWARD_SLUG)
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function lockUserSlotState(User $user): void
    {
        User::query()
            ->whereKey($user->id)
            ->lockForUpdate()
            ->firstOrFail();

        RewardPurchase::query()
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->count();

        RpgCharacter::query()
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->count();
    }

    private function createSlotPurchase(User $user): RewardPurchase
    {
        $walletTeam = Team::membersTeam();

        if (! $walletTeam) {
            throw ValidationException::withMessages([
                'slot' => 'Das Mitglieder-Team ist derzeit nicht verfügbar. Speicher-Slots können aktuell nicht gekauft werden.',
            ]);
        }

        $walletState = $this->rewardService->getWalletState($user);
        $availableBaxx = $walletState['availableBaxx'];
        $slotCost = $this->slotCostBaxx();

        if (! is_int($availableBaxx)) {
            throw ValidationException::withMessages([
                'slot' => $walletState['warning'] ?? 'Das Baxx-Guthaben ist aktuell nicht verfügbar.',
            ]);
        }

        if ($availableBaxx < $slotCost) {
            throw ValidationException::withMessages([
                'slot' => "Du benötigst {$slotCost} Baxx, hast aber nur {$availableBaxx} verfügbar.",
            ]);
        }

        $reward = $this->resolveSlotReward();

        $purchase = RewardPurchase::query()->create([
            'user_id' => $user->id,
            'reward_id' => $reward->id,
            'wallet_team_id' => $walletTeam->id,
            'cost_baxx' => $slotCost,
            'purchased_at' => now(),
        ]);

        Activity::query()->create([
            'user_id' => $user->id,
            'subject_type' => RewardPurchase::class,
            'subject_id' => $purchase->id,
            'action' => 'rpg_character_slot_purchased',
        ]);

        return $purchase;
    }
}
