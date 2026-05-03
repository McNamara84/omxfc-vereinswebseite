<x-app-layout>
    <x-member-page class="max-w-4xl">
        @php
            $pageDescription = 'von '.$fanfiction->author_display_name;

            if ($fanfiction->published_at) {
                $pageDescription .= ' • Veröffentlicht am '.$fanfiction->published_at->format('d.m.Y');
            }
        @endphp

        <x-ui.page-header title="{{ $fanfiction->title }}" description="{{ $pageDescription }}">
            <x-slot:actions>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($fanfiction->reward)
                        @if ($isOwnFanfiction)
                            <x-badge value="Eigener Beitrag" class="badge-info" icon="o-pencil-square" />
                        @elseif ($hasUnlocked)
                            <x-badge value="Freigeschaltet" class="badge-success" icon="o-lock-open" />
                        @else
                            <x-badge value="{{ $fanfiction->reward->cost_baxx }} Baxx" class="badge-warning" icon="o-currency-dollar" />
                        @endif
                    @else
                        <x-badge value="Kostenlos" class="badge-success" icon="o-gift" />
                    @endif

                    <x-button label="Zurück" icon="o-arrow-left" link="{{ route('fanfiction.index') }}" wire:navigate class="btn-ghost" />
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        @if ($walletWarning)
            <x-alert icon="o-exclamation-triangle" class="alert-warning mb-6" dismissible>
                {{ $walletWarning }}
            </x-alert>
        @endif

        @if (($autoRefundedPurchases ?? 0) > 0)
            <x-alert icon="o-arrow-uturn-left" class="alert-info mb-6" dismissible>
                @if ($autoRefundedPurchases === 1)
                    Ein früherer Eigenkauf deiner Fanfiction wurde automatisch erstattet.
                @else
                    {{ $autoRefundedPurchases }} frühere Eigenkäufe deiner Fanfiction wurden automatisch erstattet.
                @endif
            </x-alert>
        @endif

        <x-ui.panel>
            @if ($hasUnlocked)
                {{-- Galerie nur für Bilder, die NICHT per [bild:N] im Text referenziert werden --}}
                @php $unreferencedPhotos = $fanfiction->getUnreferencedPhotos(); @endphp
                @if (count($unreferencedPhotos) > 0)
                    <div class="mb-8">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4" data-testid="fanfiction-gallery">
                            @foreach ($unreferencedPhotos as $photo)
                                <a href="{{ Storage::url($photo) }}" target="_blank"
                                    class="block aspect-square overflow-hidden rounded-lg hover:opacity-90 transition-opacity">
                                    <img src="{{ Storage::url($photo) }}"
                                        alt="{{ $fanfiction->title }} – Bild {{ $loop->iteration }}"
                                        class="w-full h-full object-cover">
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Story-Inhalt (Markdown) --}}
                <div class="fanfiction-content prose dark:prose-invert max-w-none mb-8">
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
                    @if (! $walletWarning)
                        <p class="text-sm text-base-content mb-4">
                            Dein Guthaben: <strong>{{ $availableBaxx }} Baxx</strong>
                        </p>
                    @endif

                    @if (! $fanfiction->reward->is_active)
                        <p class="text-sm text-base-content/60 font-medium">
                            Diese Fanfiction ist derzeit nicht verfügbar.
                        </p>
                    @elseif ($walletWarning)
                        <p class="text-sm text-base-content/70 font-medium">
                            Neue Freischaltungen sind erst wieder möglich, wenn die ältere Baxx-Historie geprüft wurde.
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
        </x-ui.panel>

        {{-- Kommentare (nur für freigeschaltete Fanfiction) --}}
        @if ($hasUnlocked)
            <x-ui.panel title="Kommentare ({{ $fanfiction->comments->count() }})" class="mt-8">

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
            </x-ui.panel>
        @endif
    </x-member-page>
</x-app-layout>
