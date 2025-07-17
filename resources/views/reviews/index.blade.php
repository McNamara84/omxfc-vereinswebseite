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
                            <th class="px-4 py-2 text-left text-gray-700 dark:text-gray-300">Meine Rezension</th>
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
                                <td class="px-4 py-2">
                                    @if($book->has_review)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 00-1.414-1.414L8.5 10.672 5.707 7.879a1 1 0 00-1.414 1.414l3.647 3.647a1 1 0 001.414 0l7.353-7.353z" clip-rule="evenodd" />
                                            </svg>
                                            vorhanden
                                        </span>
                                    @else
                                        <a href="{{ route('reviews.create', $book) }}" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100 hover:bg-blue-200 dark:hover:bg-blue-700">
                                            Rezension schreiben
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>