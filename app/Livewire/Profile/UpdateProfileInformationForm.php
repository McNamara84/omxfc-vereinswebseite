<?php

namespace App\Livewire\Profile;

use Livewire\Component;
use Livewire\WithFileUploads;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateProfileInformationForm extends Component
{
    use WithFileUploads;

    public $state = [];
    public $photo;

    public function mount()
    {
        $this->state = auth()->user()->withoutRelations()->toArray();
    }

    public function updateProfileInformation(UpdatesUserProfileInformation $updater)
    {
        $this->resetErrorBag();

        $updater->update(
            auth()->user(),
            $this->photo
                ? array_merge($this->state, ['photo' => $this->photo])
                : $this->state
        );

        if (isset($this->photo)) {
            $this->photo = null;
        }

        $this->dispatch('saved');
        $this->dispatch('refresh-navigation-menu');
    }

    public function deleteProfilePhoto()
    {
        auth()->user()->deleteProfilePhoto();

        $this->dispatch('refresh-navigation-menu');
    }

    public function render()
    {
        return view('profile.update-profile-information-form');
    }
}