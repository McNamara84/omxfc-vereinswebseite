<x-app-layout>
    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-6">
                Rezension zu „{{ $review->book->title }}“ bearbeiten
            </h1>

            <div class="bg-maddrax-black border border-maddrax-red shadow-xl sm:rounded-lg p-6">
                <form action="{{ route('reviews.update', $review) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label for="title" class="block text-maddrax-sand font-medium">Rezensionstitel</label>
                        <input type="text" name="title" id="title" value="{{ old('title', $review->title) }}"
                               class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded" required>
                        @error('title')
                            <p class="text-red-600 dark:text-red-400 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="content" class="block text-maddrax-sand font-medium">Rezensionstext</label>
                        <textarea name="content" id="content" rows="8"
                                  class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded"
                                  required>{{ old('content', $review->content) }}</textarea>
                        <p class="text-xs text-maddrax-sand mt-1">Mindestens 140 Zeichen.</p>
                        @error('content')
                            <p class="text-red-600 dark:text-red-400 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between">
                        <a href="{{ route('reviews.show', $review->book) }}" class="text-gray-600 dark:text-gray-400 hover:underline">Abbrechen</a>
                        <button type="submit" class="bg-[#8B0116] dark:bg-[#FCA5A5] text-white px-4 py-2 rounded">
                            Rezension aktualisieren
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>