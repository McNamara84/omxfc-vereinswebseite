<x-app-layout>
    <x-member-page class="max-w-4xl">
            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
                <h1 class="text-2xl font-bold text-[#8B0116] dark:text-[#FF6B81] mb-6">Neues Gesuch erstellen</h1>

                <form action="{{ route('romantausch.store-request') }}" method="POST">
                    @csrf

                    <div class="mb-4">
                        <label class="block font-medium text-gray-700 dark:text-gray-200 mb-2">Serie</label>
                        <input type="text" class="w-full rounded bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300" value="Maddrax - Die dunkle Zukunft der Erde" disabled>
                    </div>

                    <div class="mb-4">
                        <label class="block font-medium text-gray-700 dark:text-gray-200 mb-2">Gesuchter Roman</label>
                        <select name="book_number" class="w-full rounded bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200">
                            @foreach($books as $book)
                                <option value="{{ $book['nummer'] }}">
                                    {{ $book['nummer'] }} - {{ $book['titel'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-6">
                        <label class="block font-medium text-gray-700 dark:text-gray-200 mb-2">Zustand bis einschließlich</label>
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

                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-[#8B0116] dark:bg-[#C41E3A] text-white font-semibold rounded hover:bg-[#A50019] dark:hover:bg-[#D63A4D]">
                        Gesuch speichern
                    </button>
                </form>
            </div>
    </x-member-page>
</x-app-layout>
