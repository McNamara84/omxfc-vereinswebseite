<div data-testid="cover-rating-root">
{{-- Keep the Alpine root below Livewire's component root so late hydration cannot tear down Livewire itself. --}}
<div x-data="coverRatingSession">
    <x-member-page class="max-w-6xl space-y-5 sm:space-y-6">
        <section
            class="relative overflow-hidden rounded-[2rem] border border-base-content/10 bg-base-100/90 px-5 py-5 shadow-lg shadow-base-content/5 backdrop-blur sm:px-7 sm:py-6"
            data-testid="cover-rating-header"
        >
            <div class="absolute inset-x-0 top-0 h-1 bg-primary"></div>

            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl space-y-2">
                    <p class="text-[0.68rem] font-semibold uppercase tracking-[0.26em] text-base-content/45">Community</p>
                    <h1 class="font-display text-3xl font-semibold tracking-tight text-base-content sm:text-4xl">Cover-Bewertungen</h1>
                    <p class="text-sm leading-relaxed text-base-content/70 sm:text-base">
                        Wähle eine Serie und bewerte die Cover anschließend ungestört im Vollbildmodus.
                    </p>
                </div>

                <nav class="flex flex-wrap gap-2" aria-label="Weitere Cover-Bereiche">
                    <x-button label="Ergebnisse" icon="o-chart-bar" link="{{ route('cover-ratings.results') }}" wire:navigate class="btn-sm btn-outline" />
                    <x-button label="Meine Bewertungen" icon="o-pencil-square" link="{{ route('cover-ratings.mine') }}" wire:navigate class="btn-sm btn-outline" />
                </nav>
            </div>
        </section>

        <div
            class="grid grid-cols-2 gap-3 lg:grid-cols-3"
            aria-label="Bewertungsfortschritt"
            data-testid="cover-rating-overview"
        >
            <section class="rounded-2xl border border-base-content/10 bg-base-100/90 p-4 shadow-md shadow-base-content/5 sm:p-5">
                <h2 class="text-sm font-semibold text-base-content/65">Gesamtfortschritt</h2>
                <p class="mt-2 font-display text-2xl font-semibold tabular-nums sm:text-3xl" data-testid="global-progress">
                    {{ $this->globalProgress['rated'] }} / {{ $this->globalProgress['total'] }}
                </p>
                <p class="mt-1 text-xs text-base-content/60 sm:text-sm">verfügbare Cover bewertet</p>
            </section>

            <section class="rounded-2xl border border-base-content/10 bg-base-100/90 p-4 shadow-md shadow-base-content/5 sm:p-5">
                <h2 class="text-sm font-semibold text-base-content/65">Aktueller Filter</h2>
                <p class="mt-2 font-display text-2xl font-semibold tabular-nums sm:text-3xl" data-testid="filter-progress">
                    {{ $this->progress['remaining'] }}
                </p>
                <p class="mt-1 text-xs text-base-content/60 sm:text-sm">Cover noch offen</p>
            </section>

            <section class="col-span-2 rounded-2xl border border-base-content/10 bg-base-100/90 p-4 shadow-md shadow-base-content/5 sm:p-5 lg:col-span-1">
                <h2 class="text-sm font-semibold text-base-content/65">Nächster Baxx</h2>
                @if($this->rewardProgress['is_active'])
                    <p class="mt-2 font-display text-2xl font-semibold tabular-nums sm:text-3xl" data-testid="baxx-progress">
                        {{ $this->rewardProgress['completed_in_step'] }} / {{ $this->rewardProgress['every_count'] }}
                    </p>
                    <p class="mt-1 text-xs text-base-content/60 sm:text-sm">
                        noch {{ $this->rewardProgress['remaining'] }} bis zu {{ $this->rewardProgress['points'] }} Baxx
                    </p>
                @else
                    <p class="mt-3 text-sm text-base-content/60">Aktuell ist keine Baxx-Regel aktiv.</p>
                @endif
            </section>
        </div>

        <section class="rounded-[1.75rem] border border-base-content/10 bg-base-100/90 p-5 shadow-lg shadow-base-content/5 sm:p-6">
            <div class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                <div class="w-full max-w-md">
                    <x-select
                        label="Serie auswählen"
                        wire:model.live="series"
                        :options="$this->seriesOptions"
                        placeholder=""
                        data-testid="series-filter"
                    />
                    <p class="mt-2 text-xs text-base-content/60">
                        Bei „Alle Serien“ wechseln sich die Reihen möglichst ausgewogen ab.
                    </p>
                    @error('series')
                        <p class="mt-2 text-sm font-semibold text-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <button
                    type="button"
                    x-ref="startButton"
                    x-on:click="start($event)"
                    x-bind:aria-busy="fullscreenRequestPending || fullscreenExitPromise ? 'true' : 'false'"
                    x-bind:aria-disabled="fullscreenRequestPending || fullscreenExitPromise ? 'true' : null"
                    class="btn btn-primary min-h-12 w-full px-7 md:w-auto"
                    data-testid="start-cover-rating"
                    @disabled(! $this->cover)
                >
                    <x-icon name="o-arrows-pointing-out" class="h-5 w-5" />
                    Bewertung starten
                </button>
            </div>

            @if(! $this->cover)
                <div
                    tabindex="-1"
                    role="status"
                    class="mt-5 rounded-2xl border border-base-content/10 bg-base-200/55 p-4 text-sm text-base-content/70 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                    data-cover-return-focus
                    data-testid="cover-rating-overview-empty-state"
                >
                    @if($this->progress['total'] === 0)
                        Noch sind keine Cover verfügbar. Sobald der Cover-Abgleich abgeschlossen ist, kannst du hier loslegen.
                    @elseif($this->progress['remaining'] === 0)
                        Du hast in diesem Filter alle verfügbaren Cover bewertet.
                    @else
                        Du hast alle noch offenen Cover dieses Filters für diese Sitzung zurückgestellt. Beim nächsten Besuch erscheinen sie wieder.
                    @endif
                </div>
            @endif
        </section>
    </x-member-page>

    <section
        x-ref="session"
        x-bind:aria-hidden="active ? 'false' : 'true'"
        x-bind:inert="!active"
        x-trap.noscroll.inert="active"
        class="cover-rating-session"
        role="dialog"
        aria-modal="true"
        aria-labelledby="cover-rating-session-title"
        data-testid="cover-rating-session"
    >
        <div
            x-show="feedbackVisible"
            x-transition.opacity.duration.150ms
            class="cover-rating-session__feedback"
            aria-live="polite"
            aria-atomic="true"
            data-testid="rating-status"
        >
            @if($statusMessage !== '')
                <div
                    class="flex max-w-xl flex-wrap items-center justify-between gap-3 rounded-2xl border border-base-content/15 px-4 py-3 text-sm shadow-2xl backdrop-blur-md {{ $awardedBaxx > 0 ? 'bg-success text-success-content' : 'bg-base-100/95 text-base-content' }}"
                    data-rating-feedback
                >
                    <span>
                        {{ $statusMessage }}
                        @if($awardedBaxx > 0)
                            <strong>Du erhältst {{ $awardedBaxx }} Baxx.</strong>
                        @endif
                    </span>
                    @if($lastRatingId)
                        <button
                            type="button"
                            wire:click="undoLast"
                            wire:loading.attr="disabled"
                            wire:target="undoLast"
                            class="btn btn-sm btn-ghost"
                        >
                            Rückgängig
                        </button>
                    @endif
                </div>
            @endif
        </div>

        @if($this->cover)
            @php($book = $this->cover->book)
            <header class="cover-rating-session__header flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-base-content/60">
                        <span class="badge badge-primary badge-outline badge-sm shrink-0">{{ $book->type->label() }}</span>
                        <span class="truncate">Nummer {{ $book->roman_number }}</span>
                    </div>
                    <h2
                        id="cover-rating-session-title"
                        tabindex="-1"
                        class="mt-1 truncate font-display text-lg font-semibold tracking-tight sm:text-2xl"
                        data-cover-focus
                    >
                        {{ $book->title }}
                    </h2>
                    @if($book->author)
                        <p class="hidden truncate text-xs text-base-content/60 sm:block sm:text-sm">{{ $book->author }}</p>
                    @endif
                </div>

                @if($this->cover->source_description_url)
                    <a
                        href="{{ $this->cover->source_description_url }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="Bildquelle im Maddraxikon öffnen"
                        class="btn btn-xs btn-ghost shrink-0 sm:btn-sm"
                        data-testid="cover-source-link"
                    >
                        <x-icon name="o-arrow-top-right-on-square" class="h-4 w-4" />
                        <span class="hidden sm:inline">Bildquelle im Maddraxikon</span>
                        <span class="sm:hidden">Quelle</span>
                    </a>
                @endif
            </header>

            <div
                wire:key="cover-rating-image-{{ $this->cover->id }}"
                class="cover-rating-session__image-stage"
                data-testid="cover-rating-image-stage"
            >
                <img
                    src="{{ route('cover-ratings.image', [$this->cover, 'large']) }}"
                    alt="Cover von {{ $book->type->label() }} Nummer {{ $book->roman_number }}: {{ $book->title }}"
                    class="cover-rating-session__image"
                    fetchpriority="high"
                    draggable="false"
                    data-testid="current-cover-image"
                />
            </div>

            <footer class="cover-rating-session__controls">
                <fieldset
                    wire:key="cover-rating-controls-{{ $this->cover->id }}"
                    x-on:cover-rating-advanced.window="ratingPreview = 0"
                    x-on:mouseleave="ratingPreview = 0"
                    class="min-w-0"
                    wire:loading.class="opacity-50"
                    wire:target="rate,skip"
                    data-brina-rating-controls
                >
                    <legend class="sr-only">Wie gefällt dir dieses Cover?</legend>
                    <div
                        class="flex items-center justify-center gap-0.5 sm:gap-1"
                        role="radiogroup"
                        aria-label="Cover mit 1 bis 5 Brinas bewerten"
                        data-testid="brina-rating-group"
                    >
                        @foreach(range(1, 5) as $value)
                            <div class="relative">
                                <input
                                    id="cover-{{ $this->cover->id }}-rating-{{ $value }}"
                                    type="radio"
                                    name="cover-rating-{{ $this->cover->id }}"
                                    value="{{ $value }}"
                                    class="peer sr-only"
                                    aria-label="{{ $value }} von 5 {{ $value === 1 ? 'Brina' : 'Brinas' }}"
                                    x-on:focus="ratingPreview = {{ $value }}"
                                    wire:change="rate({{ $value }})"
                                    wire:loading.attr="disabled"
                                    wire:target="rate,skip"
                                />
                                <label
                                    for="cover-{{ $this->cover->id }}-rating-{{ $value }}"
                                    class="brina-rating-option"
                                    x-on:mousemove="ratingPreview = {{ $value }}"
                                >
                                    <img
                                        src="{{ asset('images/brina-rating.webp') }}"
                                        alt=""
                                        aria-hidden="true"
                                        class="brina-rating-icon"
                                        x-bind:class="ratingPreview >= {{ $value }} ? 'brina-rating-icon--filled' : 'brina-rating-icon--empty'"
                                    />
                                    <span class="sr-only">{{ $value }} {{ $value === 1 ? 'Brina' : 'Brinas' }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('rating')
                        <p class="mt-1 text-center text-xs font-semibold text-error" role="alert">{{ $message }}</p>
                    @enderror
                </fieldset>

                <div class="flex shrink-0 items-center justify-center gap-2">
                    <button
                        type="button"
                        wire:click="skip"
                        wire:loading.attr="disabled"
                        wire:target="rate,skip"
                        class="btn btn-sm btn-ghost sm:btn-md"
                        data-testid="skip-cover"
                    >
                        Später bewerten
                    </button>
                    <button
                        type="button"
                        x-on:click="stop()"
                        class="btn btn-sm btn-outline sm:btn-md"
                        data-testid="end-cover-rating"
                    >
                        Bewertungen beenden
                    </button>
                </div>
            </footer>
        @else
            <header class="cover-rating-session__header">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-base-content/60">Cover-Bewertungen</p>
                    <h2 id="cover-rating-session-title" class="mt-1 font-display text-xl font-semibold sm:text-2xl">Sitzung abgeschlossen</h2>
                </div>
            </header>

            <div class="cover-rating-session__empty" data-testid="cover-rating-empty-state">
                @if($this->progress['total'] === 0)
                    <x-icon name="o-photo" class="mx-auto h-14 w-14 text-base-content/30" />
                    <h3 tabindex="-1" data-cover-empty-focus class="font-display text-2xl font-semibold">Noch keine Cover verfügbar</h3>
                    <p class="text-base-content/65">Sobald der Cover-Abgleich abgeschlossen ist, kannst du hier loslegen.</p>
                @elseif($this->progress['remaining'] === 0)
                    <x-icon name="o-trophy" class="mx-auto h-14 w-14 text-primary" />
                    <h3 tabindex="-1" data-cover-empty-focus class="font-display text-2xl font-semibold">Alle Cover bewertet</h3>
                    <p class="text-base-content/65">Du hast in diesem Filter jedes verfügbare Cover bewertet.</p>
                    <div class="flex flex-wrap justify-center gap-2">
                        <x-button label="Ergebnisse ansehen" link="{{ route('cover-ratings.results') }}" wire:navigate class="btn-primary" />
                        <x-button label="Bewertungen verwalten" link="{{ route('cover-ratings.mine') }}" wire:navigate class="btn-outline" />
                    </div>
                @else
                    <x-icon name="o-clock" class="mx-auto h-14 w-14 text-base-content/35" />
                    <h3 tabindex="-1" data-cover-empty-focus class="font-display text-2xl font-semibold">Für diese Sitzung zurückgestellt</h3>
                    <p class="text-base-content/65">Alle noch offenen Cover dieses Filters wurden übersprungen. Beim nächsten Besuch erscheinen sie wieder.</p>
                @endif
            </div>

            <footer class="cover-rating-session__controls cover-rating-session__controls--empty">
                <button
                    type="button"
                    x-on:click="stop()"
                    class="btn btn-primary"
                    data-testid="end-cover-rating"
                >
                    Zur Übersicht
                </button>
            </footer>
        @endif
    </section>
</div>
</div>
