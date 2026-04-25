<x-member-page class="max-w-3xl">
    <x-header :title="$formTitle" separator>
        <x-slot:actions>
            <x-button label="Zurück" icon="o-arrow-left" link="{{ $backRoute }}" wire:navigate class="btn-ghost" />
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

                <x-select
                    wire:model="category_id"
                    label="Kategorie"
                    :options="$categories"
                    placeholder="-- Kategorie wählen --"
                    required
                />

                <x-textarea
                    wire:model="description"
                    label="Beschreibung"
                    rows="4"
                />

                <x-input
                    wire:model="points"
                    label="Baxx"
                    type="number"
                    min="1"
                    max="1000"
                    hint="Wie viele Baxx erhält das Mitglied für die Erledigung dieser Challenge?"
                    required
                />
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <x-button label="Abbrechen" link="{{ $backRoute }}" wire:navigate class="btn-ghost" />
                <x-button label="{{ $todoId ? 'Challenge aktualisieren' : 'Challenge erstellen' }}" type="submit" class="btn-primary" icon="o-check"
                    wire:loading.attr="disabled" wire:target="save" />
            </div>
        </form>
    </x-card>
</x-member-page>
