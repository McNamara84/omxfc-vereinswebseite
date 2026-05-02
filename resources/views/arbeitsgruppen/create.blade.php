<x-app-layout>
    <x-member-page class="max-w-3xl">
        <div class="space-y-6">
            <x-ui.page-header
                eyebrow="Mitgliederbereich"
                title="Neue Arbeitsgruppe"
                description="Lege eine neue AG an, bestimme die Leitung und hinterlege die öffentlichen Stammdaten für Mitglieder und Interessierte."
            >
                <x-slot:actions>
                    <x-button label="Zurück" icon="o-arrow-left" link="{{ route('dashboard') }}" wire:navigate class="btn-ghost" />
                </x-slot:actions>
            </x-ui.page-header>

            <x-ui.panel title="Stammdaten" description="Name, Leitung, Beschreibung und optionale Kontaktangaben der neuen Arbeitsgruppe.">
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
                        <input type="file" name="logo" accept="image/*" class="file-input file-input-bordered w-full" />
                        @error('logo')
                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </fieldset>
                </div>

                <x-slot:actions>
                    <x-button label="Abbrechen" link="{{ route('dashboard') }}" wire:navigate class="btn-ghost" />
                    <x-button label="Speichern" type="submit" class="btn-primary" icon="o-check" />
                </x-slot:actions>
                </x-form>
            </x-ui.panel>
        </div>
    </x-member-page>
</x-app-layout>
