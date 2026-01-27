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
                        <div class="space-y-8" data-fanfiction-list>
                            @foreach ($fanfictions as $fanfiction)
                                <article
                                    class="bg-gray-50 dark:bg-gray-700/50 rounded-lg overflow-hidden"
                                    x-data="{ expanded: false }"
                                    data-fanfiction-item
                                >
                                    <div class="p-6">
                                        {{-- Header mit Titel und Autor --}}
                                        <header class="mb-4">
                                            <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-1">
                                                <a href="{{ route('fanfiction.show', $fanfiction) }}" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                                                    {{ $fanfiction->title }}
                                                </a>
                                            </h3>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                von <span class="font-medium">{{ $fanfiction->author_display_name }}</span>
                                                @if ($fanfiction->published_at)
                                                    • {{ $fanfiction->published_at->format('d.m.Y') }}
                                                @endif
                                            </p>
                                        </header>

                                        {{-- Story-Inhalt --}}
                                        <div class="prose dark:prose-invert max-w-none">
                                            {{-- Teaser (immer sichtbar wenn zugeklappt) --}}
                                            <div x-show="!expanded" data-fanfiction-teaser>
                                                <p class="text-gray-600 dark:text-gray-300">{{ $fanfiction->teaser }}</p>
                                            </div>

                                            {{-- Vollständiger Inhalt (nur wenn aufgeklappt) --}}
                                            <div x-show="expanded" x-cloak data-fanfiction-content>
                                                {!! $fanfiction->rendered_content !!}
                                            </div>
                                        </div>

                                        {{-- Bilder-Galerie (nur wenn aufgeklappt und Bilder vorhanden) --}}
                                        @if ($fanfiction->photos && count($fanfiction->photos) > 0)
                                            <div x-show="expanded" x-cloak class="mt-6" data-fanfiction-gallery>
                                                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                                    @foreach ($fanfiction->photos as $index => $photo)
                                                        <a href="{{ Storage::url($photo) }}" target="_blank"
                                                            class="block aspect-square overflow-hidden rounded-lg hover:opacity-90 transition-opacity">
                                                            <img src="{{ Storage::url($photo) }}"
                                                                alt="{{ $fanfiction->title }} - Bild {{ $index + 1 }}"
                                                                class="w-full h-full object-cover">
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        {{-- Auf-/Zuklappen Button --}}
                                        <div class="mt-4 flex items-center justify-between">
                                            <button
                                                @click="expanded = !expanded"
                                                class="inline-flex items-center gap-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 transition-colors"
                                                data-fanfiction-toggle
                                            >
                                                <template x-if="!expanded">
                                                    <span class="flex items-center gap-1">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                        </svg>
                                                        Geschichte aufklappen
                                                    </span>
                                                </template>
                                                <template x-if="expanded">
                                                    <span class="flex items-center gap-1">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                                        </svg>
                                                        Geschichte zuklappen
                                                    </span>
                                                </template>
                                            </button>

                                            <div class="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                                                <span class="flex items-center gap-1" data-comment-count>
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                                    </svg>
                                                    {{ $fanfiction->comments_count ?? $fanfiction->comments->count() }}
                                                </span>
                                                @if ($fanfiction->photos && count($fanfiction->photos) > 0)
                                                    <span class="flex items-center gap-1">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                        {{ count($fanfiction->photos) }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Kommentarbereich (immer sichtbar, unterhalb der Geschichte) --}}
                                    <div class="border-t border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 p-6" data-fanfiction-comments>
                                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                                            Kommentare ({{ $fanfiction->comments->count() }})
                                        </h4>

                                        @if ($fanfiction->comments->isEmpty())
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                Noch keine Kommentare.
                                                <a href="{{ route('fanfiction.show', $fanfiction) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                                    Sei der Erste!
                                                </a>
                                            </p>
                                        @else
                                            <div class="space-y-3">
                                                @foreach ($fanfiction->comments->take(3) as $comment)
                                                    <div class="flex items-start gap-3 text-sm">
                                                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-indigo-500 flex items-center justify-center text-white text-xs font-medium">
                                                            {{ $comment->user ? substr($comment->user->name, 0, 1) : '?' }}
                                                        </div>
                                                        <div class="flex-grow min-w-0">
                                                            <p class="font-medium text-gray-900 dark:text-gray-100">
                                                                {{ $comment->user?->name ?? 'Unbekannt' }}
                                                                <span class="font-normal text-gray-500 dark:text-gray-400">
                                                                    • {{ $comment->created_at->diffForHumans() }}
                                                                </span>
                                                            </p>
                                                            <p class="text-gray-600 dark:text-gray-300 truncate">{{ $comment->content }}</p>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                            @if ($fanfiction->comments->count() > 3)
                                                <a href="{{ route('fanfiction.show', $fanfiction) }}"
                                                    class="mt-3 inline-block text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                                                    Alle {{ $fanfiction->comments->count() }} Kommentare anzeigen →
                                                </a>
                                            @endif
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        <div class="mt-8">
                            {{ $fanfictions->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
                