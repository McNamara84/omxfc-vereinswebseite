<?php

// app\Livewire\Profile\UpdateSeriendatenForm.php

namespace App\Livewire\Profile;

use App\Actions\Fortify\UpdateUserSeriendaten;
use App\Services\Profile\SeriesProfileCompletion;
use App\Services\Profile\SeriesProfileOptions;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Mary\Traits\Toast;

class UpdateSeriendatenForm extends Component
{
    use Toast;

    public array $state = [];

    public function mount()
    {
        $user = Auth::user();
        $this->state = $user->only(array_keys(SeriesProfileCompletion::FIELDS));
    }

    public function updateSeriendaten(UpdateUserSeriendaten $updater)
    {
        $updater->update(Auth::user(), $this->state);
        $this->state = Auth::user()->only(array_keys(SeriesProfileCompletion::FIELDS));
        $this->dispatch('profile-series-saved');
        $this->toast(
            type: 'success',
            title: __('Gespeichert.'),
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            timeout: 3000,
        );
    }

    public function render()
    {
        $options = app(SeriesProfileOptions::class)->all();
        $user = Auth::user()->fresh();
        $unavailable = [];
        foreach ($options as $field => &$values) {
            if ($values === []) {
                $unavailable[] = SeriesProfileCompletion::FIELDS[$field];
            }
            // Keep historical choices visible even if the current catalog lacks them.
            $existing = $user->getAttribute($field);
            if (filled($existing) && ! in_array($existing, $values, true)) {
                $values[] = $existing;
            }
        }
        unset($values);

        return view('profile.update-seriendaten-form', [
            'fields' => SeriesProfileCompletion::FIELDS,
            'fieldOptions' => $options,
            'unavailable' => $unavailable,
            'completion' => app(SeriesProfileCompletion::class)->forUser($user),
        ]);
    }
}
