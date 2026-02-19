<x-card>
    <x-header :title="$heading" separator useH1 data-testid="page-title" />
    <form action="{{ $formAction }}" method="POST" enctype="multipart/form-data" id="offer-form">
        @csrf
        @if($formMethod !== 'POST')
            @method($formMethod)
        @endif

        @php
            $selectedSeries = old('series', optional($offer)->series ?? ($types[0]->value ?? ''));
            $selectedBookNumber = old('book_number', optional($offer)->book_number ?? null);
            $selectedCondition = old('condition', optional($offer)->condition ?? 'Z0');
            $photoError = $errors->first('photos');
            $photoItemError = $errors->first('photos.*');
            $photosErrorMessage = $photoError ?: $photoItemError;
            $existingPhotos = collect(optional($offer)->photos ?? []);
            $removePhotos = collect(old('remove_photos', []));
            $displayPhotos = $existingPhotos->map(fn ($path) => [
                'path' => $path,
                'marked_for_removal' => $removePhotos->contains($path),
            ]);
            $keptPhotosCount = $displayPhotos->reject(fn ($photo) => $photo['marked_for_removal'])->count();
            $maxNewPhotos = max(0, 3 - $keptPhotosCount);

            $seriesOptions = collect($types)->map(fn($t) => ['id' => $t->value, 'name' => $t->value])->toArray();
            $bookOptions = $books->map(fn($b) => ['id' => $b->roman_number, 'name' => $b->roman_number . ' - ' . $b->title])->toArray();
            $conditionOptions = \App\Support\ConditionOptions::full();

            $booksBySeries = $books->groupBy(fn($b) => $b->type->value)
                ->map(fn($group) => $group->pluck('roman_number')->map(fn($n) => (string) $n)->values());
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
                    :value="$selectedSeries"
                    error-field="series"
                />

                <x-form-select
                    id="book-select"
                    name="book_number"
                    label="Roman"
                    aria-label="Roman"
                    :options="$bookOptions"
                    :value="$selectedBookNumber"
                    error-field="book_number"
                />

                <x-form-select
                    id="condition-select"
                    name="condition"
                    label="Zustand"
                    aria-label="Zustand"
                    :options="$conditionOptions"
                    :value="$selectedCondition"
                    error-field="condition"
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
                                        <input type="checkbox" id="remove-photo-{{ $index }}" name="remove_photos[]" value="{{ $photo['path'] }}" @checked($photo['marked_for_removal']) class="checkbox checkbox-primary checkbox-sm">
                                        <span>Foto entfernen</span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </fieldset>
                @endif

                <div>
                    <label for="photos" data-dropzone-label class="fieldset-legend">Neue Fotos hochladen</label>
                    <p id="photos-help" class="text-sm text-base-content mb-2">Du kannst bis zu {{ $maxNewPhotos }} neue Fotos hinzufügen. Insgesamt sind maximal drei Fotos erlaubt.</p>
                    <p id="photos-size" class="text-xs text-base-content mb-4">Unterstützte Dateiformate: JPG, JPEG, PNG, GIF und WebP. Die maximale Dateigröße beträgt 2&nbsp;MB pro Foto.</p>

                    <div
                        data-romantausch-dropzone
                        data-max-files="{{ $maxNewPhotos }}"
                        class="space-y-4"
                    >
                        <div data-dropzone-ui class="hidden space-y-3">
                            <div
                                data-dropzone-area
                                role="button"
                                tabindex="0"
                                aria-describedby="photos-help photos-size photos-status{{ $photosErrorMessage ? ' photos-error' : '' }}"
                                class="relative flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-base-content/20 bg-base-200 px-4 py-6 text-center transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 hover:border-primary hover:bg-base-100"
                            >
                                <span data-dropzone-instruction-text class="font-medium text-base-content">Ziehe deine Fotos hierher oder klicke, um sie auszuwählen.</span>
                                <span class="text-sm text-base-content">
                                    <span data-dropzone-counter>0</span>
                                    /
                                    <span data-dropzone-max="true">{{ $maxNewPhotos }}</span>
                                    Dateien ausgewählt
                                    (<span data-dropzone-remaining>{{ $maxNewPhotos }}</span> frei)
                                </span>
                            </div>

                            <div
                                id="photos-status"
                                data-dropzone-status
                                class="text-sm text-base-content"
                                aria-live="polite"
                                role="status"
                            ></div>

                            <ul
                                data-dropzone-previews
                                class="hidden grid gap-3 sm:grid-cols-2"
                                aria-label="Ausgewählte Fotos"
                            ></ul>
                        </div>

                        <div data-dropzone-fallback>
                            <input
                                type="file"
                                name="photos[]"
                                id="photos"
                                data-dropzone-input
                                multiple
                                accept="image/*"
                                @class([
                                    'file-input file-input-bordered w-full',
                                    'file-input-error' => $photosErrorMessage,
                                ])
                                aria-describedby="photos-help photos-size{{ $photosErrorMessage ? ' photos-error' : '' }}"
                                @if($photosErrorMessage)
                                    aria-invalid="true"
                                @endif
                            />
                        </div>
                    </div>

                    @if($photosErrorMessage)
                        <p id="photos-error" class="text-sm text-error mt-1" role="alert">{{ $photosErrorMessage }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="mt-8 flex flex-wrap gap-3">
            <x-button :label="$submitLabel" type="submit" class="btn-primary" icon="o-check" />
            <x-button label="Abbrechen" link="{{ route('romantausch.index') }}" class="btn-ghost" />
        </div>

        <div data-romantausch-books-by-series="{{ json_encode($booksBySeries) }}" class="hidden"></div>
    </form>
</x-card>
