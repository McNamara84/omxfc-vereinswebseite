{{-- resources/views/statistik/index.blade.php --}}
<x-app-layout>
    <x-member-page>
            {{-- Kopfzeile --}}
            <div
                class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-[#FF6B81]">
                    Statistik
                </h1>
            </div>
            {{-- Card 1 – Balkendiagramm (≥ 2 Bakk) --}}
            @if ($userPoints >= 2)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Maddrax-Romane je Autor:in
                    </h2>
                    <canvas id="authorChart" height="140"></canvas>
                </div>

                {{-- Chart-Daten global für JS-Modul --}}
                <script>
                    window.authorChartLabels = @json($authorCounts->keys());
                    window.authorChartValues = @json($authorCounts->values());
                </script>
            @endif
            {{-- Card 2 – Teamplayer-Tabelle (≥ 4 Baxx) --}}
            @if ($userPoints >= 4)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Top Teamplayer
                    </h2>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
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
                </div>
            @endif
            {{-- Card 3 – Top 10 Maddrax-Romane (≥ 5 Baxx) --}}
            @if ($userPoints >= 5)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Top 10 Maddrax-Romane
                    </h2>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
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
                </div>
            @endif
            {{-- Card 4 – Top-Autor:innen nach Ø‑Bewertung (≥ 7 Baxx) --}}
            @if ($userPoints >= 7)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Top 10 Autor:innen nach Ø-Bewertung
                    </h2>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
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
                </div>
            @else
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4">
                        Top 10 Autor:innen nach Ø-Bewertung
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Diese Statistik wird ab <strong>7</strong> Baxx freigeschaltet.<br>
                        Dein aktueller Stand: <span class="font-semibold">{{ $userPoints }}</span>.
                    </p>
                </div>
            @endif
            {{-- Card 5 – Top-Charaktere nach Auftritten (≥ 10 Baxx) --}}
            @if ($userPoints >= 10)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Top 10 Charaktere nach Auftritten
                    </h2>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
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
                </div>
            @else
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Top 10 Charaktere nach Auftritten
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Diese Statistik wird ab <strong>10</strong> Baxx freigeschaltet.<br>
                        Dein aktueller Stand: <span class="font-semibold">{{ $userPoints }}</span>.
                    </p>
                </div>
            @endif
            {{-- Card 6 – Bewertungen im Maddraxikon (≥ 11 Baxx) --}}
            @if ($userPoints >= 11)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6 grid grid-cols-1 md:grid-cols-3 gap-6 text-center">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 col-span-1 md:col-span-3">
                        Bewertungen im Maddraxikon
                    </h2>
                    <div>
                        <div class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                            {{ number_format($averageRating, 2, ',', '.') }}
                        </div>
                        <div class="text-gray-600 dark:text-gray-400">Ø-Bewertung</div>
                    </div>

                    <div>
                        <div class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                            {{ $totalVotes }}
                        </div>
                        <div class="text-gray-600 dark:text-gray-400">Stimmen insgesamt</div>
                    </div>

                    <div>
                        <div class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                            {{ number_format($averageVotes, 2, ',', '.') }}
                        </div>
                    <div class="text-gray-600 dark:text-gray-400">Ø-Stimmen pro Roman</div>
                    </div>
                </div>
            @endif
            {{-- Card 7 – Rezensionen unserer Mitglieder (≥ 12 Baxx) --}}
            @if ($userPoints >= 12)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Rezensionen unserer Mitglieder
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-center">
                        <div>
                            <div class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                {{ $totalReviews }}
                            </div>
                            <div class="text-gray-600 dark:text-gray-400">Rezensionen insgesamt</div>
                        </div>

                        <div>
                            <div class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                {{ number_format($averageReviewsPerBook, 2, ',', '.') }}
                            </div>
                            <div class="text-gray-600 dark:text-gray-400">Ø Rezensionen pro Roman</div>
                        </div>

                        <div>
                            <div class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                {{ number_format($avgCommentsPerReview, 2, ',', '.') }}
                            </div>
                            <div class="text-gray-600 dark:text-gray-400">Ø Kommentare pro Rezension</div>
                        </div>
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
                </div>
            @endif
            {{-- Card 8 – Bewertungen des Euree-Zyklus (≥ 13 Baxx) --}}
            @if ($userPoints >= 13)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Euree-Zyklus
                    </h2>
                    <canvas id="eureeChart" height="140"></canvas>
                </div>

                <script>
                    window.eureeChartLabels = @json($eureeLabels);
                    window.eureeChartValues = @json($eureeValues);
                </script>
            @endif

            {{-- Card 9 – Bewertungen des Meeraka-Zyklus (≥ 14 Baxx) --}}
            @if ($userPoints >= 14)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Meeraka-Zyklus
                    </h2>
                    <canvas id="meerakaChart" height="140"></canvas>
                </div>

                <script>
                    window.meerakaChartLabels = @json($meerakaLabels);
                    window.meerakaChartValues = @json($meerakaValues);
                </script>
            @endif

            {{-- Card 10 – Bewertungen des Expeditions-Zyklus (≥ 15 Baxx) --}}
            @if ($userPoints >= 15)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Expeditions-Zyklus
                    </h2>
                    <canvas id="expeditionChart" height="140"></canvas>
                </div>

                <script>
                    window.expeditionChartLabels = @json($expeditionLabels);
                    window.expeditionChartValues = @json($expeditionValues);
                </script>
            @endif

            {{-- Card 11 – Bewertungen des Kratersee-Zyklus (≥ 16 Baxx) --}}
            @if ($userPoints >= 16)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Kratersee-Zyklus
                    </h2>
                    <canvas id="kraterseeChart" height="140"></canvas>
                </div>

                <script>
                    window.kraterseeChartLabels = @json($kraterseeLabels);
                    window.kraterseeChartValues = @json($kraterseeValues);
                </script>
            @endif

            {{-- Card 12 – Bewertungen des Daa'muren-Zyklus (≥ 17 Baxx) --}}
            @if ($userPoints >= 17)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Daa'muren-Zyklus
                    </h2>
                    <canvas id="daaMurenChart" height="140"></canvas>
                </div>

                <script>
                    window.daaMurenChartLabels = @json($daaMurenLabels);
                    window.daaMurenChartValues = @json($daaMurenValues);
                </script>
            @endif

            {{-- Card 13 – Bewertungen des Wandler-Zyklus (≥ 18 Baxx) --}}
            @if ($userPoints >= 18)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Wandler-Zyklus
                    </h2>
                    <canvas id="wandlerChart" height="140"></canvas>
                </div>

                <script>
                    window.wandlerChartLabels = @json($wandlerLabels);
                    window.wandlerChartValues = @json($wandlerValues);
                </script>
            @endif

            {{-- Card 14 – Bewertungen des Mars-Zyklus (≥ 19 Baxx) --}}
            @if ($userPoints >= 19)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Mars-Zyklus
                    </h2>
                    <canvas id="marsChart" height="140"></canvas>
                </div>

                <script>
                    window.marsChartLabels = @json($marsLabels);
                    window.marsChartValues = @json($marsValues);
                </script>
            @endif

            {{-- Card 15 – Bewertungen des Ausala-Zyklus (≥ 20 Baxx) --}}
            @if ($userPoints >= 20)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Ausala-Zyklus
                    </h2>
                    <canvas id="ausalaChart" height="140"></canvas>
                </div>

                <script>
                    window.ausalaChartLabels = @json($ausalaLabels);
                    window.ausalaChartValues = @json($ausalaValues);
                </script>
            @endif

            {{-- Card 16 – Bewertungen des Afra-Zyklus (≥ 21 Baxx) --}}
            @if ($userPoints >= 21)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Afra-Zyklus
                    </h2>
                    <canvas id="afraChart" height="140"></canvas>
                </div>

                <script>
                    window.afraChartLabels = @json($afraLabels);
                    window.afraChartValues = @json($afraValues);
                </script>
            @endif

            {{-- Card 17 – Bewertungen des Antarktis-Zyklus (≥ 22 Baxx) --}}
            @if ($userPoints >= 22)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Antarktis-Zyklus
                    </h2>
                    <canvas id="antarktisChart" height="140"></canvas>
                </div>

                <script>
                    window.antarktisChartLabels = @json($antarktisLabels);
                    window.antarktisChartValues = @json($antarktisValues);
                </script>
            @endif

            {{-- Card 18 – Bewertungen des Schatten-Zyklus (≥ 23 Baxx) --}}
            @if ($userPoints >= 23)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Schatten-Zyklus
                    </h2>
                    <canvas id="schattenChart" height="140"></canvas>
                </div>

                <script>
                    window.schattenChartLabels = @json($schattenLabels);
                    window.schattenChartValues = @json($schattenValues);
                </script>
            @endif

            {{-- Card 19 – Bewertungen des Ursprung-Zyklus (≥ 24 Baxx) --}}
            @if ($userPoints >= 24)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Ursprung-Zyklus
                    </h2>
                    <canvas id="ursprungChart" height="140"></canvas>
                </div>

                <script>
                    window.ursprungChartLabels = @json($ursprungLabels);
                    window.ursprungChartValues = @json($ursprungValues);
                </script>
            @endif

            {{-- Card 20 – Bewertungen des Streiter-Zyklus (≥ 25 Baxx) --}}
            @if ($userPoints >= 25)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Streiter-Zyklus
                    </h2>
                    <canvas id="streiterChart" height="140"></canvas>
                </div>

                <script>
                    window.streiterChartLabels = @json($streiterLabels);
                    window.streiterChartValues = @json($streiterValues);
                </script>
            @endif

            {{-- Card 21 – Bewertungen des Archivar-Zyklus (≥ 26 Baxx) --}}
            @if ($userPoints >= 26)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Archivar-Zyklus
                    </h2>
                    <canvas id="archivarChart" height="140"></canvas>
                </div>

                <script>
                    window.archivarChartLabels = @json($archivarLabels);
                    window.archivarChartValues = @json($archivarValues);
                </script>
            @endif

            {{-- Card 22 – Bewertungen des Zeitsprung-Zyklus (≥ 27 Baxx) --}}
            @if ($userPoints >= 27)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Zeitsprung-Zyklus
                    </h2>
                    <canvas id="zeitsprungChart" height="140"></canvas>
                </div>

                <script>
                    window.zeitsprungChartLabels = @json($zeitsprungLabels);
                    window.zeitsprungChartValues = @json($zeitsprungValues);
                </script>
            @endif

            {{-- Card 23 – Bewertungen des Fremdwelt-Zyklus (≥ 28 Baxx) --}}
            @if ($userPoints >= 28)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Fremdwelt-Zyklus
                    </h2>
                    <canvas id="fremdweltChart" height="140"></canvas>
                </div>

                <script>
                    window.fremdweltChartLabels = @json($fremdweltLabels);
                    window.fremdweltChartValues = @json($fremdweltValues);
                </script>
            @endif

            {{-- Card 24 – Bewertungen des Parallelwelt-Zyklus (≥ 29 Baxx) --}}
            @if ($userPoints >= 29)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Parallelwelt-Zyklus
                    </h2>
                    <canvas id="parallelweltChart" height="140"></canvas>
                </div>

                <script>
                    window.parallelweltChartLabels = @json($parallelweltLabels);
                    window.parallelweltChartValues = @json($parallelweltValues);
                </script>
            @endif

            {{-- Card 25 – Bewertungen des Weltenriss-Zyklus (≥ 30 Baxx) --}}
            @if ($userPoints >= 30)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Weltenriss-Zyklus
                    </h2>
                    <canvas id="weltenrissChart" height="140"></canvas>
                </div>

                <script>
                    window.weltenrissChartLabels = @json($weltenrissLabels);
                    window.weltenrissChartValues = @json($weltenrissValues);
                </script>
            @endif

            {{-- Card 26 – Bewertungen des Amraka-Zyklus (≥ 31 Baxx) --}}
            @if ($userPoints >= 31)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Amraka-Zyklus
                    </h2>
                    <canvas id="amrakaChart" height="140"></canvas>
                </div>

                <script>
                    window.amrakaChartLabels = @json($amrakaLabels);
                    window.amrakaChartValues = @json($amrakaValues);
                </script>
            @endif

            {{-- Card 27 – Bewertungen des Weltrat-Zyklus (≥ 32 Baxx) --}}
            @if ($userPoints >= 32)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Weltrat-Zyklus
                    </h2>
                    <canvas id="weltratChart" height="140"></canvas>
                </div>

                <script>
                    window.weltratChartLabels = @json($weltratLabels);
                    window.weltratChartValues = @json($weltratValues);
                </script>
            @endif

            {{-- Card 28 – Bewertungen der Hardcover (≥ 40 Baxx) --}}
            @if ($userPoints >= 40)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen der Hardcover
                    </h2>
                    <canvas id="hardcoverChart" height="140"></canvas>
                </div>

                <script>
                    window.hardcoverChartLabels = @json($hardcoverLabels);
                    window.hardcoverChartValues = @json($hardcoverValues);
                </script>
            @endif

            {{-- Card 29 – Hardcover je Autor:in (≥ 41 Baxx) --}}
            @if ($userPoints >= 41)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Maddrax-Hardcover je Autor:in
                    </h2>
                    <canvas id="hardcoverAuthorChart" height="140"></canvas>
                </div>

                <script>
                    window.hardcoverAuthorChartLabels = @json($hardcoverAuthorCounts->keys());
                    window.hardcoverAuthorChartValues = @json($hardcoverAuthorCounts->values());
                </script>
            @endif

            @if ($userPoints >= 1)
                @vite(['resources/js/statistik.js'])
            @endif
    </x-member-page>
</x-app-layout>
