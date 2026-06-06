<div>
    <x-form wire:submit="updateProfileInformation" class="max-w-xl">
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
            <div class="col-span-6 sm:col-span-3">
                <x-input id="vorname" label="{{ __('Vorname') }}" wire:model="state.vorname" required />
            </div>

            <div class="col-span-6 sm:col-span-3">
                <x-input id="nachname" label="{{ __('Nachname') }}" wire:model="state.nachname" required />
            </div>

            <div class="col-span-6 sm:col-span-4">
                <x-input id="alias" label="{{ __('Alias / Nickname (optional)') }}" wire:model="state.alias" maxlength="255" />
            </div>

            @if (auth()->user()?->hasRole(\App\Enums\Role::Ehrenmitglied))
                <div class="col-span-6 space-y-3">
                    <div>
                        <h3 class="text-sm font-semibold text-base-content">{{ __('Autorennamen (optional)') }}</h3>
                        <p class="mt-1 text-sm text-base-content/70">
                            {{ __('Diese Namen werden ergänzend zu deinem Alias im Profil angezeigt.') }}
                        </p>
                    </div>

                    @foreach (($state['author_aliases'] ?? ['']) as $index => $authorAlias)
                        <div class="flex items-end gap-2" wire:key="author-alias-{{ $index }}">
                            <div class="flex-1">
                                <x-input
                                    id="author_alias_{{ $index }}"
                                    label="{{ $index === 0 ? __('Autorenname') : __('Weiterer Autorenname') }}"
                                    wire:model="state.author_aliases.{{ $index }}"
                                    maxlength="255"
                                />
                            </div>

                            @if (count($state['author_aliases'] ?? []) > 1)
                                <x-button
                                    type="button"
                                    icon="o-trash"
                                    class="btn-ghost text-error"
                                    wire:click="removeAuthorAlias({{ $index }})"
                                    tooltip="Autorenname entfernen"
                                />
                            @endif
                        </div>
                    @endforeach

                    <x-button
                        type="button"
                        icon="o-plus"
                        label="{{ __('Autorenname hinzufügen') }}"
                        class="btn-outline btn-sm"
                        wire:click="addAuthorAlias"
                    />
                </div>
            @endif

            <div class="col-span-4">
                <x-input id="strasse" label="{{ __('Straße') }}" wire:model="state.strasse" required title="Deine Adresse wird für die interaktive Mitgliederkarte und den Postversand verwendet." />
            </div>

            <div class="col-span-2">
                <x-input id="hausnummer" label="{{ __('Hausnummer') }}" wire:model="state.hausnummer" required />
            </div>

            <div class="col-span-2">
                <x-input id="plz" label="{{ __('PLZ') }}" wire:model="state.plz" required />
            </div>

            <div class="col-span-4">
                <x-input id="stadt" label="{{ __('Stadt') }}" wire:model="state.stadt" required />
            </div>

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

            <div class="col-span-6 sm:col-span-4">
                <x-input id="telefon" label="{{ __('Telefonnummer (optional)') }}" type="tel" wire:model="state.telefon" />
            </div>

            <div class="col-span-6 sm:col-span-4">
                <x-input id="mitgliedsbeitrag" label="{{ __('Mitgliedsbeitrag (jährlich, min. 12€)') }}" type="number" min="12" wire:model="state.mitgliedsbeitrag" required title="Dein gewählter Jahresbeitrag wird beim nächsten Fälligkeitsdatum wirksam." />
            </div>

            <div class="col-span-6 sm:col-span-4">
                <x-input id="email" label="{{ __('E-Mail') }}" type="email" wire:model="state.email" required autocomplete="username" />
            </div>

            <div class="col-span-6 border-t border-base-300 pt-4">
                <div class="space-y-4">
                    <div>
                        <h3 class="text-sm font-semibold text-base-content">{{ __('Kontaktfreigabe') }}</h3>
                        <p class="mt-1 text-sm text-base-content/70">
                            {{ __('Diese Kontaktwege sind nur für eingeloggte Mitglieder sichtbar.') }}
                        </p>
                    </div>

                    <div class="grid gap-3">
                        <x-checkbox
                            label="{{ __('E-Mail für andere Mitglieder freigeben') }}"
                            wire:model.live="state.contact_release_email"
                        />

                        <x-checkbox
                            label="{{ __('Telefonnummer für andere Mitglieder freigeben') }}"
                            wire:model.live="state.contact_release_phone"
                        />

                        <div class="space-y-2">
                            <x-checkbox
                                label="{{ __('Maddraxikon-Profil freigeben') }}"
                                wire:model.live="state.contact_release_maddraxikon"
                            />

                            @if ($state['contact_release_maddraxikon'] ?? false)
                                <div class="pl-7">
                                    <x-input
                                        id="maddraxikon_username"
                                        label="{{ __('Maddraxikon-Account') }}"
                                        wire:model="state.maddraxikon_username"
                                        placeholder="Stefan K"
                                    />
                                    <p class="mt-1 text-sm text-base-content/70">
                                        {{ __('Trage deinen Benutzernamen aus dem Maddraxikon ein. Leerzeichen werden für den Profil-Link automatisch zu Unterstrichen.') }}
                                    </p>
                                </div>
                            @endif
                        </div>

                        <div class="space-y-2">
                            <x-checkbox
                                label="{{ __('Nextcloud-Profil freigeben') }}"
                                wire:model.live="state.contact_release_nextcloud"
                            />

                            @if ($state['contact_release_nextcloud'] ?? false)
                                <div class="pl-7">
                                    <x-input
                                        id="nextcloud_username"
                                        label="{{ __('Nextcloud-Account') }}"
                                        wire:model="state.nextcloud_username"
                                        placeholder="Holger"
                                    />
                                    <p class="mt-1 text-sm text-base-content/70">
                                        {{ __('Trage den Accountnamen aus der Fanclub-Cloud ein, wie er in deinem Profil-Link nach /u/ steht.') }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <x-slot:actions>
            <x-button type="submit" class="btn-primary" wire:loading.attr="disabled">
                {{ __('Speichern') }}
            </x-button>
        </x-slot:actions>
    </x-form>
</div>
