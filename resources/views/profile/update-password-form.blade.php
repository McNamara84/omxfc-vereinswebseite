<div>
    <x-form wire:submit="updatePassword" class="max-w-xl">
        <!-- Hidden username field for accessibility -->
        <input type="text" class="hidden" autocomplete="username" value="{{ old('email', auth()->user()->email) }}" />

        <x-password
            id="current_password"
            label="{{ __('Aktuelles Passwort') }}"
            wire:model="state.current_password"
            autocomplete="current-password" />

        <x-password
            id="password"
            label="{{ __('Neues Passwort') }}"
            wire:model="state.password"
            hint="{{ __('Mindestens 8 Zeichen.') }}"
            popover="{{ __('Verwende mindestens 8 Zeichen und ein nur hier genutztes Passwort.') }}"
            autocomplete="new-password" />

        <x-password
            id="password_confirmation"
            label="{{ __('Neues Passwort bestätigen') }}"
            wire:model="state.password_confirmation"
            autocomplete="new-password" />

        <x-slot:actions>
            <x-button type="submit" class="btn-primary">
                {{ __('Speichern') }}
            </x-button>
        </x-slot:actions>
    </x-form>
</div>
