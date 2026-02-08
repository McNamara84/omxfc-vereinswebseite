<x-app-layout>
    <x-member-page>
        <x-header title="Fanfiction" separator useH1 data-testid="page-title">
            <x-slot:subtitle>
                Hier findest du Kurzgeschichten und Fanfiction aus dem MADDRAX-Universum, geschrieben von
                unseren Mitgliedern und Gastautoren.
            </x-slot:subtitle>
        </x-header>

        @if ($fanfictions->isEmpty())
            <x-card shadow>
                <div class="text-center py-12">
                    <x-icon name="o-book-open" class="mx-auto h-12 w-12 text-base-content/40" />
                    <h3 class="mt-2 text-sm font-medium">Noch keine Fanfiction</h3>
                    <p class="mt-1 text-sm text-base-content/60">
                        Es wurden noch keine Geschichten veröffentlicht.
                    </p>
                </div>
            </x-card>
        @else
            <div class="space-y-8" data-fanfiction-list>
                @foreach ($fanfictions as $fanfiction)
                    <x-card shadow x-data="{ expanded: false }" data-fanfiction-item>
                        {{-- Header mit Titel und Autor --}}
                        <header class="mb-4">
                            <h3 class="text-xl font-semibold mb-1">
                                <a href="{{ route('fanfiction.show', $fanfiction) }}" class="hover:text-primary transition-colors">
                                    {{ $fanfiction->title }}
                                </a>
                            </h3>
                            <p class="text-sm text-base-content/60">
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
                                <p>{{ $fanfiction->teaser }}</p>
                            </div>

                            {{-- Vollständiger Inhalt (nur wenn aufgeklappt) --}}
                            <div x-show="expanded" x-cloak data-fanfiction-content>
                                {!! $fanfiction->formatted_content !!}
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

                        {{-- Auf-/Zuklappen Button + Metadaten --}}
                        <div class="mt-4 flex items-center justify-between">
                            <x-button
                                @click="expanded = !expanded"
                                class="btn-ghost btn-sm"
                                data-fanfiction-toggle
                            >
                                <template x-if="!expanded">
                                    <span class="flex items-center gap-1">
                                        <x-icon name="o-chevron-down" class="w-4 h-4" />
                                        Geschichte aufklappen
                                    </span>
                                </template>
                                <template x-if="expanded">
                                    <span class="flex items-center gap-1">
                                        <x-icon name="o-chevron-up" class="w-4 h-4" />
                                        Geschichte zuklappen
                                    </span>
                                </template>
                            </x-button>

                            <div class="flex items-center gap-4 text-sm text-base-content/60">
                                <span class="flex items-center gap-1" data-comment-count>
                                    <x-icon name="o-chat-bubble-left-ellipsis" class="w-4 h-4" />
                                    {{ $fanfiction->comments_count ?? $fanfiction->comments->count() }}
                                </span>
                                @if ($fanfiction->photos && count($fanfiction->photos) > 0)
                                    <span class="flex items-center gap-1">
                                        <x-icon name="o-photo" class="w-4 h-4" />
                                        {{ count($fanfiction->photos) }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Kommentarbereich --}}
                        <x-hr class="my-4" />
                        <div data-fanfiction-comments>
                            <h4 class="text-sm font-semibold mb-3">
                                Kommentare ({{ $fanfiction->comments->count() }})
                            </h4>

                            @if ($fanfiction->comments->isEmpty())
                                <p class="text-sm text-base-content/60">
                                    Noch keine Kommentare.
                                    <a href="{{ route('fanfiction.show', $fanfiction) }}" class="text-primary hover:underline">
                                        Sei der Erste!
                                    </a>
                                </p>
                            @else
                                <div class="space-y-3">
                                    @foreach ($fanfiction->comments->take(3) as $comment)
                                        <div class="flex items-start gap-3 text-sm">
                                            <x-avatar value="{{ $comment->user ? substr($comment->user->name, 0, 1) : '?' }}" class="!w-8 !h-8 !text-xs" />
                                            <div class="flex-grow min-w-0">
                                                <p class="font-medium">
                                                    {{ $comment->user?->name ?? 'Unbekannt' }}
                                                    <span class="font-normal text-base-content/60">
                                                        • {{ $comment->created_at->diffForHumans() }}
                                                    </span>
                                                </p>
                                                <p class="text-base-content/80 truncate">{{ $comment->content }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @if ($fanfiction->comments->count() > 3)
                                    <x-button label="Alle {{ $fanfiction->comments->count() }} Kommentare anzeigen →" link="{{ route('fanfiction.show', $fanfiction) }}" class="btn-ghost btn-sm mt-3" />
                                @endif
                            @endif
                        </div>
                    </x-card>
                @endforeach
            </div>

            <div class="mt-8">
                {{ $fanfictions->links() }}
            </div>
        @endif
    </x-member-page>
</x-app-layout>