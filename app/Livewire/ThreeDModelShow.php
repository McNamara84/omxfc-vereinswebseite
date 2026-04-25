<?php

namespace App\Livewire;

use App\Models\ThreeDModel;
use App\Services\RewardService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ThreeDModelShow extends Component
{
    #[Locked]
    public int $threeDModelId;

    public bool $confirmingDelete = false;

    public function mount(ThreeDModel $threeDModel): void
    {
        $this->threeDModelId = $threeDModel->id;
    }

    #[Computed]
    public function model(): ThreeDModel
    {
        return ThreeDModel::with(['uploader', 'reward'])->findOrFail($this->threeDModelId);
    }

    #[Computed]
    public function isUnlocked(): bool
    {
        return ! $this->model->reward
            || app(RewardService::class)->hasUnlockedRewardId(Auth::user(), $this->model->reward->id);
    }

    #[Computed]
    public function availableBaxx(): int
    {
        return app(RewardService::class)->getAvailableBaxx(Auth::user());
    }

    #[Computed]
    public function canManage(): bool
    {
        $user = Auth::user();

        return $user && $user->hasAnyRole(\App\Enums\Role::Admin, \App\Enums\Role::Vorstand);
    }

    public function purchase(): void
    {
        $model = $this->model;

        if (! $model->reward) {
            $this->addError('reward', 'Dieses Modell hat keine zugeordnete Belohnung.');

            return;
        }

        try {
            app(RewardService::class)->purchaseReward(Auth::user(), $model->reward);

            unset($this->isUnlocked);
            unset($this->availableBaxx);

            session()->flash('success', '3D-Modell erfolgreich für '.$model->reward->cost_baxx.' Baxx freigeschaltet!');
            $this->redirect(route('3d-modelle.show', $model), navigate: true);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError($key, $message);
                }
            }
        }
    }

    public function deleteModel(): void
    {
        if (! $this->canManage) {
            abort(403);
        }

        app(\App\Services\ThreeDModelService::class)->deleteModel($this->model);

        session()->flash('toast', ['type' => 'success', 'title' => '3D-Modell erfolgreich gelöscht.']);
        $this->redirect(route('3d-modelle.index'), navigate: true);
    }

    public function placeholder()
    {
        return view('components.skeleton-detail', ['hasImage' => true, 'sections' => 2]);
    }

    public function render()
    {
        return view('livewire.three-d-model-show')
            ->layout('layouts.app', ['title' => $this->model->name]);
    }
}
