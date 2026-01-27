<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('fanfiction.index') }}"
                class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $fanfiction->title }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 lg:p-8">
                    {{-- Header mit Autor und Datum --}}
                    <div class="mb-6 pb-6 border-b border-gray-200 dark:border-gray-700">
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                            {{ $fanfiction->title }}
                        </h1>
                        <p class="text-gray-600 dark:text-gray-400">
                            von <span class="font-medium">{{ $fanfiction->author_display_name }}</span>
                            @if ($fanfiction->published_at)
                                • Veröffentlicht am {{ $fanfiction->published_at->format('d.m.Y') }}
                            @endif
                        </p>
                    </div>

                    {{-- Bilder als Galerie --}}
                    @if ($fanfiction->photos && count($fanfiction->photos) > 0)
                        <div class="mb-8">
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
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

                    {{-- Story-Inhalt (Markdown) --}}
                    <div class="prose dark:prose-invert max-w-none mb-8">
                        {!! $fanfiction->rendered_content !!}
                    </div>

                    {{-- Teilen-Hinweis für Mitglieder --}}
                    <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Hat dir die Geschichte gefallen? Teile sie mit anderen Mitgliedern!
                        </p>
                    </div>
                </div>
            </div>

            {{-- Kommentare --}}
            <div class="mt-8 bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 lg:p-8">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6">
                        Kommentare ({{ $fanfiction->comments->count() }})
                    </h2>

                    {{-- Kommentar-Formular --}}
                    <form action="{{ route('fanfiction.comments.store', $fanfiction) }}" method="POST" class="mb-8">
                        @csrf
                        <div class="mb-4">
                            <label for="content" class="sr-only">Kommentar</label>
                            <textarea name="content" id="content" rows="3"
                                class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Schreibe einen Kommentar..." required>{{ old('content') }}</textarea>
                            @error('content')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex justify-end">
                            <x-button type="submit">
                                Kommentieren
                            </x-button>
                        </div>
                    </form>

                    {{-- Kommentarliste --}}
                    @if ($fanfiction->comments->isEmpty())
                        <p class="text-gray-500 dark:text-gray-400 text-center py-4">
                            Noch keine Kommentare. Sei der Erste!
                        </p>
                    @else
                        <div class="space-y-6">
                            @foreach ($fanfiction->comments->whereNull('parent_id') as $comment)
                                <x-fanfiction-comment :comment="$comment" :fanfiction="$fanfiction" />
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
