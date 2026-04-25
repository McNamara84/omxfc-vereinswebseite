<x-member-page class="max-w-4xl">
    <x-card>
        <x-header :title="$this->isEditing ? 'Angebot bearbeiten' : 'Neues Angebot erstellen'" separator useH1 data-testid="page-title" />
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
    </x-card>
</x-member-page>
