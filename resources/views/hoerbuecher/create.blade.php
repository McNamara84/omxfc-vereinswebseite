<x-app-layout>
    <x-member-page class="max-w-3xl">
        <x-header title="Neue Hörbuchfolge" separator>
            <x-slot:actions>
                <x-button label="Zurück" icon="o-arrow-left" link="{{ route('dashboard') }}" class="btn-ghost" />
            </x-slot:actions>
        </x-header>

        <x-card>
            <form action="{{ route('hoerbuecher.store') }}" method="POST">
                @csrf

                <div class="space-y-4">
                    <x-input
                        name="title"
                        label="Titel"
                        value="{{ old('title') }}"
                        required
                    />

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <x-input
                            name="episode_number"
                            label="Folgenummer"
                            value="{{ old('episode_number') }}"
                            required
                        />

                        @php
                            $statusOptions = collect($statuses)->map(fn($s) => ['id' => $s, 'name' => $s])->toArray();
                        @endphp
                        <x-select
                            name="status"
                            label="Status"
                            :options="$statusOptions"
                            placeholder="-- Status wählen --"
                            :value="old('status')"
                            required
                        />

                        <x-input
                            name="planned_release_date"
                            label="Ziel-EVT"
                            value="{{ old('planned_release_date') }}"
                            placeholder="JJJJ, MM.JJJJ oder TT.MM.JJJJ"
                            required
                        />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <x-input
                            name="author"
                            label="Autor"
                            value="{{ old('author') }}"
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
                            :value="old('responsible_user_id')"
                        />

                        <x-input
                            name="progress"
                            label="Fortschritt (%)"
                            type="number"
                            value="{{ old('progress', 0) }}"
                            min="0"
                            max="100"
                            required
                        />
                    </div>

                    {{-- Rollen-Bereich (JS-gesteuert, bleibt nativ) --}}
                    <div>
                        <label class="block text-sm font-medium text-base-content/70 mb-1">Rollen</label>
                        <div
                            id="roles_list"
                            data-members-target="#members"
                            data-previous-speaker-url="{{ route('hoerbuecher.previous-speaker') }}"
                            data-role-index="{{ count(old('roles', [])) }}"
                        ></div>
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
                    >{{ old('notes') }}</x-textarea>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <x-button label="Abbrechen" link="{{ route('dashboard') }}" class="btn-ghost" />
                    <x-button label="Speichern" type="submit" class="btn-primary" icon="o-check" />
                </div>
            </form>
        </x-card>
    </x-member-page>
    @vite(['resources/js/hoerbuch-role-form.js'])
</x-app-layout>
