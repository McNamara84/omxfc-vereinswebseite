<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-4">Rezensionen</h1>

            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 overflow-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left text-gray-700 dark:text-gray-300">Nr.</th>
                            <th class="px-4 py-2 text-left text-gray-700 dark:text-gray-300">Titel</th>
                            <th class="px-4 py-2 text-left text-gray-700 dark:text-gray-300">Autor</th>
                            <th class="px-4 py-2 text-left text-gray-700 dark:text-gray-300">Rezensionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($books->sortByDesc('roman_number') as $book)
                            <tr>
                                <td class="px-4 py-2">
                                    <a href="{{ route('reviews.show', $book) }}" class="text-[#8B0116] hover:underline">
                                        {{ $book->roman_number }}
                                    </a>
                                </td>
                                <td class="px-4 py-2 text-gray-800 dark:text-gray-200">{{ $book->title }}</td>
                                <td class="px-4 py-2 text-gray-800 dark:text-gray-200">{{ $book->author }}</td>
                                <td class="px-4 py-2">
                                    <a href="{{ route('reviews.show', $book) }}" class="text-[#8B0116] hover:underline">
                                        {{ $book->reviews_count }} {{ $book->reviews_count === 1 ? 'Rezension' : 'Rezensionen' }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>