<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Fanfiction
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 lg:p-8">
                    <div class="mb-6">
                        <p class="text-gray-600 dark:text-gray-400">
                            Hier findest du Kurzgeschichten und Fanfiction aus dem MADDRAX-Universum, geschrieben von
                            unseren Mitgliedern und Gastautoren.
                        </p>
                    </div>

                    @if ($fanfictions->isEmpty())
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Noch keine Fanfiction
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Es wurden noch keine Geschichten veröffentlicht.
                            </p>
                        </div>
                    @else
                        <div class="space-y-6">
                            @foreach ($fanfictions as $fanfiction)
                                <a href="{{ route('fanfiction.show', $fanfiction) }}"
                                    class="block bg-gray-50 dark:bg-gray-700/50 rounded-lg p-6 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    <div class="flex flex-col md:flex-row md:items-start gap-4">
                                        @if ($fanfiction->photos && count($fanfiction->photos) > 0)
                                            <div class="flex-shrink-0">
                                                <img src="{{ Storage::url($fanfiction->photos[0]) }}"
                                                    alt="{{ $fanfiction->title }}"
                                                    class="w-full md:w-32 h-32 object-cover rounded-lg">
                                            </div>
                                        @endif
                                        <div class="flex-grow">
                                            <h3
                                                class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                                {{ $fanfiction->title }}
                                            </h3>
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                                                von {{ $fanfiction->author_display_name }}
                                                @if ($fanfiction->published_at)
                                                    • {{ $fanfiction->published_at->format('d.m.Y') }}
                                                @endif
                                            </p>
                                            <div class="text-gray-600 dark:text-gray-300 text-sm line-clamp-3">
                                                {{ $fanfiction->teaser }}
                                            </div>
                                            <div class="mt-3 flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                                                <span class="flex items-center gap-1">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                                    </svg>
                                                    {{ $fanfiction->comments_count ?? $fanfiction->comments->count() }}
                                                    {{ Str::plural('Kommentar', $fanfiction->comments_count ?? $fanfiction->comments->count()) }}
                                                </span>
                                                @if ($fanfiction->photos && count($fanfiction->photos) > 0)
                                                    <span class="flex items-center gap-1">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                        {{ count($fanfiction->photos) }}
                                                        {{ Str::plural('Bild', count($fanfiction->photos)) }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>

                        <div class="mt-6">
                            {{ $fanfictions->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
