<?php

namespace App\Livewire\Profile;

use Livewire\Component;
use App\Actions\Fortify\UpdateUserSeriendaten;
use Illuminate\Support\Facades\Auth;

class UpdateSeriendatenForm extends Component
{
    public array $state = [];

    public function mount()
    {
        $this->state = Auth::user()->only([
            'einstiegsroman',
            'lesestand',
            'lieblingsroman',
            'lieblingsfigur',
            'lieblingsmutation',
            'lieblingsschauplatz',
            'lieblingsautor',
            'lieblingszyklus',
        ]);
    }

    public function updateSeriendaten(UpdateUserSeriendaten $updater)
    {
        $updater->update(Auth::user(), $this->state);
        $this->dispatch('saved');
    }

    public function render()
    {
        return view('profile.update-seriendaten-form');
    }
}
