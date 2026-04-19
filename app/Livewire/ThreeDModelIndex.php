<?php

namespace App\Livewire;

use App\Models\ThreeDModel;
use App\Services\RewardService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ThreeDModelIndex extends Component
{
    #[Computed]
    public function models()
    {
        return ThreeDModel::with('reward')->orderByDesc('created_at')->get();
    }

    #[Computed]
    public function availableBaxx(): int
    {
        return app(RewardService::class)->getAvailableBaxx(Auth::user());
    }

    #[Computed]
    public function unlockedModelIds(): array
    {
        $rewardService = app(RewardService::class);
        $unlockedRewardIds = $rewardService->getUnlockedRewardIds(Auth::user());

        return $this->models
            ->filter(fn ($model) => ! $model->reward || in_array($model->reward_id, $unlockedRewardIds, true))
            ->pluck('id')
            ->toArray();
    }

    public function placeholder()
    {
        return view('components.skeleton-card', ['cols' => 3, 'rows' => 2]);
    }

    public function render()
    {
        return view('livewire.three-d-model-index')
            ->layout('layouts.app', ['title' => '3D-Modelle']);
    }
}
