<div>
    <x-form wire:submit="updatePassword" class="max-w-xl">
        <!-- Hidden username field for accessibility -->
        <input type="text" class="hidden" autocomplete="username" value="{{ old('email', auth()->user()->email) }}" />

        <x-input
            id="current_password"
            label="{{ __('Aktuelles Passwort') }}"
            type="password"
            wire:model="state.current_password"
            autocomplete="current-password" />

        <x-input
            id="password"
            label="{{ __('Neues Passwort') }}"
            type="password"
            wire:model="state.password"
            autocomplete="new-password" />

        <x-input
            id="password_confirmation"
            label="{{ __('Neues Passwort bestätigen') }}"
            type="password"
            wire:model="state.password_confirmation"
            autocomplete="new-password" />

        <x-slot:actions>
            <x-button type="submit" class="btn-primary">
                {{ __('Speichern') }}
            </x-button>
        </x-slot:actions>
    </x-form>
</div>
