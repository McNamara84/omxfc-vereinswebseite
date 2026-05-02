<x-member-page class="max-w-6xl space-y-8">
    <x-ui.page-header
        :title="$this->isEditing ? 'Angebot bearbeiten' : 'Neues Angebot erstellen'"
        eyebrow="Romantauschbörse"
        :description="$this->isEditing ? 'Passe Serie, Roman, Zustand oder Fotos deines bestehenden Einzelangebots an.' : 'Erstelle ein einzelnes Angebot mit Roman, Zustand und optionalen Fotos für bessere Sichtbarkeit.'"
        data-testid="page-title"
    >
        <x-slot:actions>
            <x-button label="Zurück zur Übersicht" link="{{ route('romantausch.index') }}" wire:navigate icon="o-arrow-left" class="btn-ghost" />
        </x-slot:actions>
    </x-ui.page-header>

    <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,0.72fr)] xl:items-start">
        <x-ui.panel title="Einzelangebot" description="Alle Pflichtfelder werden direkt via Livewire validiert. Bereits vorhandene Fotos kannst du beim Bearbeiten gezielt entfernen.">
            <form wire:submit="save" id="offer-form">
            @php
                $seriesOptions = $this->seriesOptions;
                $bookOptions = $this->bookOptions;
                $conditionOptions = $this->conditionOptions;
                $existingPhotos = $this->existingPhotos;
                $maxNewPhotos = $this->maxNewPhotos;
                $displayPhotos = collect($existingPhotos)->map(fn ($path) => [
                    'path' => $path,
                    'marked_for_removal' => in_array($path, $remove_photos),
                ]);
                $booksBySeries = $this->booksBySeries;
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
                        label="Zustand"
                        aria-label="Zustand"
                        :options="$conditionOptions"
                        :value="$condition"
                        error-field="condition"
                        wire:model="condition"
                    />
                </div>

                <div class="md:col-span-1 space-y-6">
                    @if($displayPhotos->isNotEmpty())
                        <fieldset class="border border-base-content/10 rounded-lg p-4">
                            <legend class="text-sm font-semibold text-base-content">Vorhandene Fotos</legend>
                            <p class="text-sm text-base-content mb-3">Markiere Fotos, die du entfernen möchtest. Sie werden beim Speichern gelöscht.</p>
                            <ul class="grid gap-4 sm:grid-cols-2" aria-live="polite">
                                @foreach($displayPhotos as $index => $photo)
                                    <li class="flex flex-col rounded-lg overflow-hidden border border-base-content/10 bg-base-200">
                                        <img src="{{ Storage::disk('public')->url($photo['path']) }}" alt="Foto {{ $loop->iteration }} des Angebots" class="h-32 w-full object-cover">
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
                        <label for="photos" class="fieldset-legend">Neue Fotos hochladen</label>
                        <p id="photos-help" class="text-sm text-base-content mb-2">Du kannst bis zu {{ $maxNewPhotos }} neue Fotos hinzufügen. Insgesamt sind maximal drei Fotos erlaubt.</p>
                        <p id="photos-size" class="text-xs text-base-content mb-4">Unterstützte Dateiformate: JPG, JPEG, PNG, GIF und WebP. Die maximale Dateigröße beträgt 2&nbsp;MB pro Foto.</p>

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
                </div>
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
                <x-button :label="$this->isEditing ? 'Änderungen speichern' : 'Angebot speichern'" type="submit" class="btn-primary" icon="o-check" spinner="save" />
                <x-button label="Abbrechen" link="{{ route('romantausch.index') }}" wire:navigate class="btn-ghost" />
            </div>

            <div data-romantausch-books-by-series="{{ json_encode($booksBySeries) }}" class="hidden"></div>
            </form>
        </x-ui.panel>

        <div class="space-y-6 xl:sticky xl:top-6">
            <x-ui.panel title="Was ein gutes Angebot ausmacht" description="Je klarer das Angebot, desto leichter wird es von passenden Gesuchen gefunden.">
                <ul class="grid gap-3 text-sm leading-relaxed text-base-content/76 sm:text-base">
                    <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Wähle die richtige Serie und den exakten Roman, damit Matching und automatische Titelauflösung sauber funktionieren.</li>
                    <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Der Zustand sollte realistisch sein. Davon hängt ab, ob andere Mitglieder dein Angebot als passend betrachten.</li>
                    <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">Fotos sind optional, erhöhen aber die Aussagekraft besonders bei älteren oder gemischten Zuständen.</li>
                </ul>
            </x-ui.panel>

            <x-ui.panel title="Baxx-Regel" description="Die Börse vergibt zusätzliche Motivation über kleine, nachvollziehbare Belohnungen.">
                <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-4 text-sm leading-relaxed text-base-content/76 sm:text-base">
                    Für jedes zehnte eingestellte Angebot gibt es automatisch einen Bakk. Bei bestätigten Tauschaktionen erhalten beide Parteien zusätzlich Baxx.
                </div>
            </x-ui.panel>
        </div>
    </section>
</x-member-page>
