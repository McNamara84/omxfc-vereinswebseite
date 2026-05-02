<x-app-layout>
    <x-member-page class="max-w-3xl">
        <x-ui.page-header
            eyebrow="Mitgliederbereich"
            title="Challenge bearbeiten"
            description="Passe Titel, Kategorie, Beschreibung und Baxx-Wert einer bestehenden Challenge an, ohne den Arbeitsfluss zu ändern."
        >
            <x-slot:actions>
                <x-button label="Zurück" icon="o-arrow-left" link="{{ route('todos.show', $todo) }}" wire:navigate class="btn-ghost" />
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.panel title="Challenge-Daten" description="Hier werden bestehende Angaben gepflegt und für den nächsten Bearbeitungsschritt aktualisiert.">
            <form action="{{ route('todos.update', $todo) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="space-y-4">
                    <x-input
                        name="title"
                        label="Titel"
                        value="{{ old('title', $todo->title) }}"
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
                        :value="old('category_id', $todo->category_id)"
                        required
                    />

                    <x-textarea
                        name="description"
                        label="Beschreibung"
                        rows="4"
                    >{{ old('description', $todo->description) }}</x-textarea>

                    <x-input
                        name="points"
                        label="Baxx"
                        type="number"
                        value="{{ old('points', $todo->points) }}"
                        min="1"
                        max="1000"
                        hint="Wie viele Baxx erhält das Mitglied für die Erledigung dieser Challenge?"
                        required
                    />
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <x-button label="Abbrechen" link="{{ route('todos.show', $todo) }}" wire:navigate class="btn-ghost" />
                    <x-button label="Challenge aktualisieren" type="submit" class="btn-primary" icon="o-check" />
                </div>
            </form>
        </x-ui.panel>
    </x-member-page>
</x-app-layout>