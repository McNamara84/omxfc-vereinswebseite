<x-member-page class="max-w-6xl space-y-8">
    <x-ui.page-header
        eyebrow="Challenge-Management"
        :title="$formTitle"
        :description="$todoId ? 'Passe Titel, Kategorie, Beschreibung und Baxx-Wert an, ohne die bestehende Challenge-Struktur zu verlieren.' : 'Lege eine neue Challenge mit klarer Kategorie, Beschreibung und Baxx-Wert an, damit Mitglieder sie schnell übernehmen können.'"
    >
        <x-slot:actions>
            <x-button label="Zurück" icon="o-arrow-left" link="{{ $backRoute }}" wire:navigate class="btn-ghost" />
        </x-slot:actions>
    </x-ui.page-header>

    <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,0.72fr)] xl:items-start">
        <x-ui.panel title="Challenge-Formular" description="Alle Felder greifen direkt auf dieselbe Livewire-Instanz zu. Änderungen werden erst mit dem Speichern übernommen.">
            <form wire:submit="save" class="space-y-6">
                <div class="grid gap-5">
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
                        rows="5"
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

                <div class="flex flex-wrap justify-end gap-3 border-t border-base-content/10 pt-6">
                    <x-button label="Abbrechen" link="{{ $backRoute }}" wire:navigate class="btn-ghost" />
                    <x-button label="{{ $todoId ? 'Challenge aktualisieren' : 'Challenge erstellen' }}" type="submit" class="btn-primary" icon="o-check" wire:loading.attr="disabled" wire:target="save" />
                </div>
            </form>
        </x-ui.panel>

        <div class="space-y-6 xl:sticky xl:top-6">
            <x-ui.panel title="Was hier wichtig ist" description="Die Challenge sollte so formuliert sein, dass Mitglieder direkt verstehen, worum es geht und wann sich eine Übernahme lohnt.">
                <ul class="grid gap-3 text-sm leading-relaxed text-base-content/76 sm:text-base">
                    <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Verwende einen klaren Titel, der die Aufgabe ohne zusätzlichen Kontext verständlich macht.</li>
                    <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Beschreibe knapp, was erledigt werden soll, damit Rückfragen minimiert werden.</li>
                    <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Der Baxx-Wert sollte Aufwand, Verlässlichkeit und Sichtbarkeit der Aufgabe widerspiegeln.</li>
                </ul>
            </x-ui.panel>

            <x-ui.panel title="Baxx einordnen" description="Der Punktewert strukturiert nicht nur Belohnung, sondern auch Priorisierung und Motivation im Mitgliederbereich.">
                <div class="grid gap-3 text-sm leading-relaxed text-base-content/76 sm:text-base">
                    <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                        <p class="font-medium text-base-content">Kleinere Routineaufgaben</p>
                        <p class="mt-1">Niedrigere Baxx-Werte für klar begrenzte, schnell übernehmbare Aufgaben.</p>
                    </div>
                    <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                        <p class="font-medium text-base-content">Sichtbare Projektbeiträge</p>
                        <p class="mt-1">Höhere Baxx-Werte für Aufgaben mit größerer Außenwirkung oder höherem Aufwand.</p>
                    </div>
                </div>
            </x-ui.panel>
        </div>
    </section>
</x-member-page>
