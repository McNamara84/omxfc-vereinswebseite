<x-member-page class="max-w-3xl">
    <x-header title="{{ $this->isEditing ? 'Hörbuchfolge bearbeiten' : 'Neue Hörbuchfolge' }}" separator>
        <x-slot:actions>
            <x-button label="Zurück" icon="o-arrow-left" link="{{ $this->isEditing ? route('hoerbuecher.index') : route('dashboard') }}" wire:navigate class="btn-ghost" />
        </x-slot:actions>
    </x-header>

    <x-card>
        <form wire:submit="save">
            <div class="space-y-4">
                <x-input
                    wire:model="title"
                    label="Titel"
                    required
                />

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <x-input
                        wire:model="episode_number"
                        label="Folgenummer"
                        required
                    />

                    @php
                        $statusOptions = collect($this->statuses)->map(fn($s) => ['id' => $s, 'name' => $s])->toArray();
                    @endphp
                    <x-select
                        wire:model="status"
                        label="Status"
                        :options="$statusOptions"
                        placeholder="-- Status wählen --"
                        required
                    />

                    <x-input
                        wire:model="planned_release_date"
                        label="Ziel-EVT"
                        placeholder="JJJJ, MM.JJJJ oder TT.MM.JJJJ"
                        required
                        popover="Erstveröffentlichungstermin. Flexibles Format: nur Jahr, Monat.Jahr oder exaktes Datum."
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <x-input
                        wire:model="author"
                        label="Autor"
                        required
                    />

                    @php
                        $userOptions = $this->users->map(fn($u) => ['id' => $u->id, 'name' => $u->name])->toArray();
                    @endphp
                    <x-select
                        wire:model="responsible_user_id"
                        label="Verantwortlicher Bearbeiter"
                        :options="$userOptions"
                        placeholder="-- Mitglied wählen --"
                    />

                    <x-input
                        wire:model="progress"
                        label="Fortschritt (%)"
                        type="number"
                        min="0"
                        max="100"
                        required
                        popover="Geschätzter Gesamtfortschritt der Produktion in Prozent."
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-base-content mb-1">Rollen</label>

                    @if(count($roles) > 0)
                        <div class="hidden md:grid grid-cols-1 md:grid-cols-[1.5fr_2fr_auto_2fr_2fr_2fr_auto_auto] gap-2 md:items-center text-xs font-semibold uppercase tracking-wide text-base-content role-row-header">
                            <span>Rolle</span>
                            <span>Beschreibung</span>
                            <span class="md:text-center">Takes</span>
                            <span>Kontakt (optional)</span>
                            <span>Pseudonym (optional)</span>
                            <span>Sprecher</span>
                            <span id="roles-uploaded-header" class="md:text-center">Aufnahme hochgeladen</span>
                            <span class="sr-only md:not-sr-only md:text-right">Aktionen</span>
                        </div>
                    @endif

                    @foreach($roles as $i => $role)
                        <div wire:key="role-{{ $role['uid'] ?? $i }}" class="grid grid-cols-1 md:grid-cols-[1.5fr_2fr_auto_2fr_2fr_2fr_auto_auto] gap-2 mb-2 items-start md:items-center role-row"
                            x-data="{
                                previousSpeaker: @js($role['previousSpeaker'] ?? ''),
                                async fetchPreviousSpeaker() {
                                    const name = $wire.get('roles.{{ $i }}.name')?.trim();
                                    if (!name) { this.previousSpeaker = ''; return; }
                                    try {
                                        const url = new URL(@js(route('hoerbuecher.previous-speaker')), window.location.origin);
                                        url.searchParams.set('name', name);
                                        const token = document.querySelector('meta[name=\'csrf-token\']')?.content;
                                        const headers = { 'X-Requested-With': 'XMLHttpRequest' };
                                        if (token) headers['X-CSRF-TOKEN'] = token;
                                        const res = await fetch(url, { headers });
                                        if (!res.ok) throw new Error();
                                        const data = await res.json();
                                        this.previousSpeaker = data.speaker ? `Bisheriger Sprecher: ${data.speaker}` : '';
                                    } catch (e) {
                                        this.previousSpeaker = 'Fehler beim Laden';
                                    }
                                },
                                lookupMemberId() {
                                    const name = $wire.get('roles.{{ $i }}.member_name');
                                    const options = Array.from(document.querySelectorAll('#members option'));
                                    const match = options.find(option => option.value === name);
                                    $wire.set('roles.{{ $i }}.member_id', match?.dataset?.id ?? '', false);
                                }
                            }">
                            <input wire:model.blur="roles.{{ $i }}.name"
                                x-on:blur="fetchPreviousSpeaker()"
                                placeholder="Rolle" aria-label="Rollenname"
                                class="input input-bordered input-sm w-full" />

                            <input wire:model.blur="roles.{{ $i }}.description"
                                placeholder="Beschreibung" aria-label="Rollenbeschreibung"
                                class="input input-bordered input-sm w-full" />

                            <input wire:model.blur="roles.{{ $i }}.takes" type="number"
                                min="0" max="999" inputmode="numeric" placeholder="Takes" aria-label="Anzahl Takes"
                                class="input input-bordered input-sm w-full md:max-w-[6rem]" />

                            <input wire:model.blur="roles.{{ $i }}.contact_email" type="email"
                                placeholder="Kontakt (optional)" aria-label="Kontakt E-Mail"
                                class="input input-bordered input-sm w-full" />

                            <input wire:model.blur="roles.{{ $i }}.speaker_pseudonym"
                                placeholder="Pseudonym (optional)" aria-label="Sprecherpseudonym"
                                class="input input-bordered input-sm w-full" />

                            <div class="flex flex-col gap-2">
                                <div>
                                    <input wire:model.blur="roles.{{ $i }}.member_name"
                                        x-on:input="lookupMemberId()" list="members"
                                        placeholder="Sprecher" aria-label="Name des Sprechers"
                                        class="input input-bordered input-sm w-full" />
                                </div>
                                <div class="text-xs text-base-content previous-speaker" aria-live="polite" x-text="previousSpeaker"></div>
                            </div>

                            <div class="flex items-center md:justify-center">
                                <input wire:model="roles.{{ $i }}.uploaded" type="checkbox"
                                    aria-labelledby="roles-uploaded-header"
                                    class="checkbox checkbox-primary checkbox-sm" />
                            </div>

                            <button type="button" wire:click="removeRole({{ $i }})" class="text-error md:text-right" aria-label="Rolle entfernen">&times;</button>
                        </div>
                    @endforeach

                    <x-button type="button" wire:click="addRole" label="Rolle hinzufügen" icon="o-plus" class="btn-ghost btn-sm mt-2" />

                    <datalist id="members">
                        @foreach($this->users as $member)
                            <option data-id="{{ $member->id }}" value="{{ $member->name }}"></option>
                        @endforeach
                    </datalist>

                    @error('roles')
                        <p class="mt-1 text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <x-textarea
                    wire:model="notes"
                    label="Anmerkungen"
                    rows="4"
                />
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <x-button label="Abbrechen" link="{{ $this->isEditing ? route('hoerbuecher.index') : route('dashboard') }}" wire:navigate class="btn-ghost" />
                <x-button label="{{ $this->isEditing ? 'Aktualisieren' : 'Speichern' }}" type="submit" class="btn-primary" icon="o-check" wire:loading.attr="disabled" />
            </div>
        </form>
    </x-card>
</x-member-page>
