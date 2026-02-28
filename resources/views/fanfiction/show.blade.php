<x-app-layout>
    <x-member-page class="max-w-4xl">
        <x-header title="{{ $fanfiction->title }}" separator>
            <x-slot:actions>
                <x-button label="Zurück" icon="o-arrow-left" link="{{ route('fanfiction.index') }}" class="btn-ghost" />
            </x-slot:actions>
        </x-header>

        <x-card shadow>
            {{-- Header mit Autor und Datum --}}
            <div class="mb-6 pb-6 border-b border-base-content/10">
                <h1 class="text-2xl md:text-3xl font-bold mb-2 flex items-center gap-3">
                    {{ $fanfiction->title }}
                    @if ($fanfiction->reward)
                        @if ($hasUnlocked)
                            <x-badge value="Freigeschaltet" class="badge-success" />
                        @else
                            <x-badge value="{{ $fanfiction->reward->cost_baxx }} Baxx" class="badge-warning" />
                        @endif
                    @else
                        <x-badge value="Kostenlos" class="badge-success" />
                    @endif
                </h1>
                <p class="text-base-content">
                    von <span class="font-medium">{{ $fanfiction->author_display_name }}</span>
                    @if ($fanfiction->published_at)
                        • Veröffentlicht am {{ $fanfiction->published_at->format('d.m.Y') }}
                    @endif
                </p>
            </div>

            @if ($hasUnlocked)
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
                    {!! $fanfiction->formatted_content !!}
                </div>

                {{-- Teilen-Hinweis --}}
                <div class="mt-8 pt-6 border-t border-base-content/10">
                    <p class="text-sm text-base-content">
                        Hat dir die Geschichte gefallen? Teile sie mit anderen Mitgliedern!
                    </p>
                </div>
            @else
                {{-- Teaser für nicht freigeschaltete Fanfiction --}}
                <div class="prose dark:prose-invert max-w-none mb-8">
                    <p class="italic text-base-content/70">{{ $fanfiction->teaser }}</p>
                </div>

                {{-- Kauf-Bereich --}}
                <div class="p-6 bg-base-200 rounded-xl text-center">
                    <x-icon name="o-lock-closed" class="w-12 h-12 mx-auto mb-3 text-warning" />
                    <h3 class="text-lg font-semibold mb-2">Diese Geschichte ist gesperrt</h3>
                    <p class="text-sm text-base-content mb-1">
                        Preis: <strong>{{ $fanfiction->reward->cost_baxx }} Baxx</strong>
                    </p>
                    <p class="text-sm text-base-content mb-4">
                        Dein Guthaben: <strong>{{ $availableBaxx }} Baxx</strong>
                    </p>

                    @if (! $fanfiction->reward->is_active)
                        <p class="text-sm text-base-content/60 font-medium">
                            Diese Fanfiction ist derzeit nicht verfügbar.
                        </p>
                    @elseif ($availableBaxx >= $fanfiction->reward->cost_baxx)
                        <form action="{{ route('fanfiction.purchase', $fanfiction) }}" method="POST"
                            onsubmit="return confirm('Möchtest du diese Fanfiction für {{ $fanfiction->reward->cost_baxx }} Baxx freischalten?')">
                            @csrf
                            <x-button type="submit" label="Für {{ $fanfiction->reward->cost_baxx }} Baxx freischalten"
                                icon="o-lock-open" class="btn-primary" />
                        </form>
                    @else
                        <p class="text-sm text-error font-medium">
                            Dir fehlen noch {{ $fanfiction->reward->cost_baxx - $availableBaxx }} Baxx.
                        </p>
                    @endif

                    @error('reward')
                        <p class="mt-2 text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>
            @endif
        </x-card>

        {{-- Kommentare (nur für freigeschaltete Fanfiction) --}}
        @if ($hasUnlocked)
            <x-card shadow class="mt-8">
                <x-header title="Kommentare ({{ $fanfiction->comments->count() }})" size="text-lg" class="!mb-4" />

                {{-- Kommentar-Formular --}}
                <form action="{{ route('fanfiction.comments.store', $fanfiction) }}" method="POST" class="mb-8">
                    @csrf
                    <x-textarea
                        name="content"
                        id="content"
                        rows="3"
                        label="Kommentar"
                        aria-label="Kommentar"
                        placeholder="Schreibe einen Kommentar..."
                        required
                    >{{ old('content') }}</x-textarea>
                    @error('content')
                        <p class="mt-1 text-sm text-error">{{ $message }}</p>
                    @enderror
                    <div class="flex justify-end mt-4">
                        <x-button type="submit" label="Kommentieren" icon="o-paper-airplane" class="btn-primary" />
                    </div>
                </form>

                {{-- Kommentarliste --}}
                @if ($fanfiction->comments->isEmpty())
                    <p class="text-base-content text-center py-4">
                        Noch keine Kommentare. Sei der Erste!
                    </p>
                @else
                    <div class="space-y-6">
                        @foreach ($fanfiction->comments->whereNull('parent_id') as $comment)
                            <x-fanfiction-comment :comment="$comment" :fanfiction="$fanfiction" />
                        @endforeach
                    </div>
                @endif
            </x-card>
        @endif
    </x-member-page>
</x-app-layout>
