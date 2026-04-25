<x-member-page class="max-w-2xl">
    <x-header title="{{ $this->isEditing ? '3D-Modell bearbeiten' : '3D-Modell hochladen' }}" separator data-testid="page-header">
        <x-slot:actions>
            <x-button label="Zurück" icon="o-arrow-left" link="{{ route('3d-modelle.index') }}" wire:navigate class="btn-ghost" />
        </x-slot:actions>
    </x-header>

    <x-card>
        <form wire:submit="save">
            <div class="space-y-4">
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
                    rows="3"
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

                <div>
                    <label for="model_file" class="label label-text font-semibold">
                        {{ $this->isEditing ? 'Neue 3D-Datei (optional)' : '3D-Datei (STL, OBJ oder FBX)' }}
                    </label>
                    <input type="file" wire:model="model_file" id="model_file"
                        class="file-input file-input-bordered w-full"
                        {{ $this->isEditing ? '' : 'required' }}
                        accept=".stl,.obj,.fbx"
                        data-testid="file-input" />
                    <p class="text-sm text-base-content/60 mt-1">
                        @if ($this->isEditing && $this->existingModel)
                            Aktuelle Datei: {{ strtoupper($this->existingModel->file_format) }} ({{ $this->existingModel->file_size_formatted }}).
                            Nur hochladen, wenn die Datei ersetzt werden soll.
                        @else
                            Maximale Dateigröße: 100 MB
                        @endif
                    </p>
                    @error('model_file')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="thumbnail" class="label label-text font-semibold">
                        {{ $this->isEditing ? 'Neues Vorschaubild (optional)' : 'Vorschaubild (optional)' }}
                    </label>
                    <input type="file" wire:model="thumbnail" id="thumbnail"
                        class="file-input file-input-bordered w-full"
                        accept="image/jpeg,image/png,image/webp"
                        data-testid="thumbnail-input" />
                    <p class="text-sm text-base-content/60 mt-1">
                        @if ($this->isEditing && $this->existingModel?->thumbnail_path)
                            Aktuelles Vorschaubild vorhanden. Nur hochladen, wenn es ersetzt werden soll.
                        @else
                            Maximale Dateigröße: 2 MB (JPG, PNG, WebP)
                        @endif
                    </p>
                    @error('thumbnail')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-6">
                <x-button label="Abbrechen" link="{{ route('3d-modelle.index') }}" wire:navigate class="btn-ghost" />
                <x-button label="{{ $this->isEditing ? 'Speichern' : 'Hochladen' }}" type="submit"
                    icon="{{ $this->isEditing ? 'o-check' : 'o-arrow-up-tray' }}" class="btn-primary"
                    wire:loading.attr="disabled" data-testid="submit-button" />
            </div>
        </form>
    </x-card>
</x-member-page>
