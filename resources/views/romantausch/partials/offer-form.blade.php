<x-card>
    <x-header :title="$heading" separator />
    <form action="{{ $formAction }}" method="POST" enctype="multipart/form-data" id="offer-form">
        @csrf
        @if($formMethod !== 'POST')
            @method($formMethod)
        @endif

        @php
            $selectedSeries = old('series', optional($offer)->series ?? ($types[0]->value ?? ''));
            $selectedBookNumber = old('book_number', optional($offer)->book_number ?? null);
            $selectedCondition = old('condition', optional($offer)->condition ?? 'Z0');
            $seriesError = $errors->first('series');
            $bookNumberError = $errors->first('book_number');
            $conditionError = $errors->first('condition');
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
        @endphp

        @if(session('error'))
            <x-alert title="Fehler" :description="session('error')" icon="o-x-circle" class="alert-error mb-4" />
        @endif

        <div class="grid gap-6 md:grid-cols-2">
            <div class="md:col-span-1 space-y-4">
                <div>
                    <label for="series-select" class="label label-text">Serie</label>
                    <select
                        name="series"
                        id="series-select"
                        @class([
                            'select select-bordered w-full',
                            'select-error' => $seriesError,
                        ])
                        @if($seriesError)
                            aria-invalid="true"
                            aria-describedby="series-error"
                        @endif
                    >
                        @foreach($types as $type)
                            <option value="{{ $type->value }}" @selected($selectedSeries === $type->value)>{{ $type->value }}</option>
                        @endforeach
                    </select>
                    @error('series')
                        <p id="series-error" class="text-sm text-error mt-1" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="book-select" class="label label-text">Roman</label>
                    <select
                        name="book_number"
                        id="book-select"
                        @class([
                            'select select-bordered w-full',
                            'select-error' => $bookNumberError,
                        ])
                        @if($bookNumberError)
                            aria-invalid="true"
                            aria-describedby="book_number-error"
                        @endif
                    >
                        @foreach($books as $book)
                            <option
                                value="{{ $book->roman_number }}"
                                data-series="{{ $book->type->value }}"
                                @selected((string) $selectedBookNumber === (string) $book->roman_number)
                            >
                                {{ $book->roman_number }} - {{ $book->title }}
                            </option>
                        @endforeach
                    </select>
                    @error('book_number')
                        <p id="book_number-error" class="text-sm text-error mt-1" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="condition-select" class="label label-text">Zustand</label>
                    <select
                        name="condition"
                        id="condition-select"
                        @class([
                            'select select-bordered w-full',
                            'select-error' => $conditionError,
                        ])
                        @if($conditionError)
                            aria-invalid="true"
                            aria-describedby="condition-error"
                        @endif
                    >
                        <option value="Z0" @selected($selectedCondition === 'Z0')>Z0 - Druckfrisch (Top Zustand)</option>
                        <option value="Z0-1" @selected($selectedCondition === 'Z0-1')>Z0-1 - Druckfrisch, minimale Mängel</option>
                        <option value="Z1" @selected($selectedCondition === 'Z1')>Z1 - Sehr gut, Kleinstfehler</option>
                        <option value="Z1-2" @selected($selectedCondition === 'Z1-2')>Z1-2 - Sehr gut, leichte Gebrauchsspuren</option>
                        <option value="Z2" @selected($selectedCondition === 'Z2')>Z2 - Gut, kleine Mängel</option>
                        <option value="Z2-3" @selected($selectedCondition === 'Z2-3')>Z2-3 - Gut, stärker gebraucht</option>
                        <option value="Z3" @selected($selectedCondition === 'Z3')>Z3 - Deutlich gebraucht</option>
                        <option value="Z3-4" @selected($selectedCondition === 'Z3-4')>Z3-4 - Sehr stark gebraucht</option>
                        <option value="Z4" @selected($selectedCondition === 'Z4')>Z4 - Sehr schlecht erhalten</option>
                    </select>
                    @error('condition')
                        <p id="condition-error" class="text-sm text-error mt-1" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="md:col-span-1 space-y-6">
                @if($displayPhotos->isNotEmpty())
                    <fieldset class="border border-base-content/10 rounded-lg p-4">
                        <legend class="text-sm font-semibold text-base-content">Vorhandene Fotos</legend>
                        <p class="text-sm text-base-content/60 mb-3">Markiere Fotos, die du entfernen möchtest. Sie werden beim Speichern gelöscht.</p>
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
                    <label for="photos" data-dropzone-label class="label label-text">Neue Fotos hochladen</label>
                    <p id="photos-help" class="text-sm text-base-content/60 mb-2">Du kannst bis zu {{ $maxNewPhotos }} neue Fotos hinzufügen. Insgesamt sind maximal drei Fotos erlaubt.</p>
                    <p id="photos-size" class="text-xs text-base-content/50 mb-4">Unterstützte Dateiformate: JPG, JPEG, PNG, GIF und WebP. Die maximale Dateigröße beträgt 2&nbsp;MB pro Foto.</p>

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
                                <span class="text-sm text-base-content/60">
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
                                class="text-sm text-base-content/60"
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
    </form>
</x-card>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const seriesSelect = document.getElementById('series-select');
            const bookSelect = document.getElementById('book-select');

            function filterBooks() {
                const series = seriesSelect.value;
                let firstVisibleIndex = -1;
                let hasVisibleSelection = false;
                Array.from(bookSelect.options).forEach((option, idx) => {
                    const match = option.dataset.series === series;
                    option.hidden = !match;
                    option.disabled = !match;
                    if (match) {
                        if (firstVisibleIndex === -1) {
                            firstVisibleIndex = idx;
                        }
                        if (option.selected) {
                            hasVisibleSelection = true;
                        }
                    }
                });
                if (!hasVisibleSelection && firstVisibleIndex !== -1) {
                    bookSelect.selectedIndex = firstVisibleIndex;
                }
            }

            filterBooks();
            seriesSelect.addEventListener('change', filterBooks);
        });
    </script>
@endpush
