<x-form-section submit="updatePassword">
    <x-slot name="title">
        {{ __('Passwort ändern') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Bitte verwende nur möglichst komplexe Passwörter. Der Vorstand empfiehlt die Verwendung eines Passwortmanagers.') }}
    </x-slot>

    <x-slot name="form">
        <!-- Hidden username field for accessibility -->
        <input type="text" class="hidden" autocomplete="username" value="{{ old('email', auth()->user()->email) }}" />
        <div class="col-span-6 sm:col-span-4">
            <x-label for="current_password" value="{{ __('Aktuelles Passwort') }}" />
            <x-input id="current_password" type="password" class="mt-1 block w-full" wire:model="state.current_password" autocomplete="current-password" />
            <x-input-error for="current_password" class="mt-2" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-label for="password" value="{{ __('Neues Passwort') }}" />
            <x-input id="password" type="password" class="mt-1 block w-full" wire:model="state.password" autocomplete="new-password" />
            <x-input-error for="password" class="mt-2" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-label for="password_confirmation" value="{{ __('Neues Passwort bestätigen') }}" />
            <x-input id="password_confirmation" type="password" class="mt-1 block w-full" wire:model="state.password_confirmation" autocomplete="new-password" />
            <x-input-error for="password_confirmation" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-action-message class="me-3" on="saved">
            {{ __('Gespeichert.') }}
        </x-action-message>

        <x-button>
            {{ __('Speichern') }}
        </x-button>
    </x-slot>
</x-form-section>
