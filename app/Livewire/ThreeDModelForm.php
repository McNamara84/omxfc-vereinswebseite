<?php

namespace App\Livewire;

use App\Models\ThreeDModel;
use App\Services\ThreeDModelService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class ThreeDModelForm extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $threeDModelId = null;

    public string $name = '';

    public string $description = '';

    public int $cost_baxx = 10;

    public ?string $maddraxikon_url = null;

    public $model_file = null;

    public $thumbnail = null;

    public function mount(?ThreeDModel $threeDModel = null): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasAnyRole(\App\Enums\Role::Admin, \App\Enums\Role::Vorstand)) {
            abort(403);
        }

        if ($threeDModel?->exists) {
            $this->threeDModelId = $threeDModel->id;
            $this->name = $threeDModel->name;
            $this->description = $threeDModel->description;
            $this->cost_baxx = $threeDModel->reward?->cost_baxx ?? 10;
            $this->maddraxikon_url = $threeDModel->maddraxikon_url;
        }
    }

    #[Computed]
    public function isEditing(): bool
    {
        return $this->threeDModelId !== null;
    }

    #[Computed]
    public function existingModel(): ?ThreeDModel
    {
        return $this->threeDModelId
            ? ThreeDModel::with('reward')->find($this->threeDModelId)
            : null;
    }

    public function save(): void
    {
        $maxSize = ThreeDModelService::MAX_FILE_SIZE_KB;
        $maxThumbSize = ThreeDModelService::MAX_THUMBNAIL_SIZE_KB;
        $extensions = implode(',', ThreeDModelService::ALLOWED_EXTENSIONS);
        $thumbExtensions = implode(',', ThreeDModelService::ALLOWED_THUMBNAIL_EXTENSIONS);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'cost_baxx' => ['required', 'integer', 'min:1', 'max:1000'],
            'maddraxikon_url' => ['nullable', 'url:https', 'max:500'],
            'thumbnail' => ['nullable', 'image', "mimes:{$thumbExtensions}", "max:{$maxThumbSize}"],
        ];

        if ($this->isEditing) {
            $rules['model_file'] = ['nullable', 'file', "extensions:{$extensions}", "max:{$maxSize}"];
        } else {
            $rules['model_file'] = ['required', 'file', "extensions:{$extensions}", "max:{$maxSize}"];
        }

        $this->validate($rules);

        $service = app(ThreeDModelService::class);

        if ($this->isEditing) {
            $model = ThreeDModel::findOrFail($this->threeDModelId);

            $service->updateModel(
                model: $model,
                metadata: [
                    'name' => $this->name,
                    'description' => $this->description,
                    'maddraxikon_url' => $this->maddraxikon_url,
                    'cost_baxx' => $this->cost_baxx,
                ],
                file: $this->model_file,
                thumbnail: $this->thumbnail,
            );

            $message = '3D-Modell erfolgreich aktualisiert.';
        } else {
            $service->storeModel(
                file: $this->model_file,
                metadata: [
                    'name' => $this->name,
                    'description' => $this->description,
                    'maddraxikon_url' => $this->maddraxikon_url,
                    'cost_baxx' => $this->cost_baxx,
                    'uploaded_by' => Auth::id(),
                ],
                thumbnail: $this->thumbnail,
            );

            $message = '3D-Modell erfolgreich hochgeladen.';
        }

        session()->flash('success', $message);
        $this->redirect(route('3d-modelle.index'), navigate: true);
    }

    public function placeholder()
    {
        return view('components.skeleton-form', ['fields' => 5]);
    }

    public function render()
    {
        return view('livewire.three-d-model-form')
            ->layout('layouts.app', ['title' => $this->isEditing ? '3D-Modell bearbeiten' : '3D-Modell hochladen']);
    }
}
