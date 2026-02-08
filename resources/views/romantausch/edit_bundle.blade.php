<x-app-layout>
    <x-member-page class="max-w-4xl">
        <x-header title="Stapel-Angebot bearbeiten" separator useH1 data-testid="page-title" />

        <x-card>
            @php
                $firstOffer = $offers->first();
                $bookNumbersInput = old('book_numbers', $bookNumbersString);
                $selectedCondition = old('condition', $firstOffer->condition);
                $selectedConditionMax = old('condition_max', $firstOffer->condition_max ?? '');
                $existingPhotos = collect($firstOffer->photos ?? []);
                $removePhotos = collect(old('remove_photos', []));
                $displayPhotos = $existingPhotos->map(fn ($path) => [
                    'path' => $path,
                    'marked_for_removal' => $removePhotos->contains($path),
                ]);
                $keptPhotosCount = $displayPhotos->reject(fn ($photo) => $photo['marked_for_removal'])->count();
                $maxNewPhotos = max(0, 3 - $keptPhotosCount);
                $bookNumbersError = $errors->first('book_numbers');
                $conditionError = $errors->first('condition');
                $photoError = $errors->first('photos');
                $photoItemErrors = $errors->get('photos.*');
                $photosErrorMessage = $photoError ?: (count($photoItemErrors) > 0 ? implode(' ', $photoItemErrors) : null);
            @endphp

            <div class="mb-6 p-4 bg-base-200 rounded-lg">
                <p class="text-sm text-base-content/60">
                    <strong>Serie:</strong> {{ $firstOffer->series }}<br>
                    <strong>Aktuell:</strong> {{ $offers->count() }} Romane
                </p>
            </div>

            <form action="{{ route('romantausch.update-bundle', $bundleId) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                @if(session('error'))
                    <x-alert title="Fehler" :description="session('error')" icon="o-x-circle" class="alert-error mb-4" />
                @endif

                <script>
                    window.MAX_RANGE_SPAN = {{ App\Services\Romantausch\BundleService::MAX_RANGE_SPAN }};
                    window.COMPACT_THRESHOLD = {{ config('romantausch.compact_threshold', 20) }};
                </script>

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="md:col-span-1 space-y-4">
                        {{-- Roman-Nummern --}}
                        <div x-data="bundlePreview()">
                            <label for="book-numbers-input" class="label label-text">Roman-Nummern</label>
                            <input
                                type="text"
                                name="book_numbers"
                                id="book-numbers-input"
                                x-model="input"
                                x-init="input = $el.getAttribute('value') || input; parseNumbers()"
                                @input.debounce.300ms="parseNumbers()"
                                placeholder="z.B. 1-50, 52, 55-100"
                                value="{{ $bookNumbersInput }}"
                                @class([
                                    'input input-bordered w-full',
                                    'input-error' => $bookNumbersError,
                                ])
                                aria-describedby="book-numbers-help{{ $bookNumbersError ? ' book-numbers-error' : '' }}"
                                @if($bookNumbersError) aria-invalid="true" @endif
                            >
                            <p id="book-numbers-help" class="mt-1 text-sm text-base-content/50">
                                Gib Nummern einzeln (1, 5, 7) oder als Bereich (1-50) an, getrennt durch Kommas.
                            </p>
                            @error('book_numbers')
                                <p id="book-numbers-error" class="mt-2 text-sm text-error" role="alert">{{ $message }}</p>
                            @enderror

                            {{-- Vorschau --}}
                            <div x-show="numbers.length > 0" x-cloak class="mt-3 p-3 bg-base-200 rounded-lg">
                                <p class="text-sm font-medium text-base-content">
                                    <span x-text="numbers.length"></span> Romane erkannt
                                </p>
                                <p class="text-xs text-base-content/60 mt-1 max-h-20 overflow-y-auto" x-text="formatPreview()"></p>
                            </div>
                        </div>

                        {{-- Zustandsbereich --}}
                        <div>
                            <label class="label label-text">Zustandsbereich</label>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="condition-min" class="block text-sm text-base-content/60 mb-1">Von (bester Zustand)</label>
                                    <select
                                        name="condition"
                                        id="condition-min"
                                        @class([
                                            'select select-bordered w-full',
                                            'select-error' => $conditionError,
                                        ])
                                    >
                                        <x-condition-select-options :selected="$selectedCondition" />
                                    </select>
                                </div>
                                <div>
                                    <label for="condition-max" class="block text-sm text-base-content/60 mb-1">Bis (schlechtester)</label>
                                    <select
                                        name="condition_max"
                                        id="condition-max"
                                        class="select select-bordered w-full"
                                    >
                                        <x-condition-select-options :selected="$selectedConditionMax" :include-empty="true" :include-worst="true" />
                                    </select>
                                </div>
                            </div>
                            @error('condition')
                                <p class="mt-2 text-sm text-error" role="alert">{{ $message }}</p>
                            @enderror
                            @error('condition_max')
                                <p class="mt-2 text-sm text-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Fotos --}}
                    <div class="md:col-span-1 space-y-6">
                        @if($displayPhotos->isNotEmpty())
                            <fieldset class="border border-base-content/10 rounded-lg p-4">
                                <legend class="text-sm font-semibold text-base-content">Vorhandene Fotos</legend>
                                <p class="text-sm text-base-content/60 mb-3">Markiere Fotos, die du entfernen möchtest.</p>
                                <ul class="grid gap-4 sm:grid-cols-2">
                                    @foreach($displayPhotos as $index => $photo)
                                        <li class="flex flex-col rounded-lg overflow-hidden border border-base-content/10 bg-base-200">
                                            <img src="{{ Storage::disk('public')->url($photo['path']) }}" alt="Foto {{ $loop->iteration }} des Stapels" class="h-32 w-full object-cover">
                                            <label for="remove-photo-{{ $index }}" class="flex items-center gap-2 px-3 py-2 text-sm text-base-content">
                                                <input type="checkbox" id="remove-photo-{{ $index }}" name="remove_photos[]" value="{{ $photo['path'] }}" @checked($photo['marked_for_removal']) class="checkbox checkbox-primary checkbox-sm">
                                                <span>Entfernen</span>
                                            </label>
                                        </li>
                                    @endforeach
                                </ul>
                            </fieldset>
                        @endif

                        <div>
                            <label for="photos" class="label label-text">Neue Fotos hinzufügen</label>
                            <p class="text-sm text-base-content/60 mb-2">
                                Du kannst bis zu {{ $maxNewPhotos }} neue Fotos hinzufügen. Insgesamt max. 3 Fotos.
                            </p>
                            <input
                                type="file"
                                name="photos[]"
                                id="photos"
                                multiple
                                accept="image/*"
                                @class([
                                    'file-input file-input-bordered w-full',
                                    'file-input-error' => $photosErrorMessage,
                                ])
                                @if($photosErrorMessage) aria-invalid="true" @endif
                            >
                            @if($photosErrorMessage)
                                <p class="mt-2 text-sm text-error" role="alert">{{ $photosErrorMessage }}</p>
                            @endif
                        </div>

                        {{-- Warnhinweis --}}
                        <x-alert icon="o-exclamation-triangle" class="alert-warning">
                            <x-slot:title>Hinweis</x-slot:title>
                            <p class="text-sm">
                                Wenn du Romane entfernst, die bereits zu einem Match gehören, wird das Match ebenfalls gelöscht.
                            </p>
                        </x-alert>
                    </div>
                </div>

                <div class="mt-8 flex flex-wrap gap-3 justify-between">
                    <div class="flex flex-wrap gap-3">
                        <x-button label="Änderungen speichern" type="submit" class="btn-primary" icon="o-check" />
                        <x-button label="Abbrechen" link="{{ route('romantausch.index') }}" class="btn-ghost" />
                    </div>
                </div>
            </form>

            {{-- Löschen-Formular --}}
            <form action="{{ route('romantausch.delete-bundle', $bundleId) }}" method="POST" class="mt-4" onsubmit="return confirm('Möchtest du wirklich den gesamten Stapel mit {{ $offers->count() }} Romanen löschen?');">
                @csrf
                @method('DELETE')
                <x-button label="Stapel löschen" type="submit" class="btn-error" icon="o-trash" />
            </form>
        </x-card>
    </x-member-page>
</x-app-layout>

@vite(['resources/js/romantausch-bundle-preview.js'])
