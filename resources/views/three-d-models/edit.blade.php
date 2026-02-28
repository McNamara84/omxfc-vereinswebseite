<x-app-layout>
    <x-member-page class="max-w-2xl">
        <x-header title="3D-Modell bearbeiten" separator data-testid="page-header" />

        <x-card>
            <form method="POST" action="{{ route('3d-modelle.update', $model) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="space-y-4">
                    <div>
                        <label for="name" class="label label-text font-semibold">Name</label>
                        <input type="text" name="name" id="name"
                            value="{{ old('name', $model->name) }}"
                            class="input input-bordered w-full" required placeholder="z.B. Euphoriewurm"
                            data-testid="name-input" />
                        @error('name')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="description" class="label label-text font-semibold">Beschreibung</label>
                        <textarea name="description" id="description" rows="3"
                            class="textarea textarea-bordered w-full" required
                            placeholder="Kurze Beschreibung des 3D-Modells..."
                            data-testid="description-input">{{ old('description', $model->description) }}</textarea>
                        @error('description')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="cost_baxx" class="label label-text font-semibold">Preis in Baxx</label>
                        <input type="number" name="cost_baxx" id="cost_baxx"
                            value="{{ old('cost_baxx', $model->reward?->cost_baxx ?? 10) }}" min="1" max="1000"
                            class="input input-bordered w-full" required
                            data-testid="baxx-input" />
                        @error('cost_baxx')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="maddraxikon_url" class="label label-text font-semibold">Maddraxikon-Link (optional)</label>
                        <input type="url" name="maddraxikon_url" id="maddraxikon_url"
                            value="{{ old('maddraxikon_url', $model->maddraxikon_url) }}"
                            class="input input-bordered w-full"
                            placeholder="https://maddraxikon.de/..."
                            data-testid="maddraxikon-url-input" />
                        <p class="text-sm text-base-content/60 mt-1">Link zum entsprechenden Artikel im Maddraxikon</p>
                        @error('maddraxikon_url')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="model_file" class="label label-text font-semibold">Neue 3D-Datei (optional)</label>
                        <input type="file" name="model_file" id="model_file"
                            class="file-input file-input-bordered w-full"
                            accept=".stl,.obj,.fbx"
                            data-testid="file-input" />
                        <p class="text-sm text-base-content/60 mt-1">
                            Aktuelle Datei: {{ strtoupper($model->file_format) }} ({{ $model->file_size_formatted }}).
                            Nur hochladen, wenn die Datei ersetzt werden soll.
                        </p>
                        @error('model_file')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="thumbnail" class="label label-text font-semibold">Neues Vorschaubild (optional)</label>
                        <input type="file" name="thumbnail" id="thumbnail"
                            class="file-input file-input-bordered w-full"
                            accept="image/jpeg,image/png,image/webp"
                            data-testid="thumbnail-input" />
                        <p class="text-sm text-base-content/60 mt-1">
                            @if ($model->thumbnail_path)
                                Aktuelles Vorschaubild vorhanden. Nur hochladen, wenn es ersetzt werden soll.
                            @else
                                Kein Vorschaubild vorhanden. Maximale Dateigröße: 2 MB (JPG, PNG, WebP)
                            @endif
                        </p>
                        @error('thumbnail')
                            <p class="text-error text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <x-button label="Abbrechen" link="{{ route('3d-modelle.index') }}" class="btn-ghost" />
                    <x-button label="Speichern" type="submit" icon="o-check" class="btn-primary"
                        data-testid="submit-button" />
                </div>
            </form>
        </x-card>
    </x-member-page>
</x-app-layout>
