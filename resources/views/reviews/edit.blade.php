<x-app-layout :title="$title" :description="$description">
    <x-member-page class="max-w-3xl">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6">
                Rezension zu „{{ $review->book->title }}“ bearbeiten
            </h1>

            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
                <form action="{{ route('reviews.update', $review) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <x-form name="title" label="Rezensionstitel" class="mb-4">
                        <input id="title" name="title" aria-describedby="title-error" type="text" value="{{ old('title', $review->title) }}" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded" required />
                    </x-form>

                    <x-form name="content" label="Rezensionstext" class="mb-4">
                        <textarea id="content" name="content" aria-describedby="content-error" rows="8" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded" required>{{ old('content', $review->content) }}</textarea>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Mindestens 140 Zeichen.</p>
                    </x-form>

                    <div class="flex flex-col sm:flex-row sm:justify-between gap-4">
                        <a href="{{ route('reviews.show', $review->book) }}" class="text-gray-600 dark:text-gray-400 hover:underline">Abbrechen</a>
                        <button type="submit" class="bg-[#8B0116] dark:bg-[#FCA5A5] text-white px-4 py-2 rounded">
                            Rezension aktualisieren
                        </button>
                    </div>
                </form>
            </div>
    </x-member-page>
</x-app-layout>