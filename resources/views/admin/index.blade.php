<x-app-layout>
    <x-member-page>
        <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-6">Seitenaufrufe</h1>

        <div class="grid gap-8 lg:grid-cols-3">
            @php($hasRouteData = $visitData->isNotEmpty())
            <section
                class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6 flex flex-col justify-between"
                aria-labelledby="homepage-visits-heading"
            >
                <h2 id="homepage-visits-heading" class="text-lg font-semibold text-[#8B0116] dark:text-[#FCA5A5]">
                    Aufrufe der Startseite
                </h2>
                <p class="mt-6 text-4xl font-bold text-gray-900 dark:text-gray-100">
                    {{ number_format($homepageVisits, 0, ',', '.') }}
                </p>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Gesamtzugriffe auf <span class="font-medium" aria-label="Root-Route">/</span>
                </p>
            </section>

            <section
                class="lg:col-span-2 bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6"
                aria-labelledby="route-visits-heading"
            >
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-2 mb-4">
                    <h2 id="route-visits-heading" class="font-semibold text-lg text-[#8B0116] dark:text-[#FCA5A5]">
                        Seitenaufrufe nach Route
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Die Startseite wird separat angezeigt.
                    </p>
                </div>
                @if(! $hasRouteData)
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Noch keine Seitenaufrufe außerhalb der Startseite erfasst.
                    </p>
                @endif
                <canvas
                    id="visitsChart"
                    class="h-80 {{ $hasRouteData ? '' : 'opacity-50' }}"
                    role="img"
                    aria-label="Balkendiagramm der Seitenaufrufe nach Route"
                    aria-hidden="{{ $hasRouteData ? 'false' : 'true' }}"
                ></canvas>
            </section>
        </div>

        @php($hasUserVisitData = $userVisitData->isNotEmpty())
        <section
            class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6 mt-8"
            aria-labelledby="user-visits-heading"
        >
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-2 mb-4">
                <h2 id="user-visits-heading" class="font-semibold text-lg text-[#8B0116] dark:text-[#FCA5A5]">
                    Seitenaufrufe nach Nutzer:in
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Aggregiert nach der jeweiligen Hauptroute.
                </p>
            </div>
            <label for="userSelect" class="sr-only">Nutzer:in auswählen</label>
            <select
                id="userSelect"
                class="mb-3 border-gray-300 dark:bg-gray-700 dark:border-gray-600 rounded-md focus:border-[#8B0116] focus:ring-[#8B0116]"
                aria-describedby="userVisitsEmptyMessage"
            ></select>
            <p
                id="userVisitsEmptyMessage"
                class="text-sm text-gray-600 dark:text-gray-400 {{ $hasUserVisitData ? 'hidden' : '' }}"
            >
                Noch keine Daten für Unterseiten verfügbar.
            </p>
            <canvas
                id="userVisitsChart"
                class="h-80 {{ $hasUserVisitData ? '' : 'opacity-50' }}"
                role="img"
                aria-label="Balkendiagramm der Seitenaufrufe nach Nutzer:in"
                aria-hidden="{{ $hasUserVisitData ? 'false' : 'true' }}"
            ></canvas>
        </section>

        <section
            class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6 mt-8"
            aria-labelledby="active-users-heading"
        >
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-2 mb-4">
                <h2 id="active-users-heading" class="font-semibold text-lg text-[#8B0116] dark:text-[#FCA5A5]">
                    Aktive Mitglieder nach Uhrzeit
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Durchschnittliche Anzahl aktiver Mitglieder pro Stunde.
                </p>
            </div>
            <label for="weekdaySelect" class="sr-only">Wochentag auswählen</label>
            <select
                id="weekdaySelect"
                class="mb-3 border-gray-300 dark:bg-gray-700 dark:border-gray-600 rounded-md focus:border-[#8B0116] focus:ring-[#8B0116]"
                aria-label="Wochentag auswählen"
            ></select>
            <canvas
                id="activeUsersChart"
                class="h-80"
                role="img"
                aria-label="Liniendiagramm der aktiven Mitglieder nach Uhrzeit"
            ></canvas>
        </section>
    </x-member-page>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const visitData = @json($visitData);
        const userVisitData = @json($userVisitData);
        const activityData = @json($activityData);

        const numberFormatter = new Intl.NumberFormat('de-DE');
        const isDarkMode = document.documentElement.classList.contains('dark');
        const axisColor = isDarkMode ? '#D1D5DB' : '#4B5563';
        const gridColor = isDarkMode ? 'rgba(75, 85, 99, 0.3)' : 'rgba(156, 163, 175, 0.3)';
        const barColor = 'rgba(54, 162, 235, 0.7)';
        const barBorderColor = 'rgba(54, 162, 235, 1)';

        const formatTooltipLabel = (context) => {
            const value = context.parsed.y ?? context.parsed;
            return `${context.dataset.label}: ${numberFormatter.format(value)}`;
        };

        const visitsChartElement = document.getElementById('visitsChart');
        if (visitsChartElement) {
            const labels = visitData.map(v => v.path);
            const counts = visitData.map(v => v.total);

            new Chart(visitsChartElement.getContext('2d'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Seitenaufrufe',
                        data: counts,
                        backgroundColor: barColor,
                        borderColor: barBorderColor,
                        borderWidth: 1,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            ticks: { color: axisColor },
                            grid: { color: gridColor },
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: axisColor,
                                callback: value => numberFormatter.format(value),
                            },
                            grid: { color: gridColor },
                        },
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: formatTooltipLabel } },
                    },
                },
            });
        }

        const userSelect = document.getElementById('userSelect');
        const userVisitsEmptyMessage = document.getElementById('userVisitsEmptyMessage');
        const userVisitsChartElement = document.getElementById('userVisitsChart');
        const userPaths = [...new Set(userVisitData.map(v => v.path))];
        const userOptions = [...new Map(userVisitData.map(v => [String(v.user_id), v.user.name]))];

        const ctx2 = userVisitsChartElement.getContext('2d');
        const userChart = new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: userPaths,
                datasets: [],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        ticks: { color: axisColor },
                        grid: { color: gridColor },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: axisColor,
                            callback: value => numberFormatter.format(value),
                        },
                        grid: { color: gridColor },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: formatTooltipLabel } },
                },
            },
        });

        if (userOptions.length === 0 || userPaths.length === 0) {
            const option = document.createElement('option');
            option.textContent = 'Keine Daten verfügbar';
            option.disabled = true;
            option.selected = true;
            userSelect.appendChild(option);
            userSelect.disabled = true;
            userSelect.setAttribute('aria-disabled', 'true');
            userVisitsEmptyMessage?.classList.remove('hidden');
        } else {
            userOptions.forEach(([id, name]) => {
                const option = document.createElement('option');
                option.value = id;
                option.textContent = name;
                userSelect.appendChild(option);
            });

            const updateUserChart = (selectedUserId) => {
                const datasetLabel = userOptions.find(([id]) => id === String(selectedUserId))?.[1] ?? 'Seitenaufrufe';
                const data = userPaths.map(path => {
                    const record = userVisitData.find(v => v.path === path && String(v.user_id) === String(selectedUserId));
                    return record ? record.total : 0;
                });

                userChart.data.datasets = [{
                    label: datasetLabel,
                    data,
                    backgroundColor: barColor,
                    borderColor: barBorderColor,
                    borderWidth: 1,
                }];
                userChart.update();
            };

            updateUserChart(userOptions[0][0]);
            userSelect.addEventListener('change', (event) => updateUserChart(event.target.value));
            userVisitsEmptyMessage?.classList.add('hidden');
        }

        const weekdaySelect = document.getElementById('weekdaySelect');
        const dayNames = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
        dayNames.forEach((day, index) => {
            const option = document.createElement('option');
            option.value = String(index);
            option.textContent = day;
            weekdaySelect.appendChild(option);
        });
        const allOption = document.createElement('option');
        allOption.value = 'all';
        allOption.textContent = 'Alle';
        weekdaySelect.appendChild(allOption);

        const hours = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0') + ':00');
        const ctx3 = document.getElementById('activeUsersChart').getContext('2d');
        const activeChart = new Chart(ctx3, {
            type: 'line',
            data: {
                labels: hours,
                datasets: [{
                    label: dayNames[0],
                    data: activityData[0],
                    borderColor: barBorderColor,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    fill: false,
                    tension: 0.3,
                    pointRadius: 3,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        ticks: { color: axisColor },
                        grid: { color: gridColor },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: axisColor,
                            callback: value => numberFormatter.format(value),
                        },
                        grid: { color: gridColor },
                    },
                },
                plugins: {
                    legend: {
                        labels: { color: axisColor },
                    },
                    tooltip: {
                        callbacks: {
                            label: formatTooltipLabel,
                        },
                    },
                },
            },
        });

        const updateActiveChart = (selected) => {
            activeChart.data.datasets[0].data = activityData[selected] ?? [];
            activeChart.data.datasets[0].label = selected === 'all' ? 'Alle' : dayNames[selected];
            activeChart.update();
        };

        updateActiveChart('0');
        weekdaySelect.addEventListener('change', (event) => updateActiveChart(event.target.value));
    </script>
</x-app-layout>
