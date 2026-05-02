<x-app-layout>
    <x-member-page class="max-w-3xl">
        <x-ui.page-header
            eyebrow="Mitgliederbereich"
            title="Neue Challenge erstellen"
            description="Lege eine neue Challenge mit Titel, Kategorie, Beschreibung und Baxx-Wert in einem kompakten Formular an."
        >
            <x-slot:actions>
                <x-button label="Zurück" icon="o-arrow-left" link="{{ route('todos.index') }}" wire:navigate class="btn-ghost" />
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.panel title="Challenge-Daten" description="Alle für Veröffentlichung und Vergütung relevanten Angaben werden hier zentral gepflegt.">
            <form action="{{ route('todos.store') }}" method="POST">
                @csrf

                <div class="space-y-4">
                    <x-input
                        name="title"
                        label="Titel"
                        value="{{ old('title') }}"
                        required
                    />

                    @php
                        $categoryOptions = $categories->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->toArray();
                    @endphp
                    <x-select
                        name="category_id"
                        label="Kategorie"
                        :options="$categoryOptions"
                        placeholder="-- Kategorie wählen --"
                        :value="old('category_id')"
                        required
                    />

                    <x-textarea
                        name="description"
                        label="Beschreibung"
                        rows="4"
                    >{{ old('description') }}</x-textarea>

                    <x-input
                        name="points"
                        label="Baxx"
                        type="number"
                        value="{{ old('points', 1) }}"
                        min="1"
                        max="1000"
                        hint="Wie viele Baxx erhält das Mitglied für die Erledigung dieser Challenge?"
                        required
                    />
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <x-button label="Abbrechen" link="{{ route('todos.index') }}" wire:navigate class="btn-ghost" />
                    <x-button label="Challenge erstellen" type="submit" class="btn-primary" icon="o-check" />
                </div>
            </form>
        </x-ui.panel>
    </x-member-page>
</x-app-layout>