<?php

namespace App\Livewire\Profile;

use Illuminate\View\View;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

class UpdateProfileInformationForm extends Component
{
    use Toast;
    use WithFileUploads;

    /**
     * The component's state.
     *
     * @var array
     */
    public $state = [];

    /**
     * The new avatar for the user.
     *
     * @var mixed
     */
    public $photo;

    /**
     * Prepare the component.
     *
     * @return void
     */
    public function mount()
    {
        $this->state = auth()->user()->only([
            'vorname',
            'nachname',
            'email',
            'strasse',
            'hausnummer',
            'plz',
            'stadt',
            'land',
            'telefon',
            'mitgliedsbeitrag',
            'alias',
            'author_aliases',
            'contact_release_email',
            'contact_release_phone',
            'contact_release_maddraxikon',
            'contact_release_nextcloud',
            'maddraxikon_username',
            'nextcloud_username',
        ]);

        $this->state['alias'] = $this->state['alias'] ?? '';
        $this->state['author_aliases'] = $this->state['author_aliases'] ?: [''];
        $this->state['contact_release_email'] = (bool) ($this->state['contact_release_email'] ?? false);
        $this->state['contact_release_phone'] = (bool) ($this->state['contact_release_phone'] ?? false);
        $this->state['contact_release_maddraxikon'] = (bool) ($this->state['contact_release_maddraxikon'] ?? false);
        $this->state['contact_release_nextcloud'] = (bool) ($this->state['contact_release_nextcloud'] ?? false);
        $this->state['maddraxikon_username'] = $this->state['maddraxikon_username'] ?? '';
        $this->state['nextcloud_username'] = $this->state['nextcloud_username'] ?? '';
    }

    /**
     * Update the user's profile information.
     *
     * @return void
     */
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

        $this->toast(
            type: 'success',
            title: __('Gespeichert.'),
            position: 'toast-bottom toast-end',
            icon: 'o-check-circle',
            timeout: 3000,
        );
        $this->dispatch('refresh-navigation-menu');
    }

    /**
     * Delete user's profile photo.
     *
     * @return void
     */
    public function deleteProfilePhoto()
    {
        auth()->user()->deleteProfilePhoto();

        $this->dispatch('refresh-navigation-menu');
    }

    public function addAuthorAlias(): void
    {
        $aliases = $this->state['author_aliases'] ?? [];

        if (! is_array($aliases)) {
            $aliases = [];
        }

        if (count($aliases) >= 10) {
            return;
        }

        $aliases[] = '';
        $this->state['author_aliases'] = $aliases;
    }

    public function removeAuthorAlias(int $index): void
    {
        $aliases = $this->state['author_aliases'] ?? [];

        if (! is_array($aliases) || ! array_key_exists($index, $aliases)) {
            return;
        }

        unset($aliases[$index]);

        $this->state['author_aliases'] = array_values($aliases) ?: [''];
    }

    /**
     * Get the current user of the application.
     *
     * @return mixed
     */
    public function getUserProperty()
    {
        return auth()->user();
    }

    /**
     * Render the component.
     *
     * @return View
     */
    public function render()
    {
        return view('profile.update-profile-information-form');
    }
}
