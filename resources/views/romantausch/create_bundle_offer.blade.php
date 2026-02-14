<x-app-layout>
    <x-member-page class="max-w-4xl">
        <x-header title="Stapel-Angebot erstellen" separator useH1 data-testid="page-title" />

        <x-card>
            <p class="text-base-content mb-6">
                Mit einem Stapel-Angebot kannst du viele Romane auf einmal einstellen. Gib einfach die Nummern als Bereiche (z.B. 1-50) oder einzeln (z.B. 52, 55) ein.
            </p>

            <form action="{{ route('romantausch.store-bundle-offer') }}" method="POST" enctype="multipart/form-data" id="bundle-offer-form">
                @csrf

                @php
                    $selectedSeries = old('series', $types[0]->value ?? '');
                    $bookNumbersInput = old('book_numbers', '');
                    $selectedCondition = old('condition', 'Z1');
                    $selectedConditionMax = old('condition_max', '');
                    $photoError = $errors->first('photos');
                    $photoItemErrors = $errors->get('photos.*');
                    $photosErrorMessage = $photoError ?: (count($photoItemErrors) > 0 ? implode(' ', $photoItemErrors) : null);

                    $seriesOptions = collect($types)->map(fn($t) => ['id' => $t->value, 'name' => $t->value])->toArray();
                    $conditionOptions = [
                        ['id' => 'Z0', 'name' => 'Z0 - ' . __('romantausch.condition.Z0')],
                        ['id' => 'Z0-1', 'name' => 'Z0-1 - ' . __('romantausch.condition.Z0-1')],
                        ['id' => 'Z1', 'name' => 'Z1 - ' . __('romantausch.condition.Z1')],
                        ['id' => 'Z1-2', 'name' => 'Z1-2 - ' . __('romantausch.condition.Z1-2')],
                        ['id' => 'Z2', 'name' => 'Z2 - ' . __('romantausch.condition.Z2')],
                        ['id' => 'Z2-3', 'name' => 'Z2-3 - ' . __('romantausch.condition.Z2-3')],
                        ['id' => 'Z3', 'name' => 'Z3 - ' . __('romantausch.condition.Z3')],
                    ];
                    $conditionMaxOptions = [
                        ['id' => '', 'name' => __('romantausch.condition.same')],
                        ['id' => 'Z0', 'name' => 'Z0 - ' . __('romantausch.condition.Z0')],
                        ['id' => 'Z0-1', 'name' => 'Z0-1 - ' . __('romantausch.condition.Z0-1')],
                        ['id' => 'Z1', 'name' => 'Z1 - ' . __('romantausch.condition.Z1')],
                        ['id' => 'Z1-2', 'name' => 'Z1-2 - ' . __('romantausch.condition.Z1-2')],
                        ['id' => 'Z2', 'name' => 'Z2 - ' . __('romantausch.condition.Z2')],
                        ['id' => 'Z2-3', 'name' => 'Z2-3 - ' . __('romantausch.condition.Z2-3')],
                        ['id' => 'Z3', 'name' => 'Z3 - ' . __('romantausch.condition.Z3')],
                        ['id' => 'Z3-4', 'name' => 'Z3-4 - ' . __('romantausch.condition.Z3-4')],
                        ['id' => 'Z4', 'name' => 'Z4 - ' . __('romantausch.condition.Z4')],
                    ];
                @endphp

                @if(session('error'))
                    <x-alert title="Fehler" :description="session('error')" icon="o-x-circle" class="alert-error mb-4" />
                @endif

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="md:col-span-1 space-y-4">
                        {{-- Serie --}}
                        <x-select
                            id="series-select"
                            name="series"
                            label="Serie"
                            :options="$seriesOptions"
                            :value="$selectedSeries"
                            error-field="series"
                        />

                        <script>
                            window.MAX_RANGE_SPAN = {{ App\Services\Romantausch\BundleService::MAX_RANGE_SPAN }};
                            window.COMPACT_THRESHOLD = {{ config('romantausch.compact_threshold', 20) }};
                        </script>

                        {{-- Roman-Nummern --}}
                        <div x-data="bundlePreview()">
                            <x-input
                                id="book-numbers-input"
                                name="book_numbers"
                                label="Roman-Nummern"
                                placeholder="z.B. 1-50, 52, 55-100"
                                :value="$bookNumbersInput"
                                hint="Gib Nummern einzeln (1, 5, 7) oder als Bereich (1-50) an, getrennt durch Kommas."
                                x-model="input"
                                x-init="input = $el.querySelector('input')?.value || input; parseNumbers()"
                                @input.debounce.300ms="parseNumbers()"
                            />

                            {{-- Vorschau --}}
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

                        {{-- Zustandsbereich --}}
                        <div>
                            <label class="fieldset-legend">Zustandsbereich</label>
                            <div class="grid grid-cols-2 gap-3">
                                <x-select
                                    id="condition-min"
                                    name="condition"
                                    label="Von (bester Zustand)"
                                    :options="$conditionOptions"
                                    :value="$selectedCondition"
                                    error-field="condition"
                                />
                                <x-select
                                    id="condition-max"
                                    name="condition_max"
                                    label="Bis (schlechtester)"
                                    :options="$conditionMaxOptions"
                                    :value="$selectedConditionMax"
                                    error-field="condition_max"
                                />
                            </div>
                            @error('condition')
                                <p class="mt-2 text-sm text-error" role="alert">{{ $message }}</p>
                            @enderror
                            @error('condition_max')
                                <p class="mt-2 text-sm text-error" role="alert">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-base-content">
                                Bei gemischten Zuständen gibst du den Bereich an, z.B. „Z1 bis Z2“.
                            </p>
                        </div>
                    </div>

                    {{-- Fotos --}}
                    <div class="md:col-span-1">
                        <div>
                            <label for="photos" class="fieldset-legend">Fotos (optional)</label>
                            <p id="photos-help" class="text-sm text-base-content mb-2">
                                Du kannst bis zu 3 Übersichtsfotos für den gesamten Stapel hochladen.
                            </p>
                            <p id="photos-size" class="text-xs text-base-content mb-4">
                                Unterstützte Formate: JPG, JPEG, PNG, GIF, WebP. Max. 2 MB pro Foto.
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
                                aria-describedby="photos-help photos-size{{ $photosErrorMessage ? ' photos-error' : '' }}"
                                @if($photosErrorMessage) aria-invalid="true" @endif
                            >
                            @if($photosErrorMessage)
                                <p id="photos-error" class="mt-2 text-sm text-error" role="alert">{{ $photosErrorMessage }}</p>
                            @endif
                        </div>

                        {{-- Hinweis-Box --}}
                        <x-alert icon="o-light-bulb" class="alert-info mt-6">
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
                    <x-button label="Stapel-Angebot erstellen" type="submit" class="btn-primary" icon="o-check" />
                    <x-button label="Abbrechen" link="{{ route('romantausch.index') }}" class="btn-ghost" />
                </div>
            </form>
        </x-card>
    </x-member-page>
</x-app-layout>

@vite(['resources/js/romantausch-bundle-preview.js'])
