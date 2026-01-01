<x-app-layout>
    <x-member-page class="max-w-4xl">
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
            <h1 class="text-2xl font-bold text-[#8B0116] dark:text-[#FF6B81] mb-6">Stapel-Angebot erstellen</h1>
            <p class="text-gray-600 dark:text-gray-400 mb-6">
                Mit einem Stapel-Angebot kannst du viele Romane auf einmal einstellen. Gib einfach die Nummern als Bereiche (z.B. 1-50) oder einzeln (z.B. 52, 55) ein.
            </p>

            <form action="{{ route('romantausch.store-bundle-offer') }}" method="POST" enctype="multipart/form-data" id="bundle-offer-form">
                @csrf

                @php
                    $selectedSeries = old('series', $types[0]->value ?? '');
                    $bookNumbersInput = old('book_numbers', '');
                    $selectedCondition = old('condition', 'Z1');
                    $selectedConditionMax = old('condition_max', '');
                    $seriesError = $errors->first('series');
                    $bookNumbersError = $errors->first('book_numbers');
                    $conditionError = $errors->first('condition');
                    $photoError = $errors->first('photos');
                    $photoItemErrors = $errors->get('photos.*');
                    $photosErrorMessage = $photoError ?: (count($photoItemErrors) > 0 ? implode(' ', $photoItemErrors) : null);
                @endphp

                @if(session('error'))
                    <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-800 dark:bg-red-800 dark:border-red-700 dark:text-red-100 rounded">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="md:col-span-1 space-y-4">
                        {{-- Serie --}}
                        <div>
                            <label for="series-select" class="block font-medium text-gray-700 dark:text-gray-200 mb-2">Serie</label>
                            <select
                                name="series"
                                id="series-select"
                                @class([
                                    'w-full rounded bg-gray-50 dark:bg-gray-700 border text-gray-800 dark:text-gray-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B0116] dark:focus-visible:ring-[#FF6B81]',
                                    'border-red-500 focus-visible:ring-red-500 dark:border-red-500' => $seriesError,
                                    'border-gray-300 dark:border-gray-600' => !$seriesError,
                                ])
                                @if($seriesError) aria-invalid="true" aria-describedby="series-error" @endif
                            >
                                @foreach($types as $type)
                                    <option value="{{ $type->value }}" @selected($selectedSeries === $type->value)>{{ $type->value }}</option>
                                @endforeach
                            </select>
                            @error('series')
                                <p id="series-error" class="mt-2 text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

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
                            <div x-show="input && numbers.length === 0" x-cloak class="mt-3 p-3 bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 rounded-lg">
                                <p class="text-sm text-yellow-800 dark:text-yellow-200">Keine gültigen Nummern erkannt. Bitte überprüfe deine Eingabe.</p>
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
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Bei gemischten Zuständen gibst du den Bereich an, z.B. „Z1 bis Z2".
                            </p>
                        </div>
                    </div>

                    {{-- Fotos --}}
                    <div class="md:col-span-1">
                        <div>
                            <label for="photos" class="block font-medium text-gray-700 dark:text-gray-200 mb-2">Fotos (optional)</label>
                            <p id="photos-help" class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                Du kannst bis zu 3 Übersichtsfotos für den gesamten Stapel hochladen.
                            </p>
                            <p id="photos-size" class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                                Unterstützte Formate: JPG, JPEG, PNG, GIF, WebP. Max. 2 MB pro Foto.
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
                                aria-describedby="photos-help photos-size{{ $photosErrorMessage ? ' photos-error' : '' }}"
                                @if($photosErrorMessage) aria-invalid="true" @endif
                            >
                            @if($photosErrorMessage)
                                <p id="photos-error" class="mt-2 text-sm text-red-600 dark:text-red-400" role="alert">{{ $photosErrorMessage }}</p>
                            @endif
                        </div>

                        {{-- Hinweis-Box --}}
                        <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded-lg">
                            <h3 class="text-sm font-semibold text-blue-800 dark:text-blue-200 mb-2">💡 Tipp</h3>
                            <ul class="text-sm text-blue-700 dark:text-blue-300 space-y-1 list-disc list-inside">
                                <li>Alle Romane im Stapel können einzeln getauscht werden</li>
                                <li>Andere Mitglieder sehen, welche ihrer Gesuche zu deinem Stapel passen</li>
                                <li>Du kannst den Stapel später bearbeiten und Romane hinzufügen/entfernen</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex flex-wrap gap-3">
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-[#8B0116] dark:bg-[#C41E3A] text-white font-semibold rounded hover:bg-[#A50019] dark:hover:bg-[#D63A4D] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B0116] dark:focus-visible:ring-[#FF6B81]">
                        Stapel-Angebot erstellen
                    </button>
                    <a href="{{ route('romantausch.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-100 font-semibold rounded hover:bg-gray-300 dark:hover:bg-gray-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-gray-400">
                        Abbrechen
                    </a>
                </div>
            </form>
        </div>
    </x-member-page>
</x-app-layout>

@push('scripts')
{{--
    Bundle Preview Initialisierung
    
    Die Werte werden hier definiert, da sie aus PHP-Variablen kommen.
    Die eigentliche Logik ist in resources/js/romantausch-bundle-preview.js ausgelagert.
    
    @see resources/js/romantausch-bundle-preview.js für die bundlePreview() Funktion
    @see App\Http\Controllers\RomantauschController::MAX_RANGE_SPAN für das Limit
--}}
<script>
    window.MAX_RANGE_SPAN = {{ App\Http\Controllers\RomantauschController::MAX_RANGE_SPAN }};
    window.bundlePreviewInitialInput = {{ Js::from($bookNumbersInput) }};
</script>
@endpush

@vite(['resources/js/romantausch-bundle-preview.js'])
