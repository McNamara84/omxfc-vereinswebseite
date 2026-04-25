<x-member-page class="max-w-4xl">
    <x-card>
        <x-header :title="$this->isEditing ? 'Stapel-Angebot bearbeiten' : 'Stapel-Angebot erstellen'" separator useH1 data-testid="page-title" />
        <p class="text-base-content mb-6">
            Mit einem Stapel-Angebot kannst du viele Romane auf einmal einstellen. Gib einfach die Nummern als Bereiche (z.B. 1-50) oder einzeln (z.B. 52, 55) ein.
        </p>
        <form wire:submit="save" id="bundle-offer-form">
            @php
                $seriesOptions = $this->seriesOptions;
                $conditionOptions = $this->conditionOptions;
                $conditionMaxOptions = $this->conditionMaxOptions;
                $existingPhotos = $this->existingPhotos;
                $maxNewPhotos = $this->maxNewPhotos;
                $displayPhotos = collect($existingPhotos)->map(fn ($path) => [
                    'path' => $path,
                    'marked_for_removal' => in_array($path, $remove_photos),
                ]);
            @endphp

            @if(session('error'))
                <x-alert title="Fehler" :description="session('error')" icon="o-x-circle" class="alert-error mb-4" />
            @endif

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

                    <script>
                        window.MAX_RANGE_SPAN = {{ App\Services\Romantausch\BundleService::MAX_RANGE_SPAN }};
                        window.COMPACT_THRESHOLD = {{ config('romantausch.compact_threshold', 20) }};
                    </script>

                    <div x-data="bundlePreview()">
                        <x-input
                            id="book-numbers-input"
                            name="book_numbers"
                            label="Roman-Nummern"
                            placeholder="z.B. 1-50, 52, 55-100"
                            hint="Gib Nummern einzeln (1, 5, 7) oder als Bereich (1-50) an, getrennt durch Kommas."
                            error-field="book_numbers"
                            wire:model="book_numbers"
                            x-model="input"
                            x-init="input = $wire.get('book_numbers') || ''; parseNumbers()"
                            @input.debounce.300ms="parseNumbers()"
                        />
                        <div x-show="numbers.length > 0" x-cloak class="mt-3 p-3 bg-base-200 rounded-lg">
                            <p class="text-sm font-medium text-base-content">
                                <span x-text="numbers.length"></span> Romane erkannt
                            </p>
                            <p class="text-xs text-base-content mt-1 max-h-20 overflow-y-auto" x-text="formatPreview()"></p>
                        </div>
                        <div x-show="input && numbers.length === 0" x-cloak class="mt-3 p-3 bg-warning/10 border border-warning/30 rounded-lg">
                            <p class="text-sm text-warning-content">Keine gültigen Nummern erkannt. Bitte überprüfe deine Eingabe.</p>
                        </div>
                    </div>

                    <div>
                        <label class="fieldset-legend">Zustandsbereich</label>
                        <div class="grid grid-cols-2 gap-3">
                            <x-form-select
                                id="condition-min"
                                name="condition"
                                label="Von (bester Zustand)"
                                aria-label="Von (bester Zustand)"
                                :options="$conditionOptions"
                                :value="$condition"
                                error-field="condition"
                                wire:model="condition"
                            />
                            <x-form-select
                                id="condition-max"
                                name="condition_max"
                                label="Bis (schlechtester)"
                                aria-label="Bis (schlechtester)"
                                :options="$conditionMaxOptions"
                                :value="$condition_max"
                                error-field="condition_max"
                                wire:model="condition_max"
                            />
                        </div>
                        @error('condition')
                            <p class="mt-2 text-sm text-error" role="alert">{{ $message }}</p>
                        @enderror
                        @error('condition_max')
                            <p class="mt-2 text-sm text-error" role="alert">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-base-content">
                            Bei gemischten Zuständen gibst du den Bereich an, z.B. „Z1 bis Z2".
                        </p>
                    </div>
                </div>

                <div class="md:col-span-1 space-y-6">
                    @if($displayPhotos->isNotEmpty())
                        <fieldset class="border border-base-content/10 rounded-lg p-4">
                            <legend class="text-sm font-semibold text-base-content">Vorhandene Fotos</legend>
                            <p class="text-sm text-base-content mb-3">Markiere Fotos, die du entfernen möchtest.</p>
                            <ul class="grid gap-4 sm:grid-cols-2" aria-live="polite">
                                @foreach($displayPhotos as $index => $photo)
                                    <li class="flex flex-col rounded-lg overflow-hidden border border-base-content/10 bg-base-200">
                                        <img src="{{ Storage::disk('public')->url($photo['path']) }}" alt="Foto {{ $loop->iteration }} des Stapels" class="h-32 w-full object-cover">
                                        <label for="remove-photo-{{ $index }}" class="flex items-center gap-2 px-3 py-2 text-sm text-base-content">
                                            <input type="checkbox" id="remove-photo-{{ $index }}" wire:model="remove_photos" value="{{ $photo['path'] }}" @checked($photo['marked_for_removal']) class="checkbox checkbox-primary checkbox-sm">
                                            <span>Foto entfernen</span>
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        </fieldset>
                    @endif

                    <div>
                        <label for="photos" class="fieldset-legend">Fotos (optional)</label>
                        <p id="photos-help" class="text-sm text-base-content mb-2">
                            Du kannst bis zu {{ $maxNewPhotos }} neue Fotos hinzufügen. Insgesamt maximal 3 Fotos.
                        </p>
                        <p id="photos-size" class="text-xs text-base-content mb-4">
                            Unterstützte Formate: JPG, JPEG, PNG, GIF, WebP. Max. 2 MB pro Foto.
                        </p>
                        <input
                            type="file"
                            wire:model="photos"
                            id="photos"
                            multiple
                            accept="image/*"
                            @class([
                                'file-input file-input-bordered w-full',
                                'file-input-error' => $errors->has('photos') || $errors->has('photos.*'),
                            ])
                            aria-describedby="photos-help photos-size"
                        />
                        @error('photos')
                            <p class="text-sm text-error mt-1" role="alert">{{ $message }}</p>
                        @enderror
                        @error('photos.*')
                            <p class="text-sm text-error mt-1" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-alert icon="o-light-bulb" class="alert-info">
                        <x-slot:title>Tipp</x-slot:title>
                        <ul class="text-sm space-y-1 list-disc list-inside">
                            <li>Alle Romane im Stapel können einzeln getauscht werden</li>
                            <li>Andere Mitglieder sehen, welche ihrer Gesuche zu deinem Stapel passen</li>
                            <li>Du kannst den Stapel später bearbeiten und Romane hinzufügen/entfernen</li>
                        </ul>
                    </x-alert>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
                <x-button :label="$this->isEditing ? 'Änderungen speichern' : 'Stapel-Angebot erstellen'" type="submit" class="btn-primary" icon="o-check" spinner="save" />
                <x-button label="Abbrechen" link="{{ route('romantausch.index') }}" wire:navigate class="btn-ghost" />
                @if($this->isEditing)
                    <x-button label="Stapel löschen" wire:click="delete" wire:confirm="Möchtest du diesen Stapel wirklich löschen?" class="btn-error" icon="o-trash" spinner="delete" />
                @endif
            </div>
        </form>
    </x-card>
</x-member-page>

@assets
    @vite(['resources/js/romantausch-bundle-preview.js'])
@endassets
