{{-- resources/views/statistik/index.blade.php --}}
<x-app-layout>
    <x-member-page>
        {{-- Kopfzeile --}}
        <x-header title="Statistik" separator data-testid="page-header" />

        @php
            $statisticSections = collect($statisticSections ?? []);
            $statisticSectionLookup = $statisticSections->keyBy('id');
        @endphp

        <div class="flex flex-col lg:flex-row lg:items-start gap-6">
            <div class="w-full lg:w-72 lg:flex-shrink-0">
                @include('statistik.partials.quicknav', ['sections' => $statisticSections])
            </div>

            <div class="flex-1 space-y-6" data-statistik-sections-wrapper>
                {{-- Card 1 – Balkendiagramm (≥ 2 Bakk) --}}
                @php($section = $statisticSectionLookup->get('author-chart'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="authorChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Maddrax-Romane je Autor:in
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="authorChart" height="140" role="img" aria-labelledby="authorChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                {{-- Chart-Daten global für JS-Modul --}}
                <script>
                    window.userPoints = {{ $userPoints }};
                    window.authorChartLabels = @json($authorCounts->keys());
                    window.authorChartValues = @json($authorCounts->values());
                </script>

                {{-- Card 2 – Teamplayer-Tabelle (≥ 4 Baxx) --}}
                @php($section = $statisticSectionLookup->get('teamplayer'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 class="text-xl font-semibold text-primary mb-4 text-center">
                        Top Teamplayer
                    </h2>

                    <div class="overflow-x-auto">
                        @php
                            $teamplayerHeaders = [
                                ['key' => 'rang', 'label' => 'Rang'],
                                ['key' => 'author', 'label' => 'Autor:in'],
                                ['key' => 'count', 'label' => 'Gemeinsame Romane'],
                            ];
                            $teamplayerRows = collect($teamplayerTable)->map(fn($row, $i) => [
                                'rang' => $i + 1,
                                'author' => $row['author'],
                                'count' => $row['count'],
                            ]);
                        @endphp
                        <x-table :headers="$teamplayerHeaders" :rows="$teamplayerRows" />
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                {{-- Card 3 – Top 10 Maddrax-Romane (≥ 5 Baxx) --}}
                @php($section = $statisticSectionLookup->get('top-romane'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 class="text-xl font-semibold text-primary mb-4 text-center">
                        Top 10 Maddrax-Romane
                    </h2>

                    <div class="overflow-x-auto">
                        @php
                            $romaneHeaders = [
                                ['key' => 'nummer', 'label' => 'Nr.'],
                                ['key' => 'titel', 'label' => 'Titel'],
                                ['key' => 'autor', 'label' => 'Autor:in'],
                                ['key' => 'bewertung', 'label' => 'Ø\xc2\xa0Bewertung'],
                                ['key' => 'stimmen', 'label' => 'Stimmen'],
                            ];
                            $romaneRows = collect($romaneTable)->map(fn($row) => [
                                'nummer' => $row['nummer'],
                                'titel' => $row['titel'],
                                'autor' => $row['autor'],
                                'bewertung' => number_format($row['bewertung'], 2, ',', '.'),
                                'stimmen' => $row['stimmen'],
                            ]);
                        @endphp
                        <x-table :headers="$romaneHeaders" :rows="$romaneRows" />
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>
                {{-- Card 4 – Top-Autor:innen nach Ø‑Bewertung (≥ 7 Baxx) --}}
                @php($section = $statisticSectionLookup->get('top-autoren'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 class="text-xl font-semibold text-primary mb-4 text-center">
                        Top 10 Autor:innen nach Ø-Bewertung
                    </h2>

                    <div class="overflow-x-auto">
                        @php
                            $authorRatingHeaders = [
                                ['key' => 'rang', 'label' => 'Rang'],
                                ['key' => 'author', 'label' => 'Autor:in'],
                                ['key' => 'average', 'label' => 'Ø\xc2\xa0Bewertung'],
                            ];
                            $authorRatingRows = collect($topAuthorRatings)->map(fn($row, $i) => [
                                'rang' => $i + 1,
                                'author' => $row['author'],
                                'average' => number_format($row['average'], 2, ',', '.'),
                            ]);
                        @endphp
                        <x-table :headers="$authorRatingHeaders" :rows="$authorRatingRows" />
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                {{-- Card 5 – Top-Charaktere nach Auftritten (≥ 10 Baxx) --}}
                @php($section = $statisticSectionLookup->get('top-charaktere'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 class="text-xl font-semibold text-primary mb-4 text-center">
                        Top 10 Charaktere nach Auftritten
                    </h2>

                    <div class="overflow-x-auto">
                        @php
                            $characterHeaders = [
                                ['key' => 'rang', 'label' => 'Rang'],
                                ['key' => 'name', 'label' => 'Charakter'],
                                ['key' => 'count', 'label' => 'Auftritte'],
                            ];
                            $characterRows = collect($topCharacters)->map(fn($row, $i) => [
                                'rang' => $i + 1,
                                'name' => $row['name'],
                                'count' => $row['count'],
                            ]);
                        @endphp
                        <x-table :headers="$characterHeaders" :rows="$characterRows" />
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                {{-- Card 6 – Bewertungen im Maddraxikon (≥ 11 Baxx) --}}
                @php($section = $statisticSectionLookup->get('maddraxikon-bewertungen'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 class="text-xl font-semibold text-primary mb-4 text-center col-span-1 md:col-span-3">
                        Bewertungen im Maddraxikon
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <x-stat title="Ø-Bewertung" :value="number_format($averageRating, 2, ',', '.')" icon="o-star" />
                        <x-stat title="Stimmen insgesamt" :value="$totalVotes" icon="o-hand-thumb-up" />
                        <x-stat title="Ø-Stimmen pro Roman" :value="number_format($averageVotes, 2, ',', '.')" icon="o-chart-bar" />
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>
            {{-- Card 7 – Rezensionen unserer Mitglieder (≥ 12 Baxx) --}}
                @php($section = $statisticSectionLookup->get('mitglieds-rezensionen'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 class="text-xl font-semibold text-primary mb-4 text-center">
                        Rezensionen unserer Mitglieder
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <x-stat title="Rezensionen insgesamt" :value="$totalReviews" icon="o-document-text" />
                        <x-stat title="Ø Rezensionen pro Roman" :value="number_format($averageReviewsPerBook, 2, ',', '.')" icon="o-book-open" />
                        <x-stat title="Ø Kommentare pro Rezension" :value="number_format($avgCommentsPerReview, 2, ',', '.')" icon="o-chat-bubble-left-right" />
                    </div>

                    <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <h3 class="font-semibold mb-2">Meistkommentierte Rezensionen</h3>
                            <ul class="list-disc ml-5">
                                @foreach ($topCommentedReviews as $row)
                                    <li>{{ $row['title'] }} ({{ $row['comments'] }})</li>
                                @endforeach
                            </ul>
                        </div>

                        @if ($longestReviewAuthor)
                            <div>
                                <h3 class="font-semibold mb-2">Längste Rezensionen im Durchschnitt</h3>
                                <p>{{ $longestReviewAuthor['name'] }} ({{ $longestReviewAuthor['length'] }} Zeichen)</p>
                            </div>
                        @endif

                        <div>
                            <h3 class="font-semibold mb-2">Top Rezensent:innen</h3>
                            <ul class="list-disc ml-5">
                                @foreach ($topReviewers as $row)
                                    <li>{{ $row['name'] }} ({{ $row['count'] }})</li>
                                @endforeach
                            </ul>
                        </div>

                        @if ($mostReviewedBook)
                            <div>
                                <h3 class="font-semibold mb-2">Roman mit den meisten Rezensionen</h3>
                                <p>{{ $mostReviewedBook['title'] }} ({{ $mostReviewedBook['count'] }})</p>
                            </div>
                        @endif
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>
            {{-- Card 8 – Bewertungen des Euree-Zyklus (≥ 13 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-euree'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="eureeChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Euree-Zyklus
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="eureeChart" height="140" role="img" aria-labelledby="eureeChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.eureeChartLabels = @json($eureeLabels);
                    window.eureeChartValues = @json($eureeValues);
                </script>

            {{-- Card 9 – Bewertungen des Meeraka-Zyklus (≥ 14 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-meeraka'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="meerakaChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Meeraka-Zyklus
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="meerakaChart" height="140" role="img" aria-labelledby="meerakaChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.meerakaChartLabels = @json($meerakaLabels);
                    window.meerakaChartValues = @json($meerakaValues);
                </script>

            {{-- Card 10 – Bewertungen des Expeditions-Zyklus (≥ 15 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-expedition'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="expeditionChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Expeditions-Zyklus
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="expeditionChart" height="140" role="img" aria-labelledby="expeditionChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.expeditionChartLabels = @json($expeditionLabels);
                    window.expeditionChartValues = @json($expeditionValues);
                </script>

            {{-- Card 11 – Bewertungen des Kratersee-Zyklus (≥ 16 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-kratersee'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="kraterseeChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Kratersee-Zyklus
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="kraterseeChart" height="140" role="img" aria-labelledby="kraterseeChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.kraterseeChartLabels = @json($kraterseeLabels);
                    window.kraterseeChartValues = @json($kraterseeValues);
                </script>

            {{-- Card 12 – Bewertungen des Daa'muren-Zyklus (≥ 17 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-daamuren'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="daaMurenChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Daa'muren-Zyklus
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="daaMurenChart" height="140" role="img" aria-labelledby="daaMurenChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.daaMurenChartLabels = @json($daaMurenLabels);
                    window.daaMurenChartValues = @json($daaMurenValues);
                </script>

            {{-- Card 13 – Bewertungen des Wandler-Zyklus (≥ 18 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-wandler'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="wandlerChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Wandler-Zyklus
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="wandlerChart" height="140" role="img" aria-labelledby="wandlerChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.wandlerChartLabels = @json($wandlerLabels);
                    window.wandlerChartValues = @json($wandlerValues);
                </script>

            {{-- Card 14 – Bewertungen des Mars-Zyklus (≥ 19 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-mars'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="marsChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Mars-Zyklus
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="marsChart" height="140" role="img" aria-labelledby="marsChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.marsChartLabels = @json($marsLabels);
                    window.marsChartValues = @json($marsValues);
                </script>

            {{-- Card 15 – Bewertungen des Ausala-Zyklus (≥ 20 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-ausala'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="ausalaChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Ausala-Zyklus
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="ausalaChart" height="140" role="img" aria-labelledby="ausalaChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.ausalaChartLabels = @json($ausalaLabels);
                    window.ausalaChartValues = @json($ausalaValues);
                </script>

            {{-- Card 16 – Bewertungen des Afra-Zyklus (≥ 21 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-afra'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="afraChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Afra-Zyklus
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="afraChart" height="140" role="img" aria-labelledby="afraChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.afraChartLabels = @json($afraLabels);
                    window.afraChartValues = @json($afraValues);
                </script>

            {{-- Card 17 – Bewertungen des Antarktis-Zyklus (≥ 22 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-antarktis'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="antarktisChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Antarktis-Zyklus
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="antarktisChart" height="140" role="img" aria-labelledby="antarktisChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.antarktisChartLabels = @json($antarktisLabels);
                    window.antarktisChartValues = @json($antarktisValues);
                </script>

            {{-- Card 18 – Bewertungen des Schatten-Zyklus (≥ 23 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-schatten'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="schattenChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Schatten-Zyklus
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="schattenChart" height="140" role="img" aria-labelledby="schattenChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.schattenChartLabels = @json($schattenLabels);
                    window.schattenChartValues = @json($schattenValues);
                </script>

            {{-- Card 19 – Bewertungen des Ursprung-Zyklus (≥ 24 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-ursprung'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="ursprungChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Ursprung-Zyklus
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="ursprungChart" height="140" role="img" aria-labelledby="ursprungChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.ursprungChartLabels = @json($ursprungLabels);
                    window.ursprungChartValues = @json($ursprungValues);
                </script>

            {{-- Card 20 – Bewertungen des Streiter-Zyklus (≥ 25 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-streiter'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="streiterChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Streiter-Zyklus
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="streiterChart" height="140" role="img" aria-labelledby="streiterChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.streiterChartLabels = @json($streiterLabels);
                    window.streiterChartValues = @json($streiterValues);
                </script>

            {{-- Card 21 – Bewertungen des Archivar-Zyklus (≥ 26 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-archivar'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="archivarChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Archivar-Zyklus
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="archivarChart" height="140" role="img" aria-labelledby="archivarChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.archivarChartLabels = @json($archivarLabels);
                    window.archivarChartValues = @json($archivarValues);
                </script>

            {{-- Card 22 – Bewertungen des Zeitsprung-Zyklus (≥ 27 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-zeitsprung'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="zeitsprungChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Zeitsprung-Zyklus
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="zeitsprungChart" height="140" role="img" aria-labelledby="zeitsprungChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.zeitsprungChartLabels = @json($zeitsprungLabels);
                    window.zeitsprungChartValues = @json($zeitsprungValues);
                </script>

            {{-- Card 23 – Bewertungen des Fremdwelt-Zyklus (≥ 28 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-fremdwelt'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="fremdweltChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Fremdwelt-Zyklus
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="fremdweltChart" height="140" role="img" aria-labelledby="fremdweltChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.fremdweltChartLabels = @json($fremdweltLabels);
                    window.fremdweltChartValues = @json($fremdweltValues);
                </script>

            {{-- Card 24 – Bewertungen des Parallelwelt-Zyklus (≥ 29 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-parallelwelt'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="parallelweltChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Parallelwelt-Zyklus
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="parallelweltChart" height="140" role="img" aria-labelledby="parallelweltChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.parallelweltChartLabels = @json($parallelweltLabels);
                    window.parallelweltChartValues = @json($parallelweltValues);
                </script>

            {{-- Card 25 – Bewertungen des Weltenriss-Zyklus (≥ 30 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-weltenriss'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="weltenrissChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Weltenriss-Zyklus
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="weltenrissChart" height="140" role="img" aria-labelledby="weltenrissChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.weltenrissChartLabels = @json($weltenrissLabels);
                    window.weltenrissChartValues = @json($weltenrissValues);
                </script>

            {{-- Card 26 – Bewertungen des Amraka-Zyklus (≥ 31 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-amraka'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="amrakaChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Amraka-Zyklus
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="amrakaChart" height="140" role="img" aria-labelledby="amrakaChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.amrakaChartLabels = @json($amrakaLabels);
                    window.amrakaChartValues = @json($amrakaValues);
                </script>

            {{-- Card 27 – Bewertungen des Weltrat-Zyklus (≥ 32 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-weltrat'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="weltratChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Weltrat-Zyklus
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="weltratChart" height="140" role="img" aria-labelledby="weltratChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.weltratChartLabels = @json($weltratLabels);
                    window.weltratChartValues = @json($weltratValues);
                </script>

            {{-- Card 28 – Bewertungen der Hardcover (≥ 40 Baxx) --}}
                @php($section = $statisticSectionLookup->get('hardcover-bewertungen'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="hardcoverChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen der Hardcover
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="hardcoverChart" height="140" role="img" aria-labelledby="hardcoverChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.hardcoverChartLabels = @json($hardcoverLabels);
                    window.hardcoverChartValues = @json($hardcoverValues);
                </script>

            {{-- Card 29 – Hardcover je Autor:in (≥ 41 Baxx) --}}
                @php($section = $statisticSectionLookup->get('hardcover-autoren'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="hardcoverAuthorChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Maddrax-Hardcover je Autor:in
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="hardcoverAuthorChart" height="140" role="img" aria-labelledby="hardcoverAuthorChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.hardcoverAuthorChartLabels = @json($hardcoverAuthorCounts->keys());
                    window.hardcoverAuthorChartValues = @json($hardcoverAuthorCounts->values());
                </script>
            {{-- Card 30 – TOP20 Maddrax-Themen (≥ 42 Baxx, nur Romane mit ≥ 8 Bewertungen, Themen in ≥ 5 Romanen) --}}
                @php($section = $statisticSectionLookup->get('top-themen'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 class="text-xl font-semibold text-primary mb-4 text-center">
                        TOP20 Maddrax-Themen
                    </h2>

                    <div class="overflow-x-auto">
                        @php
                            $themeHeaders = [
                                ['key' => 'rang', 'label' => 'Rang'],
                                ['key' => 'keyword', 'label' => 'Schlagwort'],
                                ['key' => 'average', 'label' => 'Ø\xc2\xa0Bewertung'],
                            ];
                            $themeRows = collect($topThemes)->map(fn($row, $i) => [
                                'rang' => $i + 1,
                                'keyword' => $row['keyword'],
                                'average' => number_format($row['average'], 2, ',', '.'),
                            ]);
                        @endphp
                        <x-table :headers="$themeHeaders" :rows="$themeRows" />
                    </div>
                @if($userPoints < $min)
                    @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                @endif
            </x-card>

            {{-- Card 31 – Bewertungen der Mission Mars-Heftromane (≥ 43 Baxx) --}}
                @php($section = $statisticSectionLookup->get('mission-mars-bewertungen'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="missionMarsChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen der Mission Mars-Heftromane
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="missionMarsChart" height="140" role="img" aria-labelledby="missionMarsChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.missionMarsChartLabels = @json($missionMarsLabels);
                    window.missionMarsChartValues = @json($missionMarsValues);
                </script>

            {{-- Card 31b – Mission Mars-Heftromane je Autor:in (≥ 44 Baxx) --}}
                @php($section = $statisticSectionLookup->get('mission-mars-autoren'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="missionMarsAuthorChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Mission Mars-Heftromane je Autor:in
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="missionMarsAuthorChart" height="140" role="img" aria-labelledby="missionMarsAuthorChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.missionMarsAuthorChartLabels = @json($missionMarsAuthorCounts->keys());
                    window.missionMarsAuthorChartValues = @json($missionMarsAuthorCounts->values());
                </script>

            {{-- Card 32 – Bewertungen der Das Volk der Tiefe-Heftromane (≥ 45 Baxx) --}}
                @php($section = $statisticSectionLookup->get('volk-der-tiefe-bewertungen'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="volkDerTiefeChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen der Das Volk der Tiefe-Heftromane
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="volkDerTiefeChart" height="140" role="img" aria-labelledby="volkDerTiefeChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.volkDerTiefeChartLabels = @json($volkDerTiefeLabels);
                    window.volkDerTiefeChartValues = @json($volkDerTiefeValues);
                </script>

            {{-- Card 32b – Das Volk der Tiefe-Heftromane je Autor:in (≥ 46 Baxx) --}}
                @php($section = $statisticSectionLookup->get('volk-der-tiefe-autoren'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="volkDerTiefeAuthorChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Das Volk der Tiefe-Heftromane je Autor:in
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="volkDerTiefeAuthorChart" height="140" role="img" aria-labelledby="volkDerTiefeAuthorChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.volkDerTiefeAuthorChartLabels = @json($volkDerTiefeAuthorCounts->keys());
                    window.volkDerTiefeAuthorChartValues = @json($volkDerTiefeAuthorCounts->values());
                </script>

            {{-- Card 33 – Bewertungen der 2012-Heftromane (≥ 47 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zweitausendzwoelf-bewertungen'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="zweitausendzwoelfChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen der 2012-Heftromane
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="zweitausendzwoelfChart" height="140" role="img" aria-labelledby="zweitausendzwoelfChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.zweitausendzwoelfChartLabels = @json($zweitausendzwoelfLabels);
                    window.zweitausendzwoelfChartValues = @json($zweitausendzwoelfValues);
                </script>

            {{-- Card 33b – 2012-Heftromane je Autor:in (≥ 48 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zweitausendzwoelf-autoren'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="zweitausendzwoelfAuthorChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        2012-Heftromane je Autor:in
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="zweitausendzwoelfAuthorChart" height="140" role="img" aria-labelledby="zweitausendzwoelfAuthorChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.zweitausendzwoelfAuthorChartLabels = @json($zweitausendzwoelfAuthorCounts->keys());
                    window.zweitausendzwoelfAuthorChartValues = @json($zweitausendzwoelfAuthorCounts->values());
                </script>

            {{-- Card 34 – Bewertungen der Die Abenteurer-Heftromane (≥ 33 Baxx) --}}
                @php($section = $statisticSectionLookup->get('abenteurer-bewertungen'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="abenteurerChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen der Die Abenteurer-Heftromane
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="abenteurerChart" height="140" role="img" aria-labelledby="abenteurerChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.abenteurerChartLabels = @json($abenteurerLabels);
                    window.abenteurerChartValues = @json($abenteurerValues);
                </script>

            {{-- Card 34b – Die Abenteurer-Heftromane je Autor:in (≥ 34 Baxx) --}}
                @php($section = $statisticSectionLookup->get('abenteurer-autoren'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 id="abenteurerAuthorChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Die Abenteurer-Heftromane je Autor:in
                    </h2>
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="abenteurerAuthorChart" height="140" role="img" aria-labelledby="abenteurerAuthorChartTitle"></canvas>
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>

                <script>
                    window.abenteurerAuthorChartLabels = @json($abenteurerAuthorCounts->keys());
                    window.abenteurerAuthorChartValues = @json($abenteurerAuthorCounts->values());
                </script>

            {{-- Card 35 – TOP10 Lieblingsthemen (≥ 50 Baxx) --}}
                @php($section = $statisticSectionLookup->get('lieblingsthemen'))
                @php($min = $section['minPoints'] ?? 0)
                <x-card
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    data-min-points="{{ $min }}"
                    tabindex="-1"
                    shadow class="relative">
                    <h2 class="text-xl font-semibold text-primary mb-4 text-center">
                        TOP10 Lieblingsthemen
                    </h2>

                    <div class="overflow-x-auto">
                        @php
                            $favThemeHeaders = [
                                ['key' => 'rang', 'label' => 'Rang'],
                                ['key' => 'thema', 'label' => 'Thema'],
                                ['key' => 'count', 'label' => 'Anzahl'],
                            ];
                            $favThemeRows = collect($topFavoriteThemes)->map(fn($row, $i) => [
                                'rang' => $i + 1,
                                'thema' => $row['thema'],
                                'count' => $row['count'],
                            ]);
                        @endphp
                        <x-table :headers="$favThemeHeaders" :rows="$favThemeRows" />
                    </div>
                    @if($userPoints < $min)
                        @include('statistik.lock-message', ['min' => $min, 'userPoints' => $userPoints])
                    @endif
                </x-card>
            </div>
        </div>

        @vite(['resources/js/statistik.js'])
    </x-member-page>
</x-app-layout>
