<?php

namespace App\Livewire;

use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Services\RewardService;
use App\Services\TeamPointService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class BelohnungenIndex extends Component
{
    #[Url(except: 'alle')]
    public string $filter = 'alle';

    #[Url(except: 'alle')]
    public string $categoryFilter = 'alle';

    public function mount(): void
    {
        // Nothing to mount — computed properties handle data loading.
    }

    #[Computed]
    public function availableBaxx(): int
    {
        return app(RewardService::class)->getAvailableBaxx(Auth::user());
    }

    #[Computed]
    public function earnedBaxx(): int
    {
        return app(TeamPointService::class)->getUserPoints(Auth::user());
    }

    #[Computed]
    public function spentBaxx(): int
    {
        return app(RewardService::class)->getSpentBaxx(Auth::user());
    }

    #[Computed]
    public function rewards(): array
    {
        $user = Auth::user();
        $purchasedRewardIds = RewardPurchase::where('user_id', $user->id)
            ->active()
            ->pluck('reward_id')
            ->toArray();

        $query = Reward::active()->orderBy('sort_order')->orderBy('cost_baxx');

        if ($this->categoryFilter !== 'alle') {
            $query->byCategory($this->categoryFilter);
        }

        $rewards = $query->get()->map(function (Reward $reward) use ($purchasedRewardIds) {
            $purchased = in_array($reward->id, $purchasedRewardIds);
            $canAfford = $this->availableBaxx >= $reward->cost_baxx;
            $missingBaxx = $canAfford ? 0 : $reward->cost_baxx - $this->availableBaxx;

            return [
                'id' => $reward->id,
                'title' => $reward->title,
                'description' => $reward->description,
                'category' => $reward->category,
                'cost_baxx' => $reward->cost_baxx,
                'purchased' => $purchased,
                'can_afford' => $canAfford,
                'missing_baxx' => $missingBaxx,
            ];
        });

        // Apply status filter
        if ($this->filter === 'freigeschaltet') {
            $rewards = $rewards->where('purchased', true);
        } elseif ($this->filter === 'nicht_freigeschaltet') {
            $rewards = $rewards->where('purchased', false);
        }

        return $rewards->groupBy('category')->toArray();
    }

    #[Computed]
    public function categories(): array
    {
        return Reward::active()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->toArray();
    }

    public function purchase(int $rewardId): void
    {
        $reward = Reward::findOrFail($rewardId);
        $service = app(RewardService::class);

        try {
            $service->purchaseReward(Auth::user(), $reward);
            $this->dispatch('toast', type: 'success', title: e($reward->title).' freigeschaltet!', description: "{$reward->cost_baxx} Baxx wurden abgezogen.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            $message = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', type: 'error', title: 'Fehler', description: $message);
        }

        // Invalidate computed caches (earnedBaxx bleibt – ein Kauf ändert nicht die verdienten Baxx)
        unset($this->availableBaxx, $this->spentBaxx, $this->rewards);
    }

    public function updatedFilter(): void
    {
        unset($this->rewards);
    }

    public function updatedCategoryFilter(): void
    {
        unset($this->rewards);
    }

    public function render()
    {
        return view('livewire.belohnungen-index')
            ->layout('layouts.app', ['title' => 'Belohnungen']);
    }
}
