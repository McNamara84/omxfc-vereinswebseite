<x-app-layout>
    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-6">
                Neue Rezension zu „{{ $book->title }}“ (Nr. {{ $book->roman_number }})
            </h1>

            <div class="mb-6 p-4 bg-yellow-100 dark:bg-yellow-800 border-l-4 border-yellow-500 dark:border-yellow-300 text-yellow-700 dark:text-yellow-200">
                <p class="font-bold">Hinweis</p>
                <p>Du kannst die Rezensionen zu diesem Roman erst lesen, nachdem du selbst eine verfasst und gespeichert hast.</p>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
                <form action="{{ route('reviews.store', $book) }}" method="POST">
                    @csrf

                    <div class="mb-4">
                        <label for="title" class="block text-gray-700 dark:text-gray-300 font-medium">Rezensionstitel</label>
                        <input type="text" name="title" id="title" value="{{ old('title') }}"
                               class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded" required>
                        @error('title')
                            <p class="text-red-600 dark:text-red-400 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="content" class="block text-gray-700 dark:text-gray-300 font-medium">Rezensionstext</label>
                        <textarea name="content" id="content" rows="8"
                                  class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded"
                                  required>{{ old('content') }}</textarea>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Mindestens 1250 Zeichen.</p>
                        @error('content')
                            <p class="text-red-600 dark:text-red-400 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between">
                        <a href="{{ route('reviews.index') }}" class="text-gray-600 dark:text-gray-400 hover:underline">Abbrechen</a>
                        <button type="submit" class="bg-[#8B0116] dark:bg-[#FCA5A5] text-white px-4 py-2 rounded">
                            Rezension absenden
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>