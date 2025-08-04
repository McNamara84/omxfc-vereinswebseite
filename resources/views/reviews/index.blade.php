<x-app-layout>
    <x-member-page>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-4">Rezensionen</h1>
            <p class="mb-6 text-base font-medium text-gray-700 dark:text-gray-300">
                Für jede <strong>zehnte</strong> verfasste Rezension erhältst du automatisch
                <strong>1 Baxx</strong>.
            </p>
            <form method="GET" action="{{ route('reviews.index') }}" class="mb-6 bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="roman_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nr.</label>
                        <input id="roman_number" name="roman_number" type="text" value="{{ request('roman_number') }}" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded" />
                    </div>
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Titel</label>
                        <input id="title" name="title" type="text" value="{{ request('title') }}" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded" />
                    </div>
                    <div>
                        <label for="author" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Autor</label>
                        <input id="author" name="author" type="text" value="{{ request('author') }}" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded" />
                    </div>
                    <div>
                        <label for="review_status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Rezensionsstatus</label>
                        <select id="review_status" name="review_status" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded">
                            <option value="">Alle</option>
                            <option value="with" @selected(request('review_status') === 'with')>Mit Rezension</option>
                            <option value="without" @selected(request('review_status') === 'without')>Ohne Rezension</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex items-center">
                    <button type="submit" class="bg-[#8B0116] dark:bg-[#FCA5A5] text-white px-4 py-2 rounded">Filtern</button>
                    <a href="{{ route('reviews.index') }}" class="ml-3 text-gray-600 dark:text-gray-400 hover:underline">Zurücksetzen</a>
                </div>
            </form>
            <div id="accordion">
                @foreach($booksByCycle as $cycle => $cycleBooks)
                    @php
                        $id = \Illuminate\Support\Str::slug($cycle);
                        $reviewCount = $cycleBooks->sum('reviews_count');
                    @endphp
                    <div class="mb-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <h2>
                            <button type="button" class="w-full flex justify-between items-center bg-gray-100 dark:bg-gray-700 px-4 py-3 rounded-t-lg font-semibold" onclick="toggleAccordion('{{ $id }}')">
                                {{ $cycle }}-Zyklus ({{ $reviewCount }} {{ $reviewCount === 1 ? 'Rezension' : 'Rezensionen' }})
                                <span id="icon-{{ $id }}">{{ $loop->first ? '-' : '+' }}</span>
                            </button>
                        </h2>
                        <div id="content-{{ $id }}" class="{{ $loop->first ? '' : 'hidden' }} bg-white dark:bg-gray-900 px-4 py-2 rounded-b-lg overflow-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2 text-left text-gray-700 dark:text-gray-300">Nr.</th>
                                        <th class="px-4 py-2 text-left text-gray-700 dark:text-gray-300">Titel</th>
                                        <th class="px-4 py-2 text-left text-gray-700 dark:text-gray-300">Autor</th>
                                        <th class="px-4 py-2 text-left text-gray-700 dark:text-gray-300">Status</th>
                                        <th class="px-4 py-2 text-left text-gray-700 dark:text-gray-300">Rezensionen</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($cycleBooks->sortByDesc('roman_number') as $book)
                                        <tr>
                                            <td class="px-4 py-2">
                                                <a href="{{ route('reviews.show', $book) }}" class="text-[#8B0116] hover:underline">
                                                    {{ $book->roman_number }}
                                                </a>
                                            </td>
                                            <td class="px-4 py-2 text-gray-800 dark:text-gray-200">{{ $book->title }}</td>
                                            <td class="px-4 py-2 text-gray-800 dark:text-gray-200">{{ $book->author }}</td>
                                            <td class="px-4 py-2">
                                                @if($book->has_review)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 00-1.414-1.414L8.5 10.672 5.707 7.879a1 1 0 00-1.414 1.414l3.647 3.647a1 1 0 001.414 0l7.353-7.353z" clip-rule="evenodd" />
                                                        </svg>
                                                    </span>
                                                @else
                                                    <a href="{{ route('reviews.create', $book) }}" class="inline-flex items-center p-1 rounded-full bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100 hover:bg-blue-200 dark:hover:bg-blue-700" title="Rezension schreiben">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                            <path d="m2.695 14.762-1.262 3.155a.5.5 0 0 0 .65.65l3.155-1.262a4 4 0 0 0 1.343-.886L17.5 5.501a2.121 2.121 0 0 0-3-3L3.58 13.419a4 4 0 0 0-.885 1.343Z" />
                                                        </svg>
                                                    </a>
                                                @endif
                                            </td>
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
                @endforeach
            </div>
        </div>
    </div>

    <script>
        function toggleAccordion(id) {
            const content = document.getElementById('content-' + id);
            const icon = document.getElementById('icon-' + id);
            content.classList.toggle('hidden');
            icon.textContent = content.classList.contains('hidden') ? '+' : '-';
        }
    </script>
    </x-member-page>
</x-app-layout>