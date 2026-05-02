<x-member-page class="max-w-6xl space-y-8">
    <x-ui.page-header
        :title="$this->isEditing ? 'Gesuch bearbeiten' : 'Neues Gesuch erstellen'"
        eyebrow="Romantauschbörse"
        :description="$this->isEditing ? 'Aktualisiere dein bestehendes Gesuch, wenn sich Serie, Roman oder Wunschzustand geändert haben.' : 'Lege ein präzises Gesuch an, damit passende Angebote schneller sichtbar werden.'"
        data-testid="page-title"
    >
        <x-slot:actions>
            <x-button label="Zurück zur Übersicht" link="{{ route('romantausch.index') }}" wire:navigate icon="o-arrow-left" class="btn-ghost" />
        </x-slot:actions>
    </x-ui.page-header>

    <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,0.72fr)] xl:items-start">
        <x-ui.panel title="Gesuch" description="Serie, Roman und Mindestzustand bilden die Grundlage für das automatische Matching mit offenen Angeboten.">
            <form wire:submit="save" id="request-form">
            @php
                $seriesOptions = $this->seriesOptions;
                $bookOptions = $this->bookOptions;
                $conditionOptions = $this->conditionOptions;
                $booksBySeries = $this->booksBySeries;
            @endphp

            <div class="grid gap-6 md:grid-cols-2">
                <div class="md:col-span-1 space-y-4">
                    <x-form-select
                        id="series-select"
                        name="series"
                        label="Serie"
                        aria-label="Serie"
                        :options="$seriesOptions"
                        :value="$series"
                        error-field="series"
                        wire:model="series"
                    />

                    <x-form-select
                        id="book-select"
                        name="book_number"
                        label="Roman"
                        aria-label="Roman"
                        :options="$bookOptions"
                        :value="$book_number"
                        error-field="book_number"
                        wire:model="book_number"
                    />

                    <x-form-select
                        id="condition-select"
                        name="condition"
                        label="Zustand bis einschließlich"
                        aria-label="Zustand bis einschließlich"
                        :options="$conditionOptions"
                        :value="$condition"
                        error-field="condition"
                        wire:model="condition"
                    />
                </div>

                <div class="md:col-span-1 flex items-center">
                    <p class="text-sm text-base-content leading-relaxed">Beschreibe so genau wie möglich, welchen Roman du suchst und in welchem Zustand er mindestens sein soll. Mit präzisen Angaben erhöhst du die Chancen auf einen passenden Tausch.</p>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
                <x-button :label="$this->isEditing ? 'Änderungen speichern' : 'Gesuch speichern'" type="submit" class="btn-primary" icon="o-check" spinner="save" />
                <x-button label="Abbrechen" link="{{ route('romantausch.index') }}" wire:navigate class="btn-ghost" />
            </div>

            <div data-romantausch-books-by-series="{{ json_encode($booksBySeries) }}" class="hidden"></div>
            </form>
        </x-ui.panel>

        <div class="space-y-6 xl:sticky xl:top-6">
            <x-ui.panel title="Treffsichere Gesuche" description="Gute Gesuche machen anderen sofort klar, wonach du suchst und welcher Zustand noch passt.">
                <ul class="grid gap-3 text-sm leading-relaxed text-base-content/76 sm:text-base">
                    <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Wähle den exakten Band statt eines ungefähren Bereichs. Das verbessert die Match-Hinweise auf der Übersicht deutlich.</li>
                    <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Der gewünschte Zustand sollte ehrlich widerspiegeln, wie kompromissbereit du bist.</li>
                    <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Sobald eines deiner eigenen Angebote zu einem fremden Gesuch passt, hebt die Übersicht das direkt hervor.</li>
                </ul>
            </x-ui.panel>
        </div>
    </section>
</x-member-page>
