<x-member-page>
    @if($this->reviewRewardConfiguration['prominent_special_offer'])
        <x-review-baxx-special-offer :offer="$this->reviewRewardConfiguration['prominent_special_offer']" />
    @endif

    <x-ui.page-header
        eyebrow="Mitgliederbereich"
        title="Rezensionen"
        description="Entdecke Besprechungen zu Maddrax-Romanen, filtere nach Heft, Titel oder Autor und sieh sofort, wo bereits neue Stimmen zum Zyklus vorliegen."
    />

    <x-ui.panel title="Baxx für Rezensionen" class="mb-6">
        @if($this->reviewRewardConfiguration['effective_rule']['is_active'])
            <p class="text-base text-base-content">
                Aktuell erhältst du automatisch <strong>{{ $this->reviewRewardConfiguration['effective_rule']['rule_label'] }}</strong>.
            </p>
        @else
            <p class="text-base text-base-content">
                Aktuell gibt es keine Baxx für neue Rezensionen.
            </p>
        @endif
    </x-ui.panel>

    <div x-data="{ open: @js($this->filtersApplied) }" class="mb-6">
        <x-ui.panel title="Suche und Filter" description="Grenze die Übersicht gezielt nach Heftnummer, Titel, Autor oder Rezensionsstatus ein.">
            <x-slot:actions>
                <x-button
                    @click="open = !open"
                    class="btn-ghost"
                    x-bind:label="open ? 'Filter ausblenden' : 'Filter anzeigen'"
                >
                    <span class="flex items-center gap-2">
                        <span x-text="open ? 'Filter ausblenden' : 'Filter anzeigen'"></span>
                        <x-icon name="o-chevron-down" class="w-5 h-5 transform transition-transform" x-bind:class="{ 'rotate-180': open }" />
                    </span>
                </x-button>
            </x-slot:actions>

            <div x-show="open" x-transition>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <x-input wire:model.live.debounce.400ms="roman_number" label="Nr." type="number" />
                    <x-input wire:model.live.debounce.400ms="title_filter" label="Titel" />
                    <x-input wire:model.live.debounce.400ms="author" label="Autor" />
                    @php
                        $reviewStatusOptions = [
                            ['id' => '', 'name' => 'Alle'],
                            ['id' => 'with', 'name' => 'Mit Rezension'],
                            ['id' => 'without', 'name' => 'Ohne Rezension'],
                        ];
                    @endphp
                    <x-select
                        wire:model.live="review_status"
                        label="Rezensionsstatus"
                        :options="$reviewStatusOptions"
                        placeholder=""
                    />
                </div>
                <div class="mt-4 flex flex-col sm:flex-row sm:items-center gap-2">
                    <x-button label="Zurücksetzen" wire:click="resetFilters" class="btn-ghost" />
                </div>
            </div>
        </x-ui.panel>
    </div>

    <div id="accordion" wire:loading.class="opacity-50" wire:target="roman_number, title_filter, author, review_status">
        @php
            $volkDerTiefeRendered = false;
            $missionMarsRendered = false;
            $miniSeries2012Rendered = false;
            $abenteurerRendered = false;
        @endphp
        @foreach($booksByCycle as $cycle => $cycleBooks)
            @php
                $id = \Illuminate\Support\Str::slug($cycle);
            @endphp
            @include('reviews.partials.series-accordion', [
                'id' => $id,
                'title' => $cycle.'-Zyklus',
                'books' => $cycleBooks->sortByDesc('roman_number'),
                'initiallyOpen' => $loop->first,
            ])
            @if($cycle === 'Ursprung' && $abenteurer->isNotEmpty())
                @php $abenteurerRendered = true; @endphp
                @include('reviews.partials.spin-off-accordion', [
                    'id' => 'die-abenteurer',
                    'title' => 'Die Abenteurer',
                    'books' => $abenteurer,
                ])
            @endif
            @if($cycle === 'Ursprung' && $miniSeries2012->isNotEmpty())
                @php $miniSeries2012Rendered = true; @endphp
                @include('reviews.partials.spin-off-accordion', [
                    'id' => 'mini-serie-2012',
                    'title' => 'Mini-Serie 2012',
                    'books' => $miniSeries2012,
                ])
            @endif
            @if($cycle === 'Streiter' && !$abenteurerRendered && $abenteurer->isNotEmpty())
                @php $abenteurerRendered = true; @endphp
                @include('reviews.partials.spin-off-accordion', [
                    'id' => 'die-abenteurer',
                    'title' => 'Die Abenteurer',
                    'books' => $abenteurer,
                ])
            @endif
            @if($cycle === 'Streiter' && !$miniSeries2012Rendered && $miniSeries2012->isNotEmpty())
                @php $miniSeries2012Rendered = true; @endphp
                @include('reviews.partials.spin-off-accordion', [
                    'id' => 'mini-serie-2012',
                    'title' => 'Mini-Serie 2012',
                    'books' => $miniSeries2012,
                ])
            @endif
            @if($cycle === 'Mars' && $missionMars->isNotEmpty())
                @php $missionMarsRendered = true; @endphp
                @include('reviews.partials.spin-off-accordion', [
                    'id' => 'mission-mars',
                    'title' => 'Mission Mars-Heftromane',
                    'books' => $missionMars,
                ])
            @endif
            @if($cycle === 'Afra' && $volkDerTiefe->isNotEmpty())
                @php $volkDerTiefeRendered = true; @endphp
                @include('reviews.partials.spin-off-accordion', [
                    'id' => 'das-volk-der-tiefe',
                    'title' => 'Das Volk der Tiefe',
                    'books' => $volkDerTiefe,
                ])
            @endif
        @endforeach
        @if(!$abenteurerRendered && $abenteurer->isNotEmpty())
            @include('reviews.partials.spin-off-accordion', [
                'id' => 'die-abenteurer',
                'title' => 'Die Abenteurer',
                'books' => $abenteurer,
            ])
        @endif
        @if(!$miniSeries2012Rendered && $miniSeries2012->isNotEmpty())
            @include('reviews.partials.spin-off-accordion', [
                'id' => 'mini-serie-2012',
                'title' => 'Mini-Serie 2012',
                'books' => $miniSeries2012,
            ])
        @endif
        @if(!$missionMarsRendered && $missionMars->isNotEmpty())
            @include('reviews.partials.spin-off-accordion', [
                'id' => 'mission-mars',
                'title' => 'Mission Mars-Heftromane',
                'books' => $missionMars,
            ])
        @endif
        @if(!$volkDerTiefeRendered && $volkDerTiefe->isNotEmpty())
            @include('reviews.partials.spin-off-accordion', [
                'id' => 'das-volk-der-tiefe',
                'title' => 'Das Volk der Tiefe',
                'books' => $volkDerTiefe,
            ])
        @endif
        @if($hardcovers->isNotEmpty())
            @include('reviews.partials.series-accordion', [
                'id' => 'maddrax-hardcover',
                'title' => 'Maddrax-Hardcover',
                'books' => $hardcovers,
                'initiallyOpen' => false,
            ])
        @endif
    </div>
</x-member-page>
