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
            <script>
                window.userPoints = {{ $userPoints }};
            </script>
            {{-- Card 1 – Balkendiagramm (≥ 2 Bakk) --}}
                <div data-min-points="2" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
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
            {{-- Card 2 – Teamplayer-Tabelle (≥ 4 Baxx) --}}
                <div data-min-points="4" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
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
            {{-- Card 3 – Top 10 Maddrax-Romane (≥ 5 Baxx) --}}
                <div data-min-points="5" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
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
            {{-- Card 4 – Top-Autor:innen nach Ø‑Bewertung (≥ 7 Baxx) --}}
                <div data-min-points="7" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
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
            {{-- Card 5 – Top-Charaktere nach Auftritten (≥ 10 Baxx) --}}
                <div data-min-points="10" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
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
            {{-- Card 6 – Bewertungen im Maddraxikon (≥ 11 Baxx) --}}
                <div data-min-points="11" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6 grid grid-cols-1 md:grid-cols-3 gap-6 text-center">
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
            {{-- Card 7 – Rezensionen unserer Mitglieder (≥ 12 Baxx) --}}
                <div data-min-points="12" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
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
            {{-- Card 8 – Bewertungen des Euree-Zyklus (≥ 13 Baxx) --}}
                <div data-min-points="13" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Euree-Zyklus
                    </h2>
                    <canvas id="eureeChart" height="140"></canvas>
                </div>

                <script>
                    window.eureeChartLabels = @json($eureeLabels);
                    window.eureeChartValues = @json($eureeValues);
                </script>

            {{-- Card 9 – Bewertungen des Meeraka-Zyklus (≥ 14 Baxx) --}}
                <div data-min-points="14" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Meeraka-Zyklus
                    </h2>
                    <canvas id="meerakaChart" height="140"></canvas>
                </div>

                <script>
                    window.meerakaChartLabels = @json($meerakaLabels);
                    window.meerakaChartValues = @json($meerakaValues);
                </script>

            {{-- Card 10 – Bewertungen des Expeditions-Zyklus (≥ 15 Baxx) --}}
                <div data-min-points="15" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Expeditions-Zyklus
                    </h2>
                    <canvas id="expeditionChart" height="140"></canvas>
                </div>

                <script>
                    window.expeditionChartLabels = @json($expeditionLabels);
                    window.expeditionChartValues = @json($expeditionValues);
                </script>

            {{-- Card 11 – Bewertungen des Kratersee-Zyklus (≥ 16 Baxx) --}}
                <div data-min-points="16" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Kratersee-Zyklus
                    </h2>
                    <canvas id="kraterseeChart" height="140"></canvas>
                </div>

                <script>
                    window.kraterseeChartLabels = @json($kraterseeLabels);
                    window.kraterseeChartValues = @json($kraterseeValues);
                </script>

            {{-- Card 12 – Bewertungen des Daa'muren-Zyklus (≥ 17 Baxx) --}}
                <div data-min-points="17" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Daa'muren-Zyklus
                    </h2>
                    <canvas id="daaMurenChart" height="140"></canvas>
                </div>

                <script>
                    window.daaMurenChartLabels = @json($daaMurenLabels);
                    window.daaMurenChartValues = @json($daaMurenValues);
                </script>

            {{-- Card 13 – Bewertungen des Wandler-Zyklus (≥ 18 Baxx) --}}
                <div data-min-points="18" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Wandler-Zyklus
                    </h2>
                    <canvas id="wandlerChart" height="140"></canvas>
                </div>

                <script>
                    window.wandlerChartLabels = @json($wandlerLabels);
                    window.wandlerChartValues = @json($wandlerValues);
                </script>

            {{-- Card 14 – Bewertungen des Mars-Zyklus (≥ 19 Baxx) --}}
                <div data-min-points="19" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Mars-Zyklus
                    </h2>
                    <canvas id="marsChart" height="140"></canvas>
                </div>

                <script>
                    window.marsChartLabels = @json($marsLabels);
                    window.marsChartValues = @json($marsValues);
                </script>

            {{-- Card 15 – Bewertungen des Ausala-Zyklus (≥ 20 Baxx) --}}
                <div data-min-points="20" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Ausala-Zyklus
                    </h2>
                    <canvas id="ausalaChart" height="140"></canvas>
                </div>

                <script>
                    window.ausalaChartLabels = @json($ausalaLabels);
                    window.ausalaChartValues = @json($ausalaValues);
                </script>

            {{-- Card 16 – Bewertungen des Afra-Zyklus (≥ 21 Baxx) --}}
                <div data-min-points="21" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Afra-Zyklus
                    </h2>
                    <canvas id="afraChart" height="140"></canvas>
                </div>

                <script>
                    window.afraChartLabels = @json($afraLabels);
                    window.afraChartValues = @json($afraValues);
                </script>

            {{-- Card 17 – Bewertungen des Antarktis-Zyklus (≥ 22 Baxx) --}}
                <div data-min-points="22" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Antarktis-Zyklus
                    </h2>
                    <canvas id="antarktisChart" height="140"></canvas>
                </div>

                <script>
                    window.antarktisChartLabels = @json($antarktisLabels);
                    window.antarktisChartValues = @json($antarktisValues);
                </script>

            {{-- Card 18 – Bewertungen des Schatten-Zyklus (≥ 23 Baxx) --}}
                <div data-min-points="23" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Schatten-Zyklus
                    </h2>
                    <canvas id="schattenChart" height="140"></canvas>
                </div>

                <script>
                    window.schattenChartLabels = @json($schattenLabels);
                    window.schattenChartValues = @json($schattenValues);
                </script>

            {{-- Card 19 – Bewertungen des Ursprung-Zyklus (≥ 24 Baxx) --}}
                <div data-min-points="24" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Ursprung-Zyklus
                    </h2>
                    <canvas id="ursprungChart" height="140"></canvas>
                </div>

                <script>
                    window.ursprungChartLabels = @json($ursprungLabels);
                    window.ursprungChartValues = @json($ursprungValues);
                </script>

            {{-- Card 20 – Bewertungen des Streiter-Zyklus (≥ 25 Baxx) --}}
                <div data-min-points="25" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Streiter-Zyklus
                    </h2>
                    <canvas id="streiterChart" height="140"></canvas>
                </div>

                <script>
                    window.streiterChartLabels = @json($streiterLabels);
                    window.streiterChartValues = @json($streiterValues);
                </script>

            {{-- Card 21 – Bewertungen des Archivar-Zyklus (≥ 26 Baxx) --}}
                <div data-min-points="26" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Archivar-Zyklus
                    </h2>
                    <canvas id="archivarChart" height="140"></canvas>
                </div>

                <script>
                    window.archivarChartLabels = @json($archivarLabels);
                    window.archivarChartValues = @json($archivarValues);
                </script>

            {{-- Card 22 – Bewertungen des Zeitsprung-Zyklus (≥ 27 Baxx) --}}
                <div data-min-points="27" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Zeitsprung-Zyklus
                    </h2>
                    <canvas id="zeitsprungChart" height="140"></canvas>
                </div>

                <script>
                    window.zeitsprungChartLabels = @json($zeitsprungLabels);
                    window.zeitsprungChartValues = @json($zeitsprungValues);
                </script>

            {{-- Card 23 – Bewertungen des Fremdwelt-Zyklus (≥ 28 Baxx) --}}
                <div data-min-points="28" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Fremdwelt-Zyklus
                    </h2>
                    <canvas id="fremdweltChart" height="140"></canvas>
                </div>

                <script>
                    window.fremdweltChartLabels = @json($fremdweltLabels);
                    window.fremdweltChartValues = @json($fremdweltValues);
                </script>

            {{-- Card 24 – Bewertungen des Parallelwelt-Zyklus (≥ 29 Baxx) --}}
                <div data-min-points="29" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Parallelwelt-Zyklus
                    </h2>
                    <canvas id="parallelweltChart" height="140"></canvas>
                </div>

                <script>
                    window.parallelweltChartLabels = @json($parallelweltLabels);
                    window.parallelweltChartValues = @json($parallelweltValues);
                </script>

            {{-- Card 25 – Bewertungen des Weltenriss-Zyklus (≥ 30 Baxx) --}}
                <div data-min-points="30" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Weltenriss-Zyklus
                    </h2>
                    <canvas id="weltenrissChart" height="140"></canvas>
                </div>

                <script>
                    window.weltenrissChartLabels = @json($weltenrissLabels);
                    window.weltenrissChartValues = @json($weltenrissValues);
                </script>

            {{-- Card 26 – Bewertungen des Amraka-Zyklus (≥ 31 Baxx) --}}
                <div data-min-points="31" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Amraka-Zyklus
                    </h2>
                    <canvas id="amrakaChart" height="140"></canvas>
                </div>

                <script>
                    window.amrakaChartLabels = @json($amrakaLabels);
                    window.amrakaChartValues = @json($amrakaValues);
                </script>

            {{-- Card 27 – Bewertungen des Weltrat-Zyklus (≥ 32 Baxx) --}}
                <div data-min-points="32" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen des Weltrat-Zyklus
                    </h2>
                    <canvas id="weltratChart" height="140"></canvas>
                </div>

                <script>
                    window.weltratChartLabels = @json($weltratLabels);
                    window.weltratChartValues = @json($weltratValues);
                </script>

            {{-- Card 28 – Bewertungen der Hardcover (≥ 40 Baxx) --}}
                <div data-min-points="40" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Bewertungen der Hardcover
                    </h2>
                    <canvas id="hardcoverChart" height="140"></canvas>
                </div>

                <script>
                    window.hardcoverChartLabels = @json($hardcoverLabels);
                    window.hardcoverChartValues = @json($hardcoverValues);
                </script>

            {{-- Card 29 – Hardcover je Autor:in (≥ 41 Baxx) --}}
                <div data-min-points="41" class="relative bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 text-center">
                        Maddrax-Hardcover je Autor:in
                    </h2>
                    <canvas id="hardcoverAuthorChart" height="140"></canvas>
                </div>

                <script>
                    window.hardcoverAuthorChartLabels = @json($hardcoverAuthorCounts->keys());
                    window.hardcoverAuthorChartValues = @json($hardcoverAuthorCounts->values());
                </script>

                @vite(['resources/js/statistik.js'])
    </x-member-page>
</x-app-layout>
