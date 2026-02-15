<x-app-layout>
    <x-member-page class="max-w-3xl">
        <x-header title="Neue AG" separator>
            <x-slot:actions>
                <x-button label="Zurück" icon="o-arrow-left" link="{{ route('dashboard') }}" class="btn-ghost" />
            </x-slot:actions>
        </x-header>

        <x-card>
            <x-form method="POST" action="{{ route('arbeitsgruppen.store') }}" no-separator enctype="multipart/form-data">
                @csrf
                <div class="space-y-4">
                    <x-input
                        name="name"
                        label="Name der AG"
                        value="{{ old('name') }}"
                        required
                    />

                    @php
                        $userOptions = $users->map(fn($u) => ['id' => $u->id, 'name' => $u->name])->toArray();
                    @endphp
                    <x-select
                        name="leader_id"
                        label="AG-Leiter"
                        :options="$userOptions"
                        placeholder="-- Mitglied wählen --"
                        :value="old('leader_id')"
                        required
                    />

                    <x-textarea
                        name="description"
                        label="Beschreibung"
                        rows="3"
                    >{{ old('description') }}</x-textarea>

                    <x-input
                        name="email"
                        label="E-Mail-Adresse"
                        type="email"
                        value="{{ old('email') }}"
                    />

                    <x-input
                        name="meeting_schedule"
                        label="Wiederkehrender Termin"
                        value="{{ old('meeting_schedule') }}"
                    />

                    <fieldset class="fieldset py-0">
                        <legend class="fieldset-legend mb-0.5">Logo</legend>
                        <input type="file" name="logo" accept="image/*" class="file-input w-full" />
                        @error('logo')
                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </fieldset>
                </div>

                <x-slot:actions>
                    <x-button label="Abbrechen" link="{{ route('dashboard') }}" class="btn-ghost" />
                    <x-button label="Speichern" type="submit" class="btn-primary" icon="o-check" />
                </x-slot:actions>
            </x-form>
        </x-card>
    </x-member-page>
</x-app-layout>
