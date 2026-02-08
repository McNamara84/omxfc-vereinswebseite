<div>
    <x-header title="{{ __('Persönliche Daten') }}" subtitle="{{ __('Hier kannst du ganz einfach deine persönlichen Angaben aktualisieren. Bitte halte diese Informationen möglichst aktuell, damit wir dich erreichen können. Während Namen und Foto für andere sichtbar sind, bleiben deine Adressdaten und dein eingestellter Mitgliedsbeitrag für andere Mitglieder unsichtbar.') }}" size="text-lg" class="!mb-4" />

    <x-form wire:submit="updateProfileInformation" class="max-w-xl">
        <!-- Profilfoto -->
        @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
            <div x-data="{photoName: null, photoPreview: null}">
                <input type="file" id="photo" class="hidden" wire:model.live="photo" x-ref="photo" x-on:change="
                                        photoName = $refs.photo.files[0].name;
                                        const reader = new FileReader();
                                        reader.onload = (e) => {
                                            photoPreview = e.target.result;
                                        };
                                        reader.readAsDataURL($refs.photo.files[0]);
                                " />

                <label class="fieldset-legend">{{ __('Foto') }}</label>

                <div class="mt-2" x-show="! photoPreview">
                    <img loading="lazy" src="{{ $this->user->profile_photo_url }}" alt="{{ $this->user->name }}"
                        class="rounded-full size-20 object-cover">
                </div>

                <div class="mt-2" x-show="photoPreview" style="display: none;">
                    <span class="block rounded-full size-20 bg-cover bg-no-repeat bg-center"
                        x-bind:style="'background-image: url(\'' + photoPreview + '\');'"></span>
                </div>

                <div class="flex gap-2 mt-2">
                    <x-button type="button" x-on:click.prevent="$refs.photo.click()">
                        {{ __('Neues Foto auswählen') }}
                    </x-button>

                    @if ($this->user->profile_photo_path)
                        <x-button type="button" wire:click="deleteProfilePhoto">
                            {{ __('Foto entfernen') }}
                        </x-button>
                    @endif
                </div>

                <p class="mt-2 text-sm text-base-content">
                    {{ __('Erlaubte Dateiformate: jpg, jpeg, png, gif, webp. Max. Größe: 8 MB.') }}
                </p>

                @error('photo')
                    <p class="text-error text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
        @endif

        <div class="grid grid-cols-6 gap-4">
            <!-- Vorname -->
            <div class="col-span-6 sm:col-span-3">
                <x-input id="vorname" label="{{ __('Vorname') }}" wire:model="state.vorname" required />
            </div>

            <!-- Nachname -->
            <div class="col-span-6 sm:col-span-3">
                <x-input id="nachname" label="{{ __('Nachname') }}" wire:model="state.nachname" required />
            </div>

            <!-- Straße -->
            <div class="col-span-4">
                <x-input id="strasse" label="{{ __('Straße') }}" wire:model="state.strasse" required />
            </div>

            <!-- Hausnummer -->
            <div class="col-span-2">
                <x-input id="hausnummer" label="{{ __('Hausnummer') }}" wire:model="state.hausnummer" required />
            </div>

            <!-- PLZ -->
            <div class="col-span-2">
                <x-input id="plz" label="{{ __('PLZ') }}" wire:model="state.plz" required />
            </div>

            <!-- Stadt -->
            <div class="col-span-4">
                <x-input id="stadt" label="{{ __('Stadt') }}" wire:model="state.stadt" required />
            </div>

            <!-- Land -->
            <div class="col-span-6 sm:col-span-4">
                @php
                    $landOptions = [
                        ['id' => 'Deutschland', 'name' => 'Deutschland'],
                        ['id' => 'Österreich', 'name' => 'Österreich'],
                        ['id' => 'Schweiz', 'name' => 'Schweiz'],
                    ];
                @endphp
                <x-select id="land" label="{{ __('Land') }}" wire:model="state.land" :options="$landOptions" required />
            </div>

            <!-- Telefon -->
            <div class="col-span-6 sm:col-span-4">
                <x-input id="telefon" label="{{ __('Telefonnummer (optional)') }}" type="tel" wire:model="state.telefon" />
            </div>

            <!-- Mitgliedsbeitrag -->
            <div class="col-span-6 sm:col-span-4">
                <x-input id="mitgliedsbeitrag" label="{{ __('Mitgliedsbeitrag (jährlich, min. 12€)') }}" type="number" min="12" wire:model="state.mitgliedsbeitrag" required />
            </div>

            <!-- E-Mail -->
            <div class="col-span-6 sm:col-span-4">
                <x-input id="email" label="{{ __('E-Mail') }}" type="email" wire:model="state.email" required autocomplete="username" />
            </div>
        </div>

        <x-slot:actions>
            <x-action-message class="me-3" on="saved">
                {{ __('Gespeichert.') }}
            </x-action-message>
            <x-button type="submit" class="btn-primary" wire:loading.attr="disabled">
                {{ __('Speichern') }}
            </x-button>
        </x-slot:actions>
    </x-form>
</div>