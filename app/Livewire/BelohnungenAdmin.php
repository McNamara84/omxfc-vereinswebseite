<?php

namespace App\Livewire;

use App\Models\BaxxEarningRule;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Services\RewardService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class BelohnungenAdmin extends Component
{
    #[Url(except: 'rewards')]
    public string $activeTab = 'rewards';

    // --- Reward editing ---
    public bool $showRewardModal = false;
    public ?int $editingRewardId = null;
    public string $rewardTitle = '';
    public string $rewardDescription = '';
    public string $rewardCategory = '';
    public int $rewardCostBaxx = 1;
    public int $rewardSortOrder = 0;
    public bool $rewardIsActive = true;

    // --- Purchase filter ---
    public string $purchaseSearch = '';
    public string $purchaseRewardFilter = 'alle';

    // --- Earning rule editing ---
    public bool $showRuleModal = false;
    public ?int $editingRuleId = null;
    public string $ruleLabel = '';
    public string $ruleDescription = '';
    public int $rulePoints = 1;
    public bool $ruleIsActive = true;

    #[Computed]
    public function rewards(): \Illuminate\Database\Eloquent\Collection
    {
        return Reward::orderBy('sort_order')
            ->orderBy('cost_baxx')
            ->withCount(['activePurchases as purchase_count'])
            ->get();
    }

    #[Computed]
    public function earningRules(): \Illuminate\Database\Eloquent\Collection
    {
        return BaxxEarningRule::orderBy('action_key')->get();
    }

    #[Computed]
    public function purchases(): \Illuminate\Support\Collection
    {
        $query = RewardPurchase::with(['user', 'reward', 'refundedByUser'])
            ->latest('purchased_at');

        if ($this->purchaseSearch !== '') {
            $search = $this->purchaseSearch;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if ($this->purchaseRewardFilter !== 'alle') {
            $query->where('reward_id', $this->purchaseRewardFilter);
        }

        return $query->limit(100)->get();
    }

    #[Computed]
    public function statistics(): array
    {
        return app(RewardService::class)->getAdminStatistics();
    }

    #[Computed]
    public function categories(): array
    {
        return Reward::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->toArray();
    }

    // =====================================================
    // Reward CRUD
    // =====================================================

    public function openCreateReward(): void
    {
        $this->resetRewardForm();
        $this->showRewardModal = true;
    }

    public function openEditReward(int $rewardId): void
    {
        $reward = Reward::findOrFail($rewardId);
        $this->editingRewardId = $reward->id;
        $this->rewardTitle = $reward->title;
        $this->rewardDescription = $reward->description;
        $this->rewardCategory = $reward->category;
        $this->rewardCostBaxx = $reward->cost_baxx;
        $this->rewardSortOrder = $reward->sort_order;
        $this->rewardIsActive = $reward->is_active;
        $this->showRewardModal = true;
    }

    public function saveReward(): void
    {
        $this->validate([
            'rewardTitle' => 'required|string|max:255',
            'rewardDescription' => 'required|string',
            'rewardCategory' => 'required|string|max:255',
            'rewardCostBaxx' => 'required|integer|min:1',
            'rewardSortOrder' => 'required|integer|min:0',
        ]);

        $data = [
            'title' => $this->rewardTitle,
            'description' => $this->rewardDescription,
            'category' => $this->rewardCategory,
            'cost_baxx' => $this->rewardCostBaxx,
            'sort_order' => $this->rewardSortOrder,
            'is_active' => $this->rewardIsActive,
        ];

        if ($this->editingRewardId) {
            $reward = Reward::findOrFail($this->editingRewardId);
            $reward->update($data);
            $this->dispatch('toast', type: 'success', title: 'Belohnung aktualisiert');
        } else {
            $data['slug'] = Str::slug($this->rewardTitle);
            Reward::create($data);
            $this->dispatch('toast', type: 'success', title: 'Belohnung erstellt');
        }

        $this->showRewardModal = false;
        $this->resetRewardForm();
        unset($this->rewards, $this->statistics);
    }

    public function toggleRewardActive(int $rewardId): void
    {
        $reward = Reward::findOrFail($rewardId);
        $reward->update(['is_active' => ! $reward->is_active]);
        $status = $reward->is_active ? 'aktiviert' : 'deaktiviert';
        $this->dispatch('toast', type: 'success', title: "Belohnung {$status}");
        unset($this->rewards, $this->statistics);
    }

    private function resetRewardForm(): void
    {
        $this->editingRewardId = null;
        $this->rewardTitle = '';
        $this->rewardDescription = '';
        $this->rewardCategory = '';
        $this->rewardCostBaxx = 1;
        $this->rewardSortOrder = 0;
        $this->rewardIsActive = true;
    }

    // =====================================================
    // Earning Rules
    // =====================================================

    public function openEditRule(int $ruleId): void
    {
        $rule = BaxxEarningRule::findOrFail($ruleId);
        $this->editingRuleId = $rule->id;
        $this->ruleLabel = $rule->label;
        $this->ruleDescription = $rule->description ?? '';
        $this->rulePoints = $rule->points;
        $this->ruleIsActive = $rule->is_active;
        $this->showRuleModal = true;
    }

    public function saveRule(): void
    {
        $this->validate([
            'ruleLabel' => 'required|string|max:255',
            'rulePoints' => 'required|integer|min:0',
        ]);

        if ($this->editingRuleId) {
            $rule = BaxxEarningRule::findOrFail($this->editingRuleId);
            $rule->update([
                'label' => $this->ruleLabel,
                'description' => $this->ruleDescription ?: null,
                'points' => $this->rulePoints,
                'is_active' => $this->ruleIsActive,
            ]);
            $this->dispatch('toast', type: 'success', title: 'Vergaberegel aktualisiert');
        }

        $this->showRuleModal = false;
        $this->editingRuleId = null;
        unset($this->earningRules);
    }

    public function toggleRuleActive(int $ruleId): void
    {
        $rule = BaxxEarningRule::findOrFail($ruleId);
        $rule->update(['is_active' => ! $rule->is_active]);
        $status = $rule->is_active ? 'aktiviert' : 'deaktiviert';
        $this->dispatch('toast', type: 'success', title: "Vergaberegel {$status}");
        unset($this->earningRules);
    }

    // =====================================================
    // Purchase Refunds
    // =====================================================

    public function refundPurchase(int $purchaseId): void
    {
        $purchase = RewardPurchase::with(['user', 'reward'])->findOrFail($purchaseId);
        $service = app(RewardService::class);

        try {
            $service->refundPurchase($purchase, Auth::user());
            $this->dispatch('toast', type: 'success', title: 'Erstattung durchgeführt', description: "{$purchase->user->name} erhält {$purchase->cost_baxx} Baxx zurück.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            $message = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', type: 'error', title: 'Fehler', description: $message);
        }

        unset($this->purchases, $this->statistics, $this->rewards);
    }

    public function updatedPurchaseSearch(): void
    {
        unset($this->purchases);
    }

    public function updatedPurchaseRewardFilter(): void
    {
        unset($this->purchases);
    }

    public function render()
    {
        return view('livewire.belohnungen-admin')
            ->layout('layouts.app', ['title' => 'Belohnungen - Admin']);
    }
}
