<x-form-section submit="updateProfileInformation">
    <x-slot name="title">
        {{ __('Persönliche Daten') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Hier kannst du ganz einfach deine persönlichen Angaben aktualisieren. Bitte halte diese Informationen möglichst aktuell, damit wir dich erreichen können. Während Namen und Foto für andere sichtbar sind, bleiben deine Adressdaten und dein eingestellter Mitgliedsbeitrag für andere Mitglieder unsichtbar.') }}
    </x-slot>

    <x-slot name="form">
        <!-- Profilfoto -->
        @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
            <div x-data="{photoName: null, photoPreview: null}" class="col-span-6 sm:col-span-4">
                <input type="file" id="photo" class="hidden" wire:model.live="photo" x-ref="photo" x-on:change="
                                        photoName = $refs.photo.files[0].name;
                                        const reader = new FileReader();
                                        reader.onload = (e) => {
                                            photoPreview = e.target.result;
                                        };
                                        reader.readAsDataURL($refs.photo.files[0]);
                                " />

                <x-label for="photo" value="{{ __('Foto') }}" />

                <div class="mt-2" x-show="! photoPreview">
                    <img loading="lazy" src="{{ $this->user->profile_photo_url }}" alt="{{ $this->user->name }}"
                        class="rounded-full size-20 object-cover">
                </div>

                <div class="mt-2" x-show="photoPreview" style="display: none;">
                    <span class="block rounded-full size-20 bg-cover bg-no-repeat bg-center"
                        x-bind:style="'background-image: url(\'' + photoPreview + '\');'"></span>
                </div>

                <x-secondary-button class="mt-2 me-2" type="button" x-on:click.prevent="$refs.photo.click()">
                    {{ __('Neues Foto auswählen') }}
                </x-secondary-button>

                @if ($this->user->profile_photo_path)
                    <x-secondary-button type="button" class="mt-2" wire:click="deleteProfilePhoto">
                        {{ __('Foto entfernen') }}
                    </x-secondary-button>
                @endif

                <x-input-error for="photo" class="mt-2" />
            </div>
        @endif

        <!-- Vorname -->
        <div class="col-span-6 sm:col-span-3">
            <x-label for="vorname" value="{{ __('Vorname') }}" />
            <x-input id="vorname" type="text" class="mt-1 block w-full" wire:model="state.vorname" required />
            <x-input-error for="vorname" class="mt-2" />
        </div>

        <!-- Nachname -->
        <div class="col-span-6 sm:col-span-3">
            <x-label for="nachname" value="{{ __('Nachname') }}" />
            <x-input id="nachname" type="text" class="mt-1 block w-full" wire:model="state.nachname" required />
            <x-input-error for="nachname" class="mt-2" />
        </div>

        <!-- Straße -->
        <div class="col-span-4">
            <x-label for="strasse" value="{{ __('Straße') }}" />
            <x-input id="strasse" type="text" class="mt-1 block w-full" wire:model="state.strasse" required />
            <x-input-error for="strasse" class="mt-2" />
        </div>

        <!-- Hausnummer -->
        <div class="col-span-2">
            <x-label for="hausnummer" value="{{ __('Hausnummer') }}" />
            <x-input id="hausnummer" type="text" class="mt-1 block w-full" wire:model="state.hausnummer" required />
            <x-input-error for="hausnummer" class="mt-2" />
        </div>

        <!-- PLZ -->
        <div class="col-span-2">
            <x-label for="plz" value="{{ __('PLZ') }}" />
            <x-input id="plz" type="text" class="mt-1 block w-full" wire:model="state.plz" required />
            <x-input-error for="plz" class="mt-2" />
        </div>

        <!-- Stadt -->
        <div class="col-span-4">
            <x-label for="stadt" value="{{ __('Stadt') }}" />
            <x-input id="stadt" type="text" class="mt-1 block w-full" wire:model="state.stadt" required />
            <x-input-error for="stadt" class="mt-2" />
        </div>

        <!-- Land -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="land" value="{{ __('Land') }}" />
            <select id="land" wire:model="state.land" required
                class="mt-1 block w-full rounded-md shadow-sm border-gray-300">
                <option value="Deutschland">Deutschland</option>
                <option value="Österreich">Österreich</option>
                <option value="Schweiz">Schweiz</option>
            </select>
            <x-input-error for="land" class="mt-2" />
        </div>

        <!-- Telefon -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="telefon" value="{{ __('Telefonnummer (optional)') }}" />
            <x-input id="telefon" type="tel" class="mt-1 block w-full" wire:model="state.telefon" />
            <x-input-error for="telefon" class="mt-2" />
        </div>

        <!-- Mitgliedsbeitrag -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="mitgliedsbeitrag" value="{{ __('Mitgliedsbeitrag (jährlich, min. 12€)') }}" />
            <x-input id="mitgliedsbeitrag" type="number" min="12" class="mt-1 block w-full"
                wire:model="state.mitgliedsbeitrag" required />
            <x-input-error for="mitgliedsbeitrag" class="mt-2" />
        </div>

        <!-- E-Mail -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="email" value="{{ __('E-Mail') }}" />
            <x-input id="email" type="email" class="mt-1 block w-full" wire:model="state.email" required
                autocomplete="username" />
            <x-input-error for="email" class="mt-2" />
        </div>

    </x-slot>

    <x-slot name="actions">
        <x-action-message class="me-3" on="saved">
            {{ __('Gespeichert.') }}
        </x-action-message>

        <x-button wire:loading.attr="disabled">
            {{ __('Speichern') }}
        </x-button>
    </x-slot>
</x-form-section>