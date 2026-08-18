<x-member-page class="max-w-7xl space-y-8">
    <x-ui.page-header
        eyebrow="Cover-Bewertungen"
        title="Meine Bewertungen"
        description="Passe eigene Stimmen an oder lösche sie. Gelöschte Cover werden wieder in den Bewertungsfluss aufgenommen, zählen aber nicht erneut für Baxx."
    >
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
                <x-button label="Weiter bewerten" icon="o-sparkles" link="{{ route('cover-ratings.index') }}" wire:navigate class="btn-primary" />
                <x-button label="Ergebnisse" icon="o-chart-bar" link="{{ route('cover-ratings.results') }}" wire:navigate class="btn-outline" />
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div aria-live="polite" aria-atomic="true">
        @if($statusMessage !== '')
            <x-alert icon="o-check-circle" class="alert-success" dismissible>{{ $statusMessage }}</x-alert>
        @endif
    </div>

    <x-ui.panel title="Filter">
        <div class="max-w-md">
            <x-select label="Serie" wire:model.live="series" :options="$this->seriesOptions" placeholder="" />
        </div>
    </x-ui.panel>

    <div wire:loading.class="opacity-50" wire:target="series,updateRating,deleteRating">
        @if($this->ratings->isEmpty())
            <x-ui.panel data-testid="my-cover-ratings-empty">
                <div class="py-10 text-center">
                    <x-icon name="o-pencil-square" class="mx-auto h-14 w-14 text-base-content/30" />
                    <h2 class="mt-4 font-display text-2xl font-semibold">Noch keine Bewertungen</h2>
                    <p class="mt-2 text-base-content/65">Sobald du ein Cover bewertest, kannst du deine Stimme hier verwalten.</p>
                </div>
            </x-ui.panel>
        @else
            <div class="grid gap-5 lg:grid-cols-2" data-testid="my-cover-ratings-grid">
                @foreach($this->ratings as $rating)
                    <article class="grid grid-cols-[6.5rem_1fr] gap-4 rounded-[1.5rem] border border-base-content/10 bg-base-100/90 p-4 shadow-sm" data-testid="my-cover-rating-card">
                        <img
                            src="{{ route('cover-ratings.image', [$rating->bookCover, 'small']) }}"
                            alt="Cover von {{ $rating->bookCover->book->type->label() }} Nummer {{ $rating->bookCover->book->roman_number }}: {{ $rating->bookCover->book->title }}"
                            class="h-36 w-full rounded-lg bg-base-200 object-contain"
                            loading="lazy"
                        />
                        <div class="flex min-w-0 flex-col justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-base-content/45">{{ $rating->bookCover->book->type->label() }} · Nr. {{ $rating->bookCover->book->roman_number }}</p>
                                <h2 class="mt-1 truncate font-display text-lg font-semibold">{{ $rating->bookCover->book->title }}</h2>
                            </div>

                            <div>
                                <p class="mb-2 text-sm font-medium">Deine Bewertung: {{ $rating->rating }} {{ $rating->rating === 1 ? 'Brina' : 'Brinas' }}</p>
                                <div class="flex flex-wrap items-center gap-1" role="group" aria-label="Bewertung ändern">
                                    @foreach(range(1, 5) as $value)
                                        <button
                                            type="button"
                                            wire:click="updateRating({{ $rating->id }}, {{ $value }})"
                                            wire:loading.attr="disabled"
                                            class="brina-edit-option {{ $rating->rating === $value ? 'brina-edit-option--selected' : '' }}"
                                            aria-label="Auf {{ $value }} {{ $value === 1 ? 'Brina' : 'Brinas' }} ändern"
                                            aria-pressed="{{ $rating->rating === $value ? 'true' : 'false' }}"
                                        >
                                            <img src="{{ asset('images/brina-rating.webp') }}" alt="" aria-hidden="true" class="brina-edit-icon {{ $rating->rating >= $value ? 'brina-rating-icon--filled' : 'brina-rating-icon--empty' }}" />
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <button
                                type="button"
                                wire:click="deleteRating({{ $rating->id }})"
                                wire:confirm="Möchtest du diese Bewertung wirklich löschen?"
                                wire:loading.attr="disabled"
                                class="btn btn-error btn-outline btn-sm w-fit"
                            >
                                Bewertung löschen
                            </button>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-8">{{ $this->ratings->links() }}</div>
        @endif
    </div>
</x-member-page>
