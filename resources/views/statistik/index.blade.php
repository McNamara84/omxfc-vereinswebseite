{{-- resources/views/statistik/index.blade.php --}}
<x-member-layout>
    <x-member-page class="space-y-6">

        @php
            $statisticSections = collect($statisticSections ?? []);
            $statisticSectionLookup = $statisticSections->keyBy('id');
            $unlockedStatisticsCount = $statisticSections
                ->filter(fn (array $section) => in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                ->count();
        @endphp

        <x-ui.page-header
            title="Statistik"
            eyebrow="Maddraxiversum in Zahlen"
            description="Alle freischaltbaren Auswertungen zu Romanen, Autor:innen, Zyklen und Rezensionen an einem Ort, inklusive Schnellnavigation über alle Statistikabschnitte."
            data-testid="page-header"
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2 lg:justify-end">
                    <span class="badge badge-outline rounded-full px-3 py-3">{{ $statisticSections->count() }} Statistiken</span>
                    <span class="badge badge-primary badge-outline rounded-full px-3 py-3">{{ $unlockedStatisticsCount }} freigeschaltet</span>
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
            <div class="w-full lg:w-72 lg:flex-shrink-0">
                @include('statistik.partials.quicknav', ['sections' => $statisticSections])
            </div>

            <div class="flex-1 space-y-6" data-statistik-sections-wrapper>
                {{-- Card 1 – Balkendiagramm (≥ 2 Baxx) --}}
                @php($section = $statisticSectionLookup->get('author-chart'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="authorChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Maddrax-Romane je Autor:in
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="authorChart" data-statistik-chart="author" data-labels='@json($authorCounts->keys())' data-values='@json($authorCounts->values())' height="140" role="img" aria-labelledby="authorChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>


                {{-- Card 2 – Teamplayer-Tabelle (≥ 4 Baxx) --}}
                @php($section = $statisticSectionLookup->get('teamplayer'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 class="text-xl font-semibold text-primary mb-4 text-center">
                        Top Teamplayer
                    </h2>
                    @if($sectionUnlocked)

                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Rang</th>
                                    <th>Autor:in</th>
                                    <th>Gemeinsame Romane</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($teamplayerTable as $i => $row)
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>{{ $row['author'] }}</td>
                                        <td>{{ $row['count'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>

                {{-- Card 3 – Top 10 Maddrax-Romane (≥ 5 Baxx) --}}
                @php($section = $statisticSectionLookup->get('top-romane'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 class="text-xl font-semibold text-primary mb-4 text-center">
                        Top 10 Maddrax-Romane
                    </h2>
                    @if($sectionUnlocked)

                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nr.</th>
                                    <th>Titel</th>
                                    <th>Autor:in</th>
                                    <th>Ø&nbsp;Bewertung</th>
                                    <th>Stimmen</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($romaneTable as $row)
                                    <tr>
                                        <td>{{ $row['nummer'] }}</td>
                                        <td>{{ $row['titel'] }}</td>
                                        <td>{{ $row['autor'] }}</td>
                                        <td>{{ number_format($row['bewertung'], 2, ',', '.') }}</td>
                                        <td>{{ $row['stimmen'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>
                {{-- Card 4 – Top-Autor:innen nach Ø‑Bewertung (≥ 7 Baxx) --}}
                @php($section = $statisticSectionLookup->get('top-autoren'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 class="text-xl font-semibold text-primary mb-4 text-center">
                        Top 10 Autor:innen nach Ø-Bewertung
                    </h2>
                    @if($sectionUnlocked)

                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Rang</th>
                                    <th>Autor:in</th>
                                    <th>Ø&nbsp;Bewertung</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($topAuthorRatings as $i => $row)
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>{{ $row['author'] }}</td>
                                        <td>{{ number_format($row['average'], 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>

                {{-- Card 5 – Top-Charaktere nach Auftritten (≥ 10 Baxx) --}}
                @php($section = $statisticSectionLookup->get('top-charaktere'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 class="text-xl font-semibold text-primary mb-4 text-center">
                        Top 10 Charaktere nach Auftritten
                    </h2>
                    @if($sectionUnlocked)

                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Rang</th>
                                    <th>Charakter</th>
                                    <th>Auftritte</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($topCharacters as $i => $row)
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>{{ $row['name'] }}</td>
                                        <td>{{ $row['count'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>

                {{-- Card 6 – Bewertungen im Maddraxikon (≥ 11 Baxx) --}}
                @php($section = $statisticSectionLookup->get('maddraxikon-bewertungen'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen im Maddraxikon
                    </h2>
                    @if($sectionUnlocked)
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <x-stat title="Ø-Bewertung" :value="number_format($averageRating, 2, ',', '.')" icon="o-star" />
                        <x-stat title="Stimmen insgesamt" :value="$totalVotes" icon="o-hand-thumb-up" />
                        <x-stat title="Ø-Stimmen pro Roman" :value="number_format($averageVotes, 2, ',', '.')" icon="o-chart-bar" />
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>
            {{-- Card 7 – Rezensionen unserer Mitglieder (≥ 12 Baxx) --}}
                @php($section = $statisticSectionLookup->get('mitglieds-rezensionen'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 class="text-xl font-semibold text-primary mb-4 text-center">
                        Rezensionen unserer Mitglieder
                    </h2>
                    @if($sectionUnlocked)

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
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>
            {{-- Card 8 – Bewertungen des Euree-Zyklus (≥ 13 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-euree'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="eureeChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Euree-Zyklus
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="eureeChart" data-statistik-chart="cycle" data-labels='@json($eureeLabels)' data-values='@json($eureeValues)' height="140" role="img" aria-labelledby="eureeChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 9 – Bewertungen des Meeraka-Zyklus (≥ 14 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-meeraka'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="meerakaChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Meeraka-Zyklus
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="meerakaChart" data-statistik-chart="cycle" data-labels='@json($meerakaLabels)' data-values='@json($meerakaValues)' height="140" role="img" aria-labelledby="meerakaChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 10 – Bewertungen des Expeditions-Zyklus (≥ 15 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-expedition'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="expeditionChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Expeditions-Zyklus
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="expeditionChart" data-statistik-chart="cycle" data-labels='@json($expeditionLabels)' data-values='@json($expeditionValues)' height="140" role="img" aria-labelledby="expeditionChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 11 – Bewertungen des Kratersee-Zyklus (≥ 16 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-kratersee'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="kraterseeChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Kratersee-Zyklus
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="kraterseeChart" data-statistik-chart="cycle" data-labels='@json($kraterseeLabels)' data-values='@json($kraterseeValues)' height="140" role="img" aria-labelledby="kraterseeChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 12 – Bewertungen des Daa'muren-Zyklus (≥ 17 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-daamuren'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="daaMurenChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Daa'muren-Zyklus
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="daaMurenChart" data-statistik-chart="cycle" data-labels='@json($daaMurenLabels)' data-values='@json($daaMurenValues)' height="140" role="img" aria-labelledby="daaMurenChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 13 – Bewertungen des Wandler-Zyklus (≥ 18 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-wandler'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="wandlerChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Wandler-Zyklus
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="wandlerChart" data-statistik-chart="cycle" data-labels='@json($wandlerLabels)' data-values='@json($wandlerValues)' height="140" role="img" aria-labelledby="wandlerChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 14 – Bewertungen des Mars-Zyklus (≥ 19 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-mars'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="marsChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Mars-Zyklus
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="marsChart" data-statistik-chart="cycle" data-labels='@json($marsLabels)' data-values='@json($marsValues)' height="140" role="img" aria-labelledby="marsChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 15 – Bewertungen des Ausala-Zyklus (≥ 20 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-ausala'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="ausalaChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Ausala-Zyklus
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="ausalaChart" data-statistik-chart="cycle" data-labels='@json($ausalaLabels)' data-values='@json($ausalaValues)' height="140" role="img" aria-labelledby="ausalaChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 16 – Bewertungen des Afra-Zyklus (≥ 21 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-afra'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="afraChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Afra-Zyklus
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="afraChart" data-statistik-chart="cycle" data-labels='@json($afraLabels)' data-values='@json($afraValues)' height="140" role="img" aria-labelledby="afraChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 17 – Bewertungen des Antarktis-Zyklus (≥ 22 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-antarktis'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="antarktisChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Antarktis-Zyklus
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="antarktisChart" data-statistik-chart="cycle" data-labels='@json($antarktisLabels)' data-values='@json($antarktisValues)' height="140" role="img" aria-labelledby="antarktisChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 18 – Bewertungen des Schatten-Zyklus (≥ 23 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-schatten'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="schattenChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Schatten-Zyklus
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="schattenChart" data-statistik-chart="cycle" data-labels='@json($schattenLabels)' data-values='@json($schattenValues)' height="140" role="img" aria-labelledby="schattenChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 19 – Bewertungen des Ursprung-Zyklus (≥ 24 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-ursprung'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="ursprungChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Ursprung-Zyklus
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="ursprungChart" data-statistik-chart="cycle" data-labels='@json($ursprungLabels)' data-values='@json($ursprungValues)' height="140" role="img" aria-labelledby="ursprungChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 20 – Bewertungen des Streiter-Zyklus (≥ 25 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-streiter'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="streiterChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Streiter-Zyklus
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="streiterChart" data-statistik-chart="cycle" data-labels='@json($streiterLabels)' data-values='@json($streiterValues)' height="140" role="img" aria-labelledby="streiterChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 21 – Bewertungen des Archivar-Zyklus (≥ 26 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-archivar'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="archivarChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Archivar-Zyklus
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="archivarChart" data-statistik-chart="cycle" data-labels='@json($archivarLabels)' data-values='@json($archivarValues)' height="140" role="img" aria-labelledby="archivarChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 22 – Bewertungen des Zeitsprung-Zyklus (≥ 27 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-zeitsprung'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="zeitsprungChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Zeitsprung-Zyklus
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="zeitsprungChart" data-statistik-chart="cycle" data-labels='@json($zeitsprungLabels)' data-values='@json($zeitsprungValues)' height="140" role="img" aria-labelledby="zeitsprungChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 23 – Bewertungen des Fremdwelt-Zyklus (≥ 28 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-fremdwelt'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="fremdweltChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Fremdwelt-Zyklus
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="fremdweltChart" data-statistik-chart="cycle" data-labels='@json($fremdweltLabels)' data-values='@json($fremdweltValues)' height="140" role="img" aria-labelledby="fremdweltChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 24 – Bewertungen des Parallelwelt-Zyklus (≥ 29 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-parallelwelt'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="parallelweltChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Parallelwelt-Zyklus
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="parallelweltChart" data-statistik-chart="cycle" data-labels='@json($parallelweltLabels)' data-values='@json($parallelweltValues)' height="140" role="img" aria-labelledby="parallelweltChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 25 – Bewertungen des Weltenriss-Zyklus (≥ 30 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-weltenriss'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="weltenrissChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Weltenriss-Zyklus
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="weltenrissChart" data-statistik-chart="cycle" data-labels='@json($weltenrissLabels)' data-values='@json($weltenrissValues)' height="140" role="img" aria-labelledby="weltenrissChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 26 – Bewertungen des Amraka-Zyklus (≥ 31 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-amraka'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="amrakaChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Amraka-Zyklus
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="amrakaChart" data-statistik-chart="cycle" data-labels='@json($amrakaLabels)' data-values='@json($amrakaValues)' height="140" role="img" aria-labelledby="amrakaChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 27 – Bewertungen des Weltrat-Zyklus (≥ 32 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zyklus-weltrat'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="weltratChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen des Weltrat-Zyklus
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="weltratChart" data-statistik-chart="cycle" data-labels='@json($weltratLabels)' data-values='@json($weltratValues)' height="140" role="img" aria-labelledby="weltratChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 28 – Bewertungen der Hardcover (≥ 40 Baxx) --}}
                @php($section = $statisticSectionLookup->get('hardcover-bewertungen'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="hardcoverChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen der Hardcover
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="hardcoverChart" data-statistik-chart="cycle" data-labels='@json($hardcoverLabels)' data-values='@json($hardcoverValues)' height="140" role="img" aria-labelledby="hardcoverChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 29 – Hardcover je Autor:in (≥ 41 Baxx) --}}
                @php($section = $statisticSectionLookup->get('hardcover-autoren'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="hardcoverAuthorChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Maddrax-Hardcover je Autor:in
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="hardcoverAuthorChart" data-statistik-chart="author" data-labels='@json($hardcoverAuthorCounts->keys())' data-values='@json($hardcoverAuthorCounts->values())' height="140" role="img" aria-labelledby="hardcoverAuthorChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>


            {{-- Card 30 – TOP20 Maddrax-Themen (≥ 42 Baxx, nur Romane mit ≥ 8 Bewertungen, Themen in ≥ 5 Romanen) --}}
                @php($section = $statisticSectionLookup->get('top-themen'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 class="text-xl font-semibold text-primary mb-4 text-center">
                        TOP20 Maddrax-Themen
                    </h2>
                    @if($sectionUnlocked)

                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Rang</th>
                                    <th>Schlagwort</th>
                                    <th>Ø&nbsp;Bewertung</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($topThemes as $i => $row)
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>{{ $row['keyword'] }}</td>
                                        <td>{{ number_format($row['average'], 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @include('statistik.lock-message', ['sectionId' => $section['id']])
            </x-ui.panel>

            {{-- Card 31 – Bewertungen der Mission Mars-Heftromane (≥ 43 Baxx) --}}
                @php($section = $statisticSectionLookup->get('mission-mars-bewertungen'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="missionMarsChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen der Mission Mars-Heftromane
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="missionMarsChart" data-statistik-chart="cycle" data-labels='@json($missionMarsLabels)' data-values='@json($missionMarsValues)' height="140" role="img" aria-labelledby="missionMarsChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 31b – Mission Mars-Heftromane je Autor:in (≥ 44 Baxx) --}}
                @php($section = $statisticSectionLookup->get('mission-mars-autoren'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="missionMarsAuthorChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Mission Mars-Heftromane je Autor:in
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="missionMarsAuthorChart" data-statistik-chart="author" data-labels='@json($missionMarsAuthorCounts->keys())' data-values='@json($missionMarsAuthorCounts->values())' height="140" role="img" aria-labelledby="missionMarsAuthorChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 32 – Bewertungen der Das Volk der Tiefe-Heftromane (≥ 45 Baxx) --}}
                @php($section = $statisticSectionLookup->get('volk-der-tiefe-bewertungen'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="volkDerTiefeChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen der Das Volk der Tiefe-Heftromane
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="volkDerTiefeChart" data-statistik-chart="cycle" data-labels='@json($volkDerTiefeLabels)' data-values='@json($volkDerTiefeValues)' height="140" role="img" aria-labelledby="volkDerTiefeChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 32b – Das Volk der Tiefe-Heftromane je Autor:in (≥ 46 Baxx) --}}
                @php($section = $statisticSectionLookup->get('volk-der-tiefe-autoren'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="volkDerTiefeAuthorChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Das Volk der Tiefe-Heftromane je Autor:in
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="volkDerTiefeAuthorChart" data-statistik-chart="author" data-labels='@json($volkDerTiefeAuthorCounts->keys())' data-values='@json($volkDerTiefeAuthorCounts->values())' height="140" role="img" aria-labelledby="volkDerTiefeAuthorChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 33 – Bewertungen der 2012-Heftromane (≥ 47 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zweitausendzwoelf-bewertungen'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="zweitausendzwoelfChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen der 2012-Heftromane
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="zweitausendzwoelfChart" data-statistik-chart="cycle" data-labels='@json($zweitausendzwoelfLabels)' data-values='@json($zweitausendzwoelfValues)' height="140" role="img" aria-labelledby="zweitausendzwoelfChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 33b – 2012-Heftromane je Autor:in (≥ 48 Baxx) --}}
                @php($section = $statisticSectionLookup->get('zweitausendzwoelf-autoren'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="zweitausendzwoelfAuthorChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        2012-Heftromane je Autor:in
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="zweitausendzwoelfAuthorChart" data-statistik-chart="author" data-labels='@json($zweitausendzwoelfAuthorCounts->keys())' data-values='@json($zweitausendzwoelfAuthorCounts->values())' height="140" role="img" aria-labelledby="zweitausendzwoelfAuthorChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 34 – Bewertungen der Die Abenteurer-Heftromane (≥ 33 Baxx) --}}
                @php($section = $statisticSectionLookup->get('abenteurer-bewertungen'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="abenteurerChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Bewertungen der Die Abenteurer-Heftromane
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="abenteurerChart" data-statistik-chart="cycle" data-labels='@json($abenteurerLabels)' data-values='@json($abenteurerValues)' height="140" role="img" aria-labelledby="abenteurerChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 34b – Die Abenteurer-Heftromane je Autor:in (≥ 34 Baxx) --}}
                @php($section = $statisticSectionLookup->get('abenteurer-autoren'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 id="abenteurerAuthorChartTitle" class="text-xl font-semibold text-primary mb-4 text-center">
                        Die Abenteurer-Heftromane je Autor:in
                    </h2>
                    @if($sectionUnlocked)
                    <div data-chart-wrapper class="mt-4">
                        <canvas id="abenteurerAuthorChart" data-statistik-chart="author" data-labels='@json($abenteurerAuthorCounts->keys())' data-values='@json($abenteurerAuthorCounts->values())' height="140" role="img" aria-labelledby="abenteurerAuthorChartTitle"></canvas>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>



            {{-- Card 35 – TOP10 Lieblingsthemen (≥ 50 Baxx) --}}
                @php($section = $statisticSectionLookup->get('lieblingsthemen'))
                @php($sectionUnlocked = in_array('statistik-' . $section['id'], $unlockedSlugs, true))
                <x-ui.panel
                    id="{{ $section['id'] }}"
                    data-statistik-section
                    tabindex="-1"
                    class="relative">
                    <h2 class="text-xl font-semibold text-primary mb-4 text-center">
                        TOP10 Lieblingsthemen
                    </h2>
                    @if($sectionUnlocked)

                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Rang</th>
                                    <th>Thema</th>
                                    <th>Anzahl</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($topFavoriteThemes as $i => $row)
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>{{ $row['thema'] }}</td>
                                        <td>{{ $row['count'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                    @include('statistik.lock-message', ['sectionId' => $section['id']])
                </x-ui.panel>
            </div>
        </div>

    </x-member-page>
</x-member-layout>
