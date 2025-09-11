<x-app-layout>
    <x-member-page class="max-w-4xl">
            @if(session('error'))
                <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-800 dark:bg-red-800 dark:border-red-700 dark:text-red-100 rounded">
                    {{ session('error') }}
                </div>
            @endif
            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
                <h1 class="text-2xl font-bold text-[#8B0116] dark:text-[#FF6B81] mb-6">Neues Angebot erstellen</h1>

                <form action="{{ route('romantausch.store-offer') }}" method="POST" enctype="multipart/form-data" id="offer-form">
                    @csrf

                    <div class="mb-4">
                        <label class="block font-medium text-gray-700 dark:text-gray-200 mb-2">Serie</label>
                        <select name="series" id="series-select" class="w-full rounded bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200">
                            @foreach($types as $type)
                                <option value="{{ $type->value }}">{{ $type->value }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block font-medium text-gray-700 dark:text-gray-200 mb-2">Angebotener Roman</label>
                        <select name="book_number" id="book-select" class="w-full rounded bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200">
                            @foreach($books as $book)
                                <option value="{{ $book->roman_number }}" data-series="{{ $book->type->value }}">
                                    {{ $book->roman_number }} - {{ $book->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-6">
                        <label class="block font-medium text-gray-700 dark:text-gray-200 mb-2">Zustand</label>
                        <select name="condition" class="w-full rounded bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200">
                            <option value="Z0">Z0 - Druckfrisch (Top Zustand)</option>
                            <option value="Z0-1">Z0-1 - Druckfrisch, minimale Mängel</option>
                            <option value="Z1">Z1 - Sehr gut, Kleinstfehler</option>
                            <option value="Z1-2">Z1-2 - Sehr gut, leichte Gebrauchsspuren</option>
                            <option value="Z2">Z2 - Gut, kleine Mängel</option>
                            <option value="Z2-3">Z2-3 - Gut, stärker gebraucht</option>
                            <option value="Z3">Z3 - Deutlich gebraucht</option>
                            <option value="Z3-4">Z3-4 - Sehr stark gebraucht</option>
                            <option value="Z4">Z4 - Sehr schlecht erhalten</option>
                        </select>
                    </div>

                    <div class="mb-6">
                        <label class="block font-medium text-gray-700 dark:text-gray-200 mb-2">Fotos (max. 3)</label>
                        <input type="file" name="photos[]" multiple accept="image/*" class="w-full rounded bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200" />
                    </div>

                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-[#8B0116] dark:bg-[#C41E3A] text-white font-semibold rounded hover:bg-[#A50019] dark:hover:bg-[#D63A4D]">
                        Angebot speichern
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
                Array.from(bookSelect.options).forEach((option, idx) => {
                    const match = option.dataset.series === series;
                    option.hidden = !match;
                    option.disabled = !match;
                    if (match && firstVisibleIndex === -1) {
                        firstVisibleIndex = idx;
                    }
                });
                if (firstVisibleIndex !== -1) {
                    bookSelect.selectedIndex = firstVisibleIndex;
                }
            }

            filterBooks();
            seriesSelect.addEventListener('change', filterBooks);
        });
    </script>
</x-app-layout>
