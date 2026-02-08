<x-app-layout>
    <x-member-page class="max-w-3xl">
        <x-header title="Hörbuchfolge bearbeiten" separator>
            <x-slot:actions>
                <x-button label="Zurück" icon="o-arrow-left" link="{{ route('hoerbuecher.index') }}" class="btn-ghost" />
            </x-slot:actions>
        </x-header>

        <x-card>
            <form action="{{ route('hoerbuecher.update', $episode) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="space-y-4">
                    <x-input
                        name="title"
                        label="Titel"
                        value="{{ old('title', $episode->title) }}"
                        required
                    />

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <x-input
                            name="episode_number"
                            label="Folgenummer"
                            value="{{ old('episode_number', $episode->episode_number) }}"
                            required
                        />

                        <div>
                            <label for="status" class="block text-sm font-medium text-base-content mb-1">Status</label>
                            <select name="status" id="status" required class="select select-bordered w-full">
                                <option value="">-- Status wählen --</option>
                                @foreach($statuses as $status)
                                    <option value="{{ $status }}" {{ old('status', $episode->status->value) === $status ? 'selected' : '' }}>{{ $status }}</option>
                                @endforeach
                            </select>
                            @error('status')
                                <p class="mt-1 text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <x-input
                            name="planned_release_date"
                            label="Ziel-EVT"
                            value="{{ old('planned_release_date', $episode->planned_release_date) }}"
                            placeholder="JJJJ, MM.JJJJ oder TT.MM.JJJJ"
                            required
                        />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <x-input
                            name="author"
                            label="Autor"
                            value="{{ old('author', $episode->author) }}"
                            required
                        />

                        @php
                            $userOptions = $users->map(fn($u) => ['id' => $u->id, 'name' => $u->name])->toArray();
                        @endphp
                        <x-select
                            name="responsible_user_id"
                            label="Verantwortlicher Bearbeiter"
                            :options="$userOptions"
                            placeholder="-- Mitglied wählen --"
                            :value="old('responsible_user_id', $episode->responsible_user_id)"
                        />

                        <x-input
                            name="progress"
                            label="Fortschritt (%)"
                            type="number"
                            value="{{ old('progress', $episode->progress) }}"
                            min="0"
                            max="100"
                            required
                        />
                    </div>

                    {{-- Rollen-Bereich (JS-gesteuert, bleibt nativ) --}}
                    <div>
                        <label class="block text-sm font-medium text-base-content mb-1">Rollen</label>
                        <div
                            class="grid grid-cols-1 gap-2"
                            id="roles_list"
                            data-members-target="#members"
                            data-previous-speaker-url="{{ route('hoerbuecher.previous-speaker') }}"
                            data-role-index="{{ count(old('roles', $episode->roles->toArray())) }}"
                        >
                            <div class="grid grid-cols-1 md:grid-cols-[1.5fr_2fr_auto_2fr_2fr_2fr_auto_auto] gap-2 md:items-center text-xs font-semibold uppercase tracking-wide text-base-content role-row-header">
                                <span>Rolle</span>
                                <span>Beschreibung</span>
                                <span class="md:text-center">Takes</span>
                                <span>Kontakt (optional)</span>
                                <span>Pseudonym (optional)</span>
                                <span>Sprecher</span>
                                <span id="roles-uploaded-header" class="md:text-center">Aufnahme hochgeladen</span>
                                <span class="sr-only md:not-sr-only md:text-right">Aktionen</span>
                            </div>
                            @foreach(old('roles', $episode->roles->toArray()) as $i => $role)
                                @php($uploaded = $role['uploaded'] ?? false)
                                <div class="grid grid-cols-1 md:grid-cols-[1.5fr_2fr_auto_2fr_2fr_2fr_auto_auto] gap-2 mb-2 items-start md:items-center role-row">
                                    <input type="text" name="roles[{{ $i }}][name]" value="{{ $role['name'] ?? '' }}" placeholder="Rolle" aria-label="Rollenname" class="input input-bordered input-sm w-full" />
                                    <input type="text" name="roles[{{ $i }}][description]" value="{{ $role['description'] ?? '' }}" placeholder="Beschreibung" aria-label="Rollenbeschreibung" class="input input-bordered input-sm w-full" />
                                    <input type="number" name="roles[{{ $i }}][takes]" value="{{ $role['takes'] ?? 0 }}" min="0" max="999" inputmode="numeric" placeholder="Takes" aria-label="Anzahl Takes" class="input input-bordered input-sm w-full md:max-w-[6rem]" />
                                    <input type="email" name="roles[{{ $i }}][contact_email]" value="{{ $role['contact_email'] ?? '' }}" placeholder="Kontakt (optional)" aria-label="Kontakt E-Mail" class="input input-bordered input-sm w-full" />
                                    <input type="text" name="roles[{{ $i }}][speaker_pseudonym]" value="{{ $role['speaker_pseudonym'] ?? '' }}" placeholder="Pseudonym (optional)" aria-label="Sprecherpseudonym" class="input input-bordered input-sm w-full" />
                                    <div class="flex flex-col gap-2">
                                        <div>
                                            <input type="text" name="roles[{{ $i }}][member_name]" value="{{ $role['speaker_name'] ?? ($role['member_name'] ?? '') }}" list="members" placeholder="Sprecher" aria-label="Name des Sprechers" class="input input-bordered input-sm w-full" />
                                            <input type="hidden" name="roles[{{ $i }}][member_id]" value="{{ $role['user_id'] ?? ($role['member_id'] ?? '') }}" />
                                        </div>
                                        @php($prev = $previousSpeakers[$role['name'] ?? ''] ?? null)
                                        <div class="text-xs text-base-content previous-speaker" aria-live="polite">
                                            {{ $prev ? 'Bisheriger Sprecher: ' . $prev : '' }}
                                        </div>
                                    </div>
                                    <div class="flex items-center md:justify-center">
                                        <input type="hidden" name="roles[{{ $i }}][uploaded]" value="0">
                                        <input
                                            type="checkbox"
                                            name="roles[{{ $i }}][uploaded]"
                                            value="1"
                                            {{ $uploaded ? 'checked' : '' }}
                                            aria-labelledby="roles-uploaded-header"
                                            class="checkbox checkbox-primary checkbox-sm"
                                        >
                                    </div>
                                    <button type="button" class="text-error md:text-right" aria-label="Rolle entfernen" data-role-remove>&times;</button>
                                </div>
                            @endforeach
                        </div>
                        <x-button type="button" id="add_role" label="Rolle hinzufügen" icon="o-plus" class="btn-ghost btn-sm mt-2" />
                        <datalist id="members">
                            @foreach($users as $member)
                                <option data-id="{{ $member->id }}" value="{{ $member->name }}"></option>
                            @endforeach
                        </datalist>
                        @error('roles')
                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-textarea
                        name="notes"
                        label="Anmerkungen"
                        rows="4"
                    >{{ old('notes', $episode->notes) }}</x-textarea>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <x-button label="Abbrechen" link="{{ route('hoerbuecher.index') }}" class="btn-ghost" />
                    <x-button label="Aktualisieren" type="submit" class="btn-primary" icon="o-check" />
                </div>
            </form>
        </x-card>
    </x-member-page>
    @vite(['resources/js/hoerbuch-role-form.js'])
</x-app-layout>