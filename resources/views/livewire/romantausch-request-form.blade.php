<x-member-page class="max-w-4xl">
    <x-card>
        <x-header :title="$this->isEditing ? 'Gesuch bearbeiten' : 'Neues Gesuch erstellen'" separator useH1 data-testid="page-title" />
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
    </x-card>
</x-member-page>
