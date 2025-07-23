{{-- resources/views/statistik/index.blade.php --}}
<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Kopfzeile --}}
            <div
                class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-[#FF6B81]">
                    Statistik
                </h1>
            </div>

            {{-- Card 1 – Grundstatistiken (für alle sichtbar) --}}
            <div
                class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6 grid grid-cols-1 md:grid-cols-3 gap-6 text-center">
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

            {{-- Card 2 – Balkendiagramm (≥ 1 Bakk) --}}
            @if ($userPoints >= 1)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4">
                        Romane je Autor:in
                    </h2>
                    <canvas id="authorChart" height="140"></canvas>
                </div>

                {{-- Chart-Daten global für JS-Modul --}}
                <script>
                    window.authorChartLabels = @json($authorCounts->keys());
                    window.authorChartValues = @json($authorCounts->values());
                </script>
            @endif

            {{-- TODO: Card 3 – Teamplayer-Tabelle (≥ 2 Baxx) --}}

            {{-- Card 4 – Romane-Tabelle (≥ 4 Baxx) --}}
            @if ($userPoints >= 4)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4">
                        Alle Romane
                    </h2>

                    <div class="overflow-x-auto">
                        <table id="romaneTable" class="w-full text-left">
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

            {{-- Card 4 – Top-Autor:innen nach Ø‑Bewertung (≥ 10 Baxx) --}}
            @if ($userPoints >= 10)
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4">
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
                        Diese Statistik wird ab <strong>10</strong> Baxx freigeschaltet.<br>
                        Dein aktueller Stand: <span class="font-semibold">{{ $userPoints }}</span>.
                    </p>
                </div>
            @endif

            {{-- Vite-Asset EINMAL am Ende laden, sobald irgendeine JS-Card erscheint --}}
            @if ($userPoints >= 1)
                @vite(['resources/js/statistik.js'])
            @endif
        </div>
    </div>
</x-app-layout>