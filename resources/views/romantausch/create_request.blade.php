<x-app-layout>
    <x-member-page class="max-w-4xl">
            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
                <h1 class="text-2xl font-bold text-[#8B0116] dark:text-[#FF6B81] mb-6">Neues Gesuch erstellen</h1>

                <form action="{{ route('romantausch.store-request') }}" method="POST" id="request-form">
                    @csrf

                    @php
                        $selectedSeries = old('series', $types[0]->value ?? '');
                        $seriesError = $errors->first('series');
                        $bookNumberError = $errors->first('book_number');
                        $conditionError = $errors->first('condition');
                    @endphp

                    <div class="mb-4">
                        <label for="series-select" class="block font-medium text-gray-700 dark:text-gray-200 mb-2">Serie</label>
                        <select
                            name="series"
                            id="series-select"
                            @class([
                                'w-full rounded bg-gray-50 dark:bg-gray-700 border text-gray-800 dark:text-gray-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B0116] dark:focus-visible:ring-[#FF6B81]',
                                'border-red-500 focus-visible:ring-red-500 dark:border-red-500' => $seriesError,
                                'border-gray-300 dark:border-gray-600' => !$seriesError,
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
                            <p id="series-error" class="mt-2 text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="book-select" class="block font-medium text-gray-700 dark:text-gray-200 mb-2">Gesuchter Roman</label>
                        <select
                            name="book_number"
                            id="book-select"
                            @class([
                                'w-full rounded bg-gray-50 dark:bg-gray-700 border text-gray-800 dark:text-gray-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B0116] dark:focus-visible:ring-[#FF6B81]',
                                'border-red-500 focus-visible:ring-red-500 dark:border-red-500' => $bookNumberError,
                                'border-gray-300 dark:border-gray-600' => !$bookNumberError,
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
                                    @selected((string) old('book_number') === (string) $book->roman_number)
                                >
                                    {{ $book->roman_number }} - {{ $book->title }}
                                </option>
                            @endforeach
                        </select>
                        @error('book_number')
                            <p id="book_number-error" class="mt-2 text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label for="condition-select" class="block font-medium text-gray-700 dark:text-gray-200 mb-2">Zustand bis einschließlich</label>
                        <select
                            name="condition"
                            id="condition-select"
                            @class([
                                'w-full rounded bg-gray-50 dark:bg-gray-700 border text-gray-800 dark:text-gray-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B0116] dark:focus-visible:ring-[#FF6B81]',
                                'border-red-500 focus-visible:ring-red-500 dark:border-red-500' => $conditionError,
                                'border-gray-300 dark:border-gray-600' => !$conditionError,
                            ])
                            @if($conditionError)
                                aria-invalid="true"
                                aria-describedby="condition-error"
                            @endif
                        >
                            <option value="Z0" @selected(old('condition') === 'Z0')>Z0 - Druckfrisch (Top Zustand)</option>
                            <option value="Z0-1" @selected(old('condition') === 'Z0-1')>Z0-1 - Druckfrisch, minimale Mängel</option>
                            <option value="Z1" @selected(old('condition') === 'Z1')>Z1 - Sehr gut, Kleinstfehler</option>
                            <option value="Z1-2" @selected(old('condition') === 'Z1-2')>Z1-2 - Sehr gut, leichte Gebrauchsspuren</option>
                            <option value="Z2" @selected(old('condition') === 'Z2')>Z2 - Gut, kleine Mängel</option>
                            <option value="Z2-3" @selected(old('condition') === 'Z2-3')>Z2-3 - Gut, stärker gebraucht</option>
                            <option value="Z3" @selected(old('condition') === 'Z3')>Z3 - Deutlich gebraucht</option>
                            <option value="Z3-4" @selected(old('condition') === 'Z3-4')>Z3-4 - Sehr stark gebraucht</option>
                            <option value="Z4" @selected(old('condition') === 'Z4')>Z4 - Sehr schlecht erhalten</option>
                        </select>
                        @error('condition')
                            <p id="condition-error" class="mt-2 text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-[#8B0116] dark:bg-[#C41E3A] text-white font-semibold rounded hover:bg-[#A50019] dark:hover:bg-[#D63A4D]">
                        Gesuch speichern
                    </button>
                </form>
            </div>
    </x-member-page>

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
</x-app-layout>
