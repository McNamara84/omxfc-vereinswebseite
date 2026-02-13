<x-app-layout :title="$title" :description="$description">
    <x-member-page>
            <x-card shadow class="mb-6">
                <x-header title="Rezensionen" class="!mb-2" />
                <p class="text-base text-base-content">
                    Für jede <strong>zehnte</strong> verfasste Rezension erhältst du automatisch
                    <strong>1 Baxx</strong>.
                </p>
            </x-card>
            @php
                $filtersApplied = request()->filled('roman_number') || request()->filled('title') || request()->filled('author') || request()->filled('review_status');
            @endphp
            @php
                $reviewStatusOptions = [
                    ['id' => '', 'name' => 'Alle'],
                    ['id' => 'with', 'name' => 'Mit Rezension'],
                    ['id' => 'without', 'name' => 'Ohne Rezension'],
                ];
            @endphp
            <div x-data="{ open: @js($filtersApplied) }" class="mb-6">
                <x-button
                    @click="open = !open"
                    class="w-full flex justify-between items-center btn-ghost bg-base-100 shadow-xs rounded-lg p-4"
                >
                    <span class="font-semibold text-base-content" x-text="open ? 'Filter ausblenden' : 'Filter anzeigen'"></span>
                    <x-icon name="o-chevron-down" class="w-5 h-5 transform transition-transform" x-bind:class="{ 'rotate-180': open }" />
                </x-button>
                <div x-show="open" x-transition class="mt-4">
                    <form method="GET" action="{{ route('reviews.index') }}" class="bg-base-100 shadow-xs rounded-lg p-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <x-input name="roman_number" label="Nr." value="{{ request('roman_number') }}" />
                            <x-input name="title" label="Titel" value="{{ request('title') }}" />
                            <x-input name="author" label="Autor" value="{{ request('author') }}" />
                            <x-select
                                name="review_status"
                                label="Rezensionsstatus"
                                :options="$reviewStatusOptions"
                                :value="request('review_status', '')"
                                placeholder=""
                            />
                        </div>
                        <div class="mt-4 flex flex-col sm:flex-row sm:items-center gap-2">
                            <x-button label="Filtern" type="submit" class="btn-primary" />
                            <x-button label="Zurücksetzen" link="{{ route('reviews.index') }}" class="btn-ghost" />
                        </div>
                    </form>
                </div>
            </div>
            <div id="accordion">
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
                        @php
                            $missionMarsRendered = true;
                        @endphp
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

    <script>
        function toggleAccordion(id) {
            const content = document.getElementById('content-' + id);
            const icon = document.getElementById('icon-' + id);
            const button = document.querySelector('[data-accordion-button="' + id + '"]');
            content.classList.toggle('hidden');
            const expanded = !content.classList.contains('hidden');
            icon.textContent = expanded ? '-' : '+';
            if (button) {
                button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            }
        }
    </script>
    </x-member-page>
</x-app-layout>
