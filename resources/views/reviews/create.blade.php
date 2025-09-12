<x-app-layout :title="$title" :description="$description">
    <x-member-page class="max-w-3xl">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6">
                Neue Rezension zu „{{ $book->title }}“ (Nr. {{ $book->roman_number }})
            </h1>

            <div class="mb-6 p-4 bg-yellow-100 dark:bg-yellow-800 border-l-4 border-yellow-500 dark:border-yellow-300 text-yellow-700 dark:text-yellow-200">
                <p class="font-bold">Hinweis</p>
                <p>Du kannst die Rezensionen zu diesem Roman erst lesen, nachdem du selbst eine verfasst und gespeichert hast.</p>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
                <form action="{{ route('reviews.store', $book) }}" method="POST">
                    @csrf

                    <x-form name="title" label="Rezensionstitel" class="mb-4">
                        <input id="title" name="title" aria-describedby="title-error" type="text" value="{{ old('title') }}" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded" required />
                    </x-form>

                    <x-form name="content" label="Rezensionstext" class="mb-4">
                        <textarea id="content" name="content" aria-describedby="content-error" rows="8" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded" required>{{ old('content') }}</textarea>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Mindestens 140 Zeichen.</p>
                    </x-form>

                    <div class="flex flex-col sm:flex-row sm:justify-between gap-4">
                        <a href="{{ route('reviews.index') }}" class="text-gray-600 dark:text-gray-400 hover:underline">Abbrechen</a>
                        <button type="submit" class="bg-[#8B0116] dark:bg-[#FCA5A5] text-white px-4 py-2 rounded">
                            Rezension absenden
                        </button>
                    </div>
                </form>
            </div>
    </x-member-page>
</x-app-layout>