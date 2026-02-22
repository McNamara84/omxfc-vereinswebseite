<?php

namespace App\Livewire\Teams;

use Illuminate\Support\Facades\Auth;
use Laravel\Jetstream\Contracts\UpdatesTeamNames;
use Laravel\Jetstream\Http\Livewire\UpdateTeamNameForm as JetstreamUpdateTeamNameForm;
use Mary\Traits\Toast;

class UpdateTeamNameForm extends JetstreamUpdateTeamNameForm
{
    use Toast;

    /**
     * Update the team's name.
     */
    public function updateTeamName(UpdatesTeamNames $updater): void
    {
        $this->resetErrorBag();

        $updater->update($this->user, $this->team, $this->state);

        $this->toast(
            type: 'success',
            title: __('Gespeichert.'),
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            timeout: 3000,
        );

        $this->dispatch('refresh-navigation-menu');
    }
}
