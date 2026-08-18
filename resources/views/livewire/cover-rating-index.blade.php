<x-member-page class="max-w-6xl space-y-8">
    <x-ui.page-header
        eyebrow="Community"
        title="Cover-Bewertungen"
        description="Bewerte jedes Cover spontan mit 1 bis 5 Brinas. Nach deiner Stimme wartet direkt das nächste noch unbewertete Motiv."
        data-testid="cover-rating-header"
    >
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
                <x-button label="Ergebnisse" icon="o-chart-bar" link="{{ route('cover-ratings.results') }}" wire:navigate class="btn-outline" />
                <x-button label="Meine Bewertungen" icon="o-pencil-square" link="{{ route('cover-ratings.mine') }}" wire:navigate class="btn-outline" />
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 md:grid-cols-3" aria-label="Bewertungsfortschritt">
        <x-ui.panel title="Gesamtfortschritt" class="h-full">
            <p class="font-display text-3xl font-semibold tabular-nums" data-testid="global-progress">
                {{ $this->globalProgress['rated'] }} / {{ $this->globalProgress['total'] }}
            </p>
            <p class="mt-1 text-sm text-base-content/65">verfügbare Cover bewertet</p>
        </x-ui.panel>

        <x-ui.panel title="Aktueller Filter" class="h-full">
            <p class="font-display text-3xl font-semibold tabular-nums" data-testid="filter-progress">
                {{ $this->progress['remaining'] }}
            </p>
            <p class="mt-1 text-sm text-base-content/65">Cover noch offen</p>
        </x-ui.panel>

        <x-ui.panel title="Nächster Baxx" class="h-full">
            @if($this->rewardProgress['is_active'])
                <p class="font-display text-3xl font-semibold tabular-nums" data-testid="baxx-progress">
                    {{ $this->rewardProgress['completed_in_step'] }} / {{ $this->rewardProgress['every_count'] }}
                </p>
                <p class="mt-1 text-sm text-base-content/65">
                    noch {{ $this->rewardProgress['remaining'] }} bis zu {{ $this->rewardProgress['points'] }} Baxx
                </p>
            @else
                <p class="text-sm text-base-content/65">Aktuell ist keine Baxx-Regel aktiv.</p>
            @endif
        </x-ui.panel>
    </div>

    <div class="space-y-3" aria-live="polite" aria-atomic="true" data-testid="rating-status">
        @if($statusMessage !== '')
            <x-alert icon="o-check-circle" class="{{ $awardedBaxx > 0 ? 'alert-success' : 'alert-info' }}">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span>
                        {{ $statusMessage }}
                        @if($awardedBaxx > 0)
                            <strong>Du erhältst {{ $awardedBaxx }} Baxx.</strong>
                        @endif
                    </span>
                    @if($lastRatingId)
                        <button type="button" wire:click="undoLast" class="btn btn-sm btn-ghost" wire:loading.attr="disabled">
                            Rückgängig
                        </button>
                    @endif
                </div>
            </x-alert>
        @endif
    </div>

    <x-ui.panel title="Serie auswählen" description="Bei „Alle Serien“ wechseln sich die Reihen möglichst ausgewogen ab.">
        <div class="max-w-md">
            <x-select
                label="Serie"
                wire:model.live="series"
                :options="$this->seriesOptions"
                placeholder=""
                data-testid="series-filter"
            />
            @error('series')
                <p class="mt-2 text-sm text-error">{{ $message }}</p>
            @enderror
        </div>
    </x-ui.panel>

    <div
        x-data
        x-on:cover-rating-advanced.window="$nextTick(() => $el.querySelector('[data-cover-focus]')?.focus())"
    >
    @if($this->cover)
        @php($book = $this->cover->book)
        <article
            wire:key="cover-rating-{{ $this->cover->id }}"
            class="grid overflow-hidden rounded-[2rem] border border-base-content/10 bg-base-100/90 shadow-xl shadow-base-content/5 lg:grid-cols-[minmax(18rem,0.9fr)_minmax(22rem,1.1fr)]"
            data-testid="cover-rating-card"
        >
            <div class="flex min-h-[28rem] items-center justify-center bg-base-200/70 p-4 sm:p-8">
                <img
                    src="{{ route('cover-ratings.image', [$this->cover, 'large']) }}"
                    alt="Cover von {{ $book->type->label() }} Nummer {{ $book->roman_number }}: {{ $book->title }}"
                    class="max-h-[70vh] w-auto max-w-full rounded-xl object-contain shadow-2xl"
                    fetchpriority="high"
                    data-testid="current-cover-image"
                />
            </div>

            <div class="flex flex-col justify-center gap-7 p-6 sm:p-10">
                <div class="space-y-3">
                    <span class="badge badge-primary badge-outline rounded-full">{{ $book->type->label() }}</span>
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-base-content/45">Nummer {{ $book->roman_number }}</p>
                        <h2 tabindex="-1" class="mt-2 font-display text-3xl font-semibold tracking-tight" data-cover-title data-cover-focus>
                            {{ $book->title }}
                        </h2>
                        @if($book->author)
                            <p class="mt-2 text-base text-base-content/65">{{ $book->author }}</p>
                        @endif
                    </div>
                </div>

                <fieldset
                    x-data="{ preview: 0 }"
                    x-on:mouseleave="preview = 0"
                    class="space-y-4"
                    wire:loading.class="opacity-50"
                    wire:target="rate,skip"
                >
                    <legend class="font-display text-xl font-semibold">Wie gefällt dir dieses Cover?</legend>
                    <p class="text-sm text-base-content/65">Die Auswahl wird sofort gespeichert.</p>

                    <div class="flex flex-wrap gap-1 sm:gap-2" role="radiogroup" aria-label="Cover mit 1 bis 5 Brinas bewerten" data-testid="brina-rating-group">
                        @foreach(range(1, 5) as $value)
                            <div class="relative">
                                <input
                                    id="cover-{{ $this->cover->id }}-rating-{{ $value }}"
                                    type="radio"
                                    name="cover-rating-{{ $this->cover->id }}"
                                    value="{{ $value }}"
                                    class="peer sr-only"
                                    aria-label="{{ $value }} von 5 {{ $value === 1 ? 'Brina' : 'Brinas' }}"
                                    wire:change="rate({{ $value }})"
                                    wire:loading.attr="disabled"
                                    wire:target="rate,skip"
                                />
                                <label
                                    for="cover-{{ $this->cover->id }}-rating-{{ $value }}"
                                    class="brina-rating-option"
                                    x-on:mouseenter="preview = {{ $value }}"
                                    x-on:focusin="preview = {{ $value }}"
                                >
                                    <img
                                        src="{{ asset('images/brina-rating.webp') }}"
                                        alt=""
                                        aria-hidden="true"
                                        class="brina-rating-icon"
                                        x-bind:class="preview >= {{ $value }} ? 'brina-rating-icon--filled' : 'brina-rating-icon--empty'"
                                    />
                                    <span class="sr-only">{{ $value }} {{ $value === 1 ? 'Brina' : 'Brinas' }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('rating')
                        <p class="text-sm font-semibold text-error" role="alert">{{ $message }}</p>
                    @enderror
                </fieldset>

                <div class="flex flex-col gap-3 border-t border-base-content/10 pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <button
                        type="button"
                        wire:click="skip"
                        wire:loading.attr="disabled"
                        wire:target="rate,skip"
                        class="btn btn-ghost"
                        data-testid="skip-cover"
                    >
                        Später bewerten
                    </button>

                    @if($this->cover->source_description_url)
                        <a
                            href="{{ $this->cover->source_description_url }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-sm font-medium text-base-content/60 underline decoration-base-content/25 underline-offset-4 hover:text-primary"
                        >
                            Bildquelle im Maddraxikon
                        </a>
                    @endif
                </div>
            </div>
        </article>
    @else
        <x-ui.panel data-testid="cover-rating-empty-state">
            <div class="mx-auto max-w-2xl space-y-5 py-10 text-center">
                @if($this->progress['total'] === 0)
                    <x-icon name="o-photo" class="mx-auto h-16 w-16 text-base-content/30" />
                    <h2 tabindex="-1" data-cover-focus class="font-display text-2xl font-semibold">Noch keine Cover verfügbar</h2>
                    <p class="text-base-content/65">Sobald der Cover-Abgleich abgeschlossen ist, kannst du hier loslegen.</p>
                @elseif($this->progress['remaining'] === 0)
                    <x-icon name="o-trophy" class="mx-auto h-16 w-16 text-primary" />
                    <h2 tabindex="-1" data-cover-focus class="font-display text-2xl font-semibold">Alle Cover bewertet</h2>
                    <p class="text-base-content/65">Du hast in diesem Filter jedes verfügbare Cover bewertet.</p>
                    <div class="flex flex-wrap justify-center gap-3">
                        <x-button label="Ergebnisse ansehen" link="{{ route('cover-ratings.results') }}" wire:navigate class="btn-primary" />
                        <x-button label="Bewertungen verwalten" link="{{ route('cover-ratings.mine') }}" wire:navigate class="btn-outline" />
                    </div>
                @else
                    <x-icon name="o-clock" class="mx-auto h-16 w-16 text-base-content/35" />
                    <h2 tabindex="-1" data-cover-focus class="font-display text-2xl font-semibold">Für diese Sitzung zurückgestellt</h2>
                    <p class="text-base-content/65">Alle noch offenen Cover dieses Filters wurden übersprungen. Beim nächsten Besuch erscheinen sie wieder.</p>
                @endif
            </div>
        </x-ui.panel>
    @endif
    </div>
</x-member-page>
