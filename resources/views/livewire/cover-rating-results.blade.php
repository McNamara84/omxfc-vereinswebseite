<x-member-page class="max-w-7xl space-y-8">
    <x-ui.page-header
        eyebrow="Cover-Bewertungen"
        title="Ergebnisse"
        description="Anonyme Durchschnittswerte der Cover, die du bereits selbst bewertet hast. Vorher bleiben Ergebnisse bewusst verborgen."
    >
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
                <x-button label="Weiter bewerten" icon="o-sparkles" link="{{ route('cover-ratings.index') }}" wire:navigate class="btn-primary" />
                <x-button label="Meine Bewertungen" icon="o-pencil-square" link="{{ route('cover-ratings.mine') }}" wire:navigate class="btn-outline" />
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.panel title="Filtern und sortieren">
        <div class="grid gap-4 md:grid-cols-2">
            <x-select label="Serie" wire:model.live="series" :options="$this->seriesOptions" placeholder="" />
            <x-select label="Sortierung" wire:model.live="sort" :options="$this->sortOptions" placeholder="" />
        </div>
    </x-ui.panel>

    <div wire:loading.class="opacity-50" wire:target="series,sort">
        @if($this->results->isEmpty())
            <x-ui.panel data-testid="cover-results-empty">
                <div class="py-10 text-center">
                    <x-icon name="o-chart-bar" class="mx-auto h-14 w-14 text-base-content/30" />
                    <h2 class="mt-4 font-display text-2xl font-semibold">Noch keine sichtbaren Ergebnisse</h2>
                    <p class="mt-2 text-base-content/65">Bewerte zuerst ein Cover, damit dessen anonymes Ergebnis für dich sichtbar wird.</p>
                </div>
            </x-ui.panel>
        @else
            <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3" data-testid="cover-results-grid">
                @foreach($this->results as $cover)
                    @php
                        $average = (float) ($cover->ratings_avg_rating ?? 0);
                        $enoughVotes = $cover->ratings_count >= $minimumVotes;
                    @endphp
                    <article class="overflow-hidden rounded-[1.75rem] border border-base-content/10 bg-base-100/90 shadow-lg shadow-base-content/5">
                        <div class="aspect-[3/4] bg-base-200/70 p-4">
                            <img
                                src="{{ route('cover-ratings.image', [$cover, 'small']) }}"
                                alt="Cover von {{ $cover->book->type->label() }} Nummer {{ $cover->book->roman_number }}: {{ $cover->book->title }}"
                                class="h-full w-full rounded-lg object-contain"
                                loading="lazy"
                            />
                        </div>
                        <div class="space-y-4 p-5">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-base-content/45">{{ $cover->book->type->label() }} · Nr. {{ $cover->book->roman_number }}</p>
                                <h2 class="mt-2 font-display text-xl font-semibold">{{ $cover->book->title }}</h2>
                            </div>

                            @if($enoughVotes)
                                <div class="space-y-2" aria-label="Durchschnittlich {{ number_format($average, 2, ',', '.') }} von 5 Brinas bei {{ $cover->ratings_count }} Stimmen">
                                    <div class="flex gap-0.5" aria-hidden="true">
                                        @foreach(range(1, 5) as $value)
                                            <img
                                                src="{{ asset('images/brina-rating.webp') }}"
                                                alt=""
                                                class="brina-result-icon {{ $average >= ($value - 0.5) ? 'brina-rating-icon--filled' : 'brina-rating-icon--empty' }}"
                                            />
                                        @endforeach
                                    </div>
                                    <p class="text-sm font-semibold tabular-nums">
                                        {{ number_format($average, 2, ',', '.') }} / 5 · {{ $cover->ratings_count }} Stimmen
                                    </p>
                                </div>
                            @else
                                <div class="rounded-xl bg-base-200/70 px-4 py-3 text-sm text-base-content/65">
                                    Noch nicht genügend Bewertungen (mindestens {{ $minimumVotes }}).
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-8">{{ $this->results->links() }}</div>
        @endif
    </div>
</x-member-page>
