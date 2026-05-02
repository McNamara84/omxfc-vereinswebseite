<x-member-page class="max-w-6xl space-y-8">
    <x-ui.page-header
        :title="$this->isEditing ? '3D-Modell bearbeiten' : '3D-Modell hochladen'"
        eyebrow="3D-Bibliothek"
        :description="$this->isEditing ? 'Aktualisiere Metadaten, Datei oder Vorschaubild des bestehenden Modells.' : 'Lege ein neues Modell mit Baxx-Wert, Vorschau und optionalem Maddraxikon-Link an.'"
        data-testid="page-header"
    >
        <x-slot:actions>
            <x-button label="Zurück" icon="o-arrow-left" link="{{ route('3d-modelle.index') }}" wire:navigate class="btn-ghost" />
        </x-slot:actions>
    </x-ui.page-header>

    <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(19rem,0.75fr)] xl:items-start">
        <x-ui.panel title="Modell-Formular" description="Alle Inhalte werden direkt über Livewire verwaltet. Datei und Vorschaubild lassen sich beim Bearbeiten gezielt austauschen.">
            <form wire:submit="save" class="space-y-6">
                <div class="grid gap-5">
                    <x-input
                        wire:model="name"
                        label="Name"
                        required
                        placeholder="z.B. Euphoriewurm"
                        data-testid="name-input"
                    />

                    <x-textarea
                        wire:model="description"
                        label="Beschreibung"
                        rows="4"
                        required
                        placeholder="Kurze Beschreibung des 3D-Modells..."
                        data-testid="description-input"
                    />

                    <x-input
                        wire:model="cost_baxx"
                        label="Preis in Baxx"
                        type="number"
                        min="1"
                        max="1000"
                        required
                        data-testid="baxx-input"
                    />

                    <x-input
                        wire:model="maddraxikon_url"
                        label="Maddraxikon-Link (optional)"
                        type="url"
                        placeholder="https://maddraxikon.de/..."
                        hint="Link zum entsprechenden Artikel im Maddraxikon"
                        data-testid="maddraxikon-url-input"
                    />

                    <div class="rounded-[1.5rem] border border-base-content/10 bg-base-100/78 p-4">
                        <label for="model_file" class="label label-text font-semibold">
                            {{ $this->isEditing ? 'Neue 3D-Datei (optional)' : '3D-Datei (STL, OBJ oder FBX)' }}
                        </label>
                        <input type="file" wire:model="model_file" id="model_file" class="file-input file-input-bordered w-full" {{ $this->isEditing ? '' : 'required' }} accept=".stl,.obj,.fbx" data-testid="file-input" />
                        <p class="mt-2 text-sm text-base-content/62">
                            @if ($this->isEditing && $this->existingModel)
                                Aktuelle Datei: {{ strtoupper($this->existingModel->file_format) }} ({{ $this->existingModel->file_size_formatted }}). Nur hochladen, wenn die Datei ersetzt werden soll.
                            @else
                                Maximale Dateigröße: 100 MB
                            @endif
                        </p>
                        @error('model_file')
                            <p class="mt-2 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="rounded-[1.5rem] border border-base-content/10 bg-base-100/78 p-4">
                        <label for="thumbnail" class="label label-text font-semibold">
                            {{ $this->isEditing ? 'Neues Vorschaubild (optional)' : 'Vorschaubild (optional)' }}
                        </label>
                        <input type="file" wire:model="thumbnail" id="thumbnail" class="file-input file-input-bordered w-full" accept="image/jpeg,image/png,image/webp" data-testid="thumbnail-input" />
                        <p class="mt-2 text-sm text-base-content/62">
                            @if ($this->isEditing && $this->existingModel?->thumbnail_path)
                                Aktuelles Vorschaubild vorhanden. Nur hochladen, wenn es ersetzt werden soll.
                            @else
                                Maximale Dateigröße: 2 MB (JPG, PNG, WebP)
                            @endif
                        </p>
                        @error('thumbnail')
                            <p class="mt-2 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex flex-wrap justify-end gap-2 border-t border-base-content/10 pt-6">
                    <x-button label="Abbrechen" link="{{ route('3d-modelle.index') }}" wire:navigate class="btn-ghost" />
                    <x-button label="{{ $this->isEditing ? 'Speichern' : 'Hochladen' }}" type="submit" icon="{{ $this->isEditing ? 'o-check' : 'o-arrow-up-tray' }}" class="btn-primary" wire:loading.attr="disabled" data-testid="submit-button" />
                </div>
            </form>
        </x-ui.panel>

        <div class="space-y-6 xl:sticky xl:top-6">
            <x-ui.panel title="Was gute Modelle brauchen" description="Die wichtigsten Metadaten sollten auch ohne Download sofort verständlich sein.">
                <ul class="grid gap-3 text-sm leading-relaxed text-base-content/76 sm:text-base">
                    <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Nutze einen klaren Namen, der im Mitgliederbereich und in der Detailansicht sofort funktioniert.</li>
                    <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Die Beschreibung sollte Aufbau, Einsatz oder Motiv des Modells in ein bis zwei Sätzen erklären.</li>
                    <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Ein Maddraxikon-Link stärkt Kontext und Auffindbarkeit, ist aber optional.</li>
                </ul>
            </x-ui.panel>

            <x-ui.panel title="Baxx und Freischaltung" description="Der Preis steuert Sichtbarkeit in der Bibliothek und den Freischaltfluss auf der Detailseite.">
                <div class="grid gap-3 text-sm leading-relaxed text-base-content/76 sm:text-base">
                    <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                        <p class="font-medium text-base-content">Niedrige Werte</p>
                        <p class="mt-1">Geeignet für kleinere Extras oder leichtgewichtige Modelle.</p>
                    </div>
                    <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                        <p class="font-medium text-base-content">Höhere Werte</p>
                        <p class="mt-1">Sinnvoll für besonders aufwendige oder exklusive Modelle mit Sammlerwert.</p>
                    </div>
                </div>
            </x-ui.panel>
        </div>
    </section>
</x-member-page>
