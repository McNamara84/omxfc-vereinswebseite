<x-app-layout>
    <x-member-page class="max-w-4xl">
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
            <h1 class="text-2xl font-bold text-[#8B0116] dark:text-[#FF6B81] mb-6">Stapel-Angebot bearbeiten</h1>

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
                $photoItemError = $errors->first('photos.*');
                $photosErrorMessage = $photoError ?: $photoItemError;
            @endphp

            <div class="mb-6 p-4 bg-gray-100 dark:bg-gray-700 rounded-lg">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    <strong>Serie:</strong> {{ $firstOffer->series }}<br>
                    <strong>Aktuell:</strong> {{ $offers->count() }} Romane
                </p>
            </div>

            <form action="{{ route('romantausch.update-bundle', $bundleId) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                @if(session('error'))
                    <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-800 dark:bg-red-800 dark:border-red-700 dark:text-red-100 rounded">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="md:col-span-1 space-y-4">
                        {{-- Roman-Nummern --}}
                        <div x-data="bundlePreview()">
                            <label for="book-numbers-input" class="block font-medium text-gray-700 dark:text-gray-200 mb-2">Roman-Nummern</label>
                            <input
                                type="text"
                                name="book_numbers"
                                id="book-numbers-input"
                                x-model="input"
                                @input.debounce.300ms="parseNumbers()"
                                placeholder="z.B. 1-50, 52, 55-100"
                                value="{{ $bookNumbersInput }}"
                                @class([
                                    'w-full px-3 py-2 rounded bg-gray-50 dark:bg-gray-700 border text-gray-800 dark:text-gray-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B0116] dark:focus-visible:ring-[#FF6B81]',
                                    'border-red-500 focus-visible:ring-red-500 dark:border-red-500' => $bookNumbersError,
                                    'border-gray-300 dark:border-gray-600' => !$bookNumbersError,
                                ])
                                aria-describedby="book-numbers-help{{ $bookNumbersError ? ' book-numbers-error' : '' }}"
                                @if($bookNumbersError) aria-invalid="true" @endif
                            >
                            <p id="book-numbers-help" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Gib Nummern einzeln (1, 5, 7) oder als Bereich (1-50) an, getrennt durch Kommas.
                            </p>
                            @error('book_numbers')
                                <p id="book-numbers-error" class="mt-2 text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
                            @enderror

                            {{-- Vorschau --}}
                            <div x-show="numbers.length > 0" x-cloak class="mt-3 p-3 bg-gray-100 dark:bg-gray-700 rounded-lg">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    <span x-text="numbers.length"></span> Romane erkannt
                                </p>
                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 max-h-20 overflow-y-auto" x-text="formatPreview()"></p>
                            </div>
                        </div>

                        {{-- Zustandsbereich --}}
                        <div>
                            <label class="block font-medium text-gray-700 dark:text-gray-200 mb-2">Zustandsbereich</label>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="condition-min" class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Von (bester Zustand)</label>
                                    <select
                                        name="condition"
                                        id="condition-min"
                                        @class([
                                            'w-full rounded bg-gray-50 dark:bg-gray-700 border text-gray-800 dark:text-gray-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B0116] dark:focus-visible:ring-[#FF6B81]',
                                            'border-red-500 focus-visible:ring-red-500 dark:border-red-500' => $conditionError,
                                            'border-gray-300 dark:border-gray-600' => !$conditionError,
                                        ])
                                    >
                                        <x-condition-select-options :selected="$selectedCondition" />
                                    </select>
                                </div>
                                <div>
                                    <label for="condition-max" class="block text-sm text-gray-600 dark:text-gray-400 mb-1">Bis (schlechtester)</label>
                                    <select
                                        name="condition_max"
                                        id="condition-max"
                                        class="w-full rounded bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B0116] dark:focus-visible:ring-[#FF6B81]"
                                    >
                                        <x-condition-select-options :selected="$selectedConditionMax" :include-empty="true" :include-worst="true" />
                                    </select>
                                </div>
                            </div>
                            @error('condition')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
                            @enderror
                            @error('condition_max')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Fotos --}}
                    <div class="md:col-span-1 space-y-6">
                        @if($displayPhotos->isNotEmpty())
                            <fieldset class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                                <legend class="text-sm font-semibold text-gray-700 dark:text-gray-200">Vorhandene Fotos</legend>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Markiere Fotos, die du entfernen möchtest.</p>
                                <ul class="grid gap-4 sm:grid-cols-2">
                                    @foreach($displayPhotos as $index => $photo)
                                        <li class="flex flex-col rounded-lg overflow-hidden border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700">
                                            <img src="{{ Storage::disk('public')->url($photo['path']) }}" alt="Foto {{ $loop->iteration }} des Stapels" class="h-32 w-full object-cover">
                                            <label for="remove-photo-{{ $index }}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-200">
                                                <input type="checkbox" id="remove-photo-{{ $index }}" name="remove_photos[]" value="{{ $photo['path'] }}" @checked($photo['marked_for_removal']) class="rounded border-gray-300 text-[#8B0116] focus:ring-[#8B0116] dark:bg-gray-800 dark:border-gray-500">
                                                <span>Entfernen</span>
                                            </label>
                                        </li>
                                    @endforeach
                                </ul>
                            </fieldset>
                        @endif

                        <div>
                            <label for="photos" class="block font-medium text-gray-700 dark:text-gray-200 mb-2">Neue Fotos hinzufügen</label>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                Du kannst bis zu {{ $maxNewPhotos }} neue Fotos hinzufügen. Insgesamt max. 3 Fotos.
                            </p>
                            <input
                                type="file"
                                name="photos[]"
                                id="photos"
                                multiple
                                accept="image/*"
                                @class([
                                    'w-full px-3 py-2 rounded bg-gray-50 dark:bg-gray-700 border text-gray-800 dark:text-gray-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B0116] dark:focus-visible:ring-[#FF6B81]',
                                    'border-red-500 focus-visible:ring-red-500 dark:border-red-500' => $photosErrorMessage,
                                    'border-gray-300 dark:border-gray-600' => !$photosErrorMessage,
                                ])
                                @if($photosErrorMessage) aria-invalid="true" @endif
                            >
                            @if($photosErrorMessage)
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400" role="alert">{{ $photosErrorMessage }}</p>
                            @endif
                        </div>

                        {{-- Warnhinweis --}}
                        <div class="p-4 bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 rounded-lg">
                            <h3 class="text-sm font-semibold text-yellow-800 dark:text-yellow-200 mb-2">⚠️ Hinweis</h3>
                            <p class="text-sm text-yellow-700 dark:text-yellow-300">
                                Wenn du Romane entfernst, die bereits zu einem Match gehören, wird das Match ebenfalls gelöscht.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex flex-wrap gap-3 justify-between">
                    <div class="flex flex-wrap gap-3">
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-[#8B0116] dark:bg-[#C41E3A] text-white font-semibold rounded hover:bg-[#A50019] dark:hover:bg-[#D63A4D] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B0116] dark:focus-visible:ring-[#FF6B81]">
                            Änderungen speichern
                        </button>
                        <a href="{{ route('romantausch.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-100 font-semibold rounded hover:bg-gray-300 dark:hover:bg-gray-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-gray-400">
                            Abbrechen
                        </a>
                    </div>
                </div>
            </form>

            {{-- Löschen-Formular --}}
            <form action="{{ route('romantausch.delete-bundle', $bundleId) }}" method="POST" class="mt-4" onsubmit="return confirm('Möchtest du wirklich den gesamten Stapel mit {{ $offers->count() }} Romanen löschen?');">
                @csrf
                @method('DELETE')
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-red-600 dark:bg-red-700 text-white font-semibold rounded hover:bg-red-700 dark:hover:bg-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-red-500">
                    Stapel löschen
                </button>
            </form>
        </div>
    </x-member-page>
</x-app-layout>

@push('scripts')
<script>
    // Konstanten aus dem Backend - WICHTIG: Muss mit RomantauschController::MAX_RANGE_SPAN übereinstimmen!
    const MAX_RANGE_SPAN = {{ App\Http\Controllers\RomantauschController::MAX_RANGE_SPAN }};

    /**
     * Alpine.js Komponente für die Bundle-Vorschau.
     * Hinweis: Gleiche Logik existiert auch in create_bundle_offer.blade.php
     * Für zukünftige Refaktorierung siehe resources/js/romantausch-bundle-preview.js
     */
    function bundlePreview() {
        return {
            input: {!! json_encode($bookNumbersInput) !!},
            numbers: [],

            init() {
                if (this.input) {
                    this.parseNumbers();
                }
            },

            parseNumbers() {
                const numbers = [];
                const parts = this.input.split(',');

                for (const part of parts) {
                    const trimmed = part.trim();
                    if (!trimmed) continue;

                    if (trimmed.includes('-')) {
                        const [startStr, endStr] = trimmed.split('-');
                        const start = parseInt(startStr.trim(), 10);
                        const end = parseInt(endStr.trim(), 10);

                        if (start > 0 && end > 0 && end >= start && (end - start) <= MAX_RANGE_SPAN) {
                            for (let i = start; i <= end; i++) {
                                numbers.push(i);
                            }
                        }
                    } else {
                        const num = parseInt(trimmed, 10);
                        if (num > 0) {
                            numbers.push(num);
                        }
                    }
                }

                this.numbers = [...new Set(numbers)].sort((a, b) => a - b);
            },

            formatPreview() {
                if (this.numbers.length === 0) return '';
                if (this.numbers.length <= 20) {
                    return this.numbers.join(', ');
                }

                // Kompakte Darstellung als Bereiche
                const ranges = [];
                let start = this.numbers[0];
                let end = this.numbers[0];

                for (let i = 1; i < this.numbers.length; i++) {
                    if (this.numbers[i] === end + 1) {
                        end = this.numbers[i];
                    } else {
                        ranges.push(start === end ? String(start) : `${start}-${end}`);
                        start = this.numbers[i];
                        end = this.numbers[i];
                    }
                }
                ranges.push(start === end ? String(start) : `${start}-${end}`);

                return ranges.join(', ');
            }
        };
    }
</script>
@endpush
