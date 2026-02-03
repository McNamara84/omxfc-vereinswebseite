<x-app-layout>
    <x-member-page>
        {{-- Header --}}
        <x-header title="Seitenaufrufe" subtitle="Statistiken und Analysen der Website-Nutzung" separator />

        {{-- Obere Stats-Reihe --}}
        <div class="grid gap-6 lg:grid-cols-4 mb-8">
            {{-- Startseiten-Aufrufe --}}
            <section aria-labelledby="homepage-visits-heading">
                <x-stat
                    title="Aufrufe der Startseite"
                    :value="number_format($homepageVisits, 0, ',', '.')"
                    description="Gesamtzugriffe auf /"
                    icon="o-home"
                >
                    <x-slot:title>
                        <span id="homepage-visits-heading">Aufrufe der Startseite</span>
                    </x-slot:title>
                </x-stat>
            </section>

            {{-- Daily Active Users --}}
            @php($trendPositive = $dailyActiveUsers['trend'] > 0)
            @php($trendNegative = $dailyActiveUsers['trend'] < 0)
            <x-stat
                title="Daily Active Users"
                :value="number_format($dailyActiveUsers['today'], 0, ',', '.')"
                description="Aktive Mitglieder heute"
                icon="o-users"
                :color="$trendPositive ? 'text-success' : ($trendNegative ? 'text-error' : '')"
            >
                <x-slot:actions>
                    @if($trendPositive)
                        <x-badge value="+{{ abs($dailyActiveUsers['trend']) }}" class="badge-success badge-sm" />
                    @elseif($trendNegative)
                        <x-badge value="-{{ abs($dailyActiveUsers['trend']) }}" class="badge-error badge-sm" />
                    @else
                        <x-badge value="±0" class="badge-ghost badge-sm" />
                    @endif
                </x-slot:actions>
            </x-stat>

            {{-- 7-Tage-Durchschnitt --}}
            <x-stat
                title="7-Tage-Schnitt"
                :value="number_format($dailyActiveUsers['seven_day_average'], 1, ',', '.')"
                description="Durchschnitt aktiver Mitglieder"
                icon="o-chart-bar"
            />

            {{-- Gestern --}}
            <x-stat
                title="Gestern"
                :value="number_format($dailyActiveUsers['yesterday'], 0, ',', '.')"
                description="Aktive Mitglieder gestern"
                icon="o-calendar"
            />
        </div>

        {{-- DAU Sparkline --}}
        @php($dailyActiveSeries = collect($dailyActiveUsers['series']))
        @php($recentDailyActiveSeries = $dailyActiveSeries->slice(-7)->values())
        @php($recentDailyActiveMax = max($recentDailyActiveSeries->max('total') ?? 0, 1))
        <x-card title="Aktive Mitglieder (letzte 7 Tage)" class="mb-8">
            @if($recentDailyActiveSeries->isEmpty())
                <x-slot:empty>
                    <x-icon name="o-chart-bar" class="w-12 h-12 opacity-30 mx-auto" />
                    <p class="mt-2">Noch keine Login-Daten vorhanden.</p>
                </x-slot:empty>
            @else
                <div class="flex items-end justify-between gap-1 h-24" aria-hidden="true">
                    @foreach ($recentDailyActiveSeries as $entry)
                        @php($barHeight = $recentDailyActiveMax > 0 ? (int) round(($entry['total'] / $recentDailyActiveMax) * 100) : 0)
                        @php($dayLabel = \Carbon\Carbon::parse($entry['date'])->translatedFormat('D'))
                        <div class="flex-1 flex flex-col items-center text-xs opacity-60">
                            <span class="flex h-full w-full items-end">
                                <span
                                    class="w-full rounded-t-md {{ $entry['total'] === 0 ? 'bg-base-300' : 'bg-primary' }}"
                                    style="height: {{ $entry['total'] === 0 ? '4px' : max($barHeight, 12) . '%' }}"
                                ></span>
                            </span>
                            <span class="mt-1 block text-[10px] uppercase tracking-wide">{{ $dayLabel }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>

        {{-- Seitenaufrufe nach Route --}}
        @php($hasRouteData = $visitData->isNotEmpty())
        <section aria-labelledby="route-visits-heading">
        <x-card class="mb-8">
            <x-slot:title>
                <span id="route-visits-heading">Seitenaufrufe nach Route</span>
            </x-slot:title>
            <x-slot:subtitle>Die Startseite wird separat angezeigt.</x-slot:subtitle>
            @if(! $hasRouteData)
                <x-alert icon="o-information-circle" class="alert-info mb-4">
                    Noch keine Seitenaufrufe außerhalb der Startseite erfasst.
                </x-alert>
            @endif
            <div data-chart-wrapper>
                <canvas
                    id="visitsChart"
                    class="h-80 w-full {{ $hasRouteData ? '' : 'opacity-50' }}"
                    role="img"
                    aria-label="Balkendiagramm der Seitenaufrufe nach Route"
                    aria-hidden="{{ $hasRouteData ? 'false' : 'true' }}"
                ></canvas>
            </div>
        </x-card>
        </section>

        {{-- Browser-Nutzung --}}
        @php($hasBrowserUsageByBrowser = $browserUsageByBrowser->isNotEmpty())
        @php($hasBrowserUsageByFamily = $browserUsageByFamily->isNotEmpty())
        @php($hasDeviceUsage = $deviceUsage->isNotEmpty())
        <x-card title="Browsernutzung unserer Mitglieder" subtitle="Basierend auf den letzten 30 Tagen aktiver Mitglieder." class="mb-8">
            <div class="grid grid-cols-1 gap-10 xl:grid-cols-3">
                {{-- Beliebteste Browser --}}
                <div class="flex flex-col items-center">
                    <h3 class="text-lg font-semibold mb-4">Beliebteste Browser</h3>
                    <div data-chart-wrapper class="w-full max-w-xl">
                        <canvas
                            id="browserUsageChart"
                            class="h-72 w-full {{ $hasBrowserUsageByBrowser ? '' : 'opacity-50' }}"
                            role="img"
                            aria-label="Kreisdiagramm der beliebtesten Browser"
                        ></canvas>
                    </div>
                    @if ($hasBrowserUsageByBrowser)
                        @php($totalBrowserSessions = max($browserUsageByBrowser->sum('value'), 1))
                        <div class="mt-4 w-full space-y-2">
                            @foreach ($browserUsageByBrowser as $entry)
                                @php($percentage = round(($entry['value'] / $totalBrowserSessions) * 100))
                                <div class="flex items-center justify-between gap-2 rounded-lg bg-base-200 px-3 py-2">
                                    <span class="font-medium">{{ $entry['label'] }}</span>
                                    <div class="flex items-baseline gap-1">
                                        <span class="font-semibold">{{ $entry['value'] }}</span>
                                        <x-badge :value="$percentage . '%'" class="badge-ghost badge-sm" />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-4 text-sm opacity-60 text-center">Noch keine Login-Daten vorhanden.</p>
                    @endif
                </div>

                {{-- Browser-Familien --}}
                <div class="flex flex-col items-center">
                    <h3 class="text-lg font-semibold mb-4">Browser-Familien</h3>
                    <div data-chart-wrapper class="w-full max-w-xl">
                        <canvas
                            id="browserFamilyChart"
                            class="h-72 w-full {{ $hasBrowserUsageByFamily ? '' : 'opacity-50' }}"
                            role="img"
                            aria-label="Kreisdiagramm der Browser-Familien"
                        ></canvas>
                    </div>
                    @if ($hasBrowserUsageByFamily)
                        @php($totalBrowserFamilies = max($browserUsageByFamily->sum('value'), 1))
                        <div class="mt-4 w-full space-y-2">
                            @foreach ($browserUsageByFamily as $entry)
                                @php($percentage = round(($entry['value'] / $totalBrowserFamilies) * 100))
                                <div class="flex items-center justify-between gap-2 rounded-lg bg-base-200 px-3 py-2">
                                    <span class="font-medium">{{ $entry['label'] }}</span>
                                    <div class="flex items-baseline gap-1">
                                        <span class="font-semibold">{{ $entry['value'] }}</span>
                                        <x-badge :value="$percentage . '%'" class="badge-ghost badge-sm" />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-4 text-sm opacity-60 text-center">Noch keine Login-Daten vorhanden.</p>
                    @endif
                </div>

                {{-- Endgeräte --}}
                <div class="flex flex-col items-center">
                    <h3 class="text-lg font-semibold mb-4">Endgeräte unserer Mitglieder</h3>
                    <p class="text-sm opacity-60 text-center mb-4">Vergleicht Mobil- und Festgeräte.</p>
                    <div data-chart-wrapper class="w-full max-w-xl">
                        <canvas
                            id="deviceUsageChart"
                            class="h-72 w-full {{ $hasDeviceUsage ? '' : 'opacity-50' }}"
                            role="img"
                            aria-label="Kreisdiagramm der Endgeräte"
                        ></canvas>
                    </div>
                    @if ($hasDeviceUsage)
                        @php($totalDeviceUsage = max($deviceUsage->sum('value'), 1))
                        <div class="mt-4 w-full space-y-2">
                            @foreach ($deviceUsage as $entry)
                                @php($percentage = round(($entry['value'] / $totalDeviceUsage) * 100))
                                <div class="flex items-center justify-between gap-2 rounded-lg bg-base-200 px-3 py-2">
                                    <span class="font-medium">{{ $entry['label'] }}</span>
                                    <div class="flex items-baseline gap-1">
                                        <span class="font-semibold">{{ $entry['value'] }}</span>
                                        <x-badge :value="$percentage . '%'" class="badge-ghost badge-sm" />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-4 text-sm opacity-60 text-center">Noch keine Login-Daten vorhanden.</p>
                    @endif
                </div>
            </div>
        </x-card>

        {{-- Seitenaufrufe nach Nutzer:in --}}
        @php($hasUserVisitData = $userVisitData->isNotEmpty())
        <x-card title="Seitenaufrufe nach Nutzer:in" subtitle="Aggregiert nach der jeweiligen Hauptroute." class="mb-8">
            <div class="mb-4">
                <label for="userSelect" class="sr-only">Nutzer:in auswählen</label>
                <select
                    id="userSelect"
                    class="select select-bordered w-full max-w-xs"
                    aria-describedby="userVisitsEmptyMessage"
                ></select>
            </div>
            <p
                id="userVisitsEmptyMessage"
                class="text-sm opacity-60 {{ $hasUserVisitData ? 'hidden' : '' }}"
            >
                Noch keine Daten für Unterseiten verfügbar.
            </p>
            <div data-chart-wrapper>
                <canvas
                    id="userVisitsChart"
                    class="h-80 w-full {{ $hasUserVisitData ? '' : 'opacity-50' }}"
                    role="img"
                    aria-label="Balkendiagramm der Seitenaufrufe nach Nutzer:in"
                    aria-hidden="{{ $hasUserVisitData ? 'false' : 'true' }}"
                ></canvas>
            </div>
        </x-card>

        {{-- Aktive Mitglieder nach Uhrzeit --}}
        <x-card title="Aktive Mitglieder nach Uhrzeit" subtitle="Durchschnittliche Anzahl aktiver Mitglieder pro Stunde." class="mb-8">
            <div class="mb-4">
                <label for="weekdaySelect" class="sr-only">Wochentag auswählen</label>
                <select
                    id="weekdaySelect"
                    class="select select-bordered w-full max-w-xs"
                    aria-label="Wochentag auswählen"
                ></select>
            </div>
            <div data-chart-wrapper>
                <canvas
                    id="activeUsersChart"
                    class="h-80 w-full"
                    role="img"
                    aria-label="Liniendiagramm der aktiven Mitglieder nach Uhrzeit"
                ></canvas>
            </div>
        </x-card>

        {{-- Aktive Mitglieder nach Wochentag --}}
        <x-card title="Aktive Mitglieder nach Wochentag" subtitle="Verlauf aktiver Mitglieder je Stunde, gruppiert nach Wochentag.">
            <div data-chart-wrapper aria-describedby="active-users-weekday-description">
                <p id="active-users-weekday-description" class="sr-only">
                    Zeigt den Verlauf aktiver Mitglieder je Stunde, gruppiert nach Wochentag.
                </p>
                <canvas
                    id="activeUsersWeekdayChart"
                    class="h-80 w-full"
                    role="img"
                    aria-label="Liniendiagramm der aktiven Mitglieder nach Wochentag und Uhrzeit"
                ></canvas>
            </div>
        </x-card>
    </x-member-page>

    {{-- Chart.js --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const visitData = @json($visitData);
        const userVisitData = @json($userVisitData);
        const activityData = @json($activityData);
        const activityTimeline = @json($activityTimeline);
        const browserUsageByBrowser = @json($browserUsageByBrowser);
        const browserUsageByFamily = @json($browserUsageByFamily);
        const deviceUsage = @json($deviceUsage);

        const numberFormatter = new Intl.NumberFormat('de-DE');

        // daisyUI-kompatible Farben basierend auf CSS-Variablen
        const getComputedColor = (varName) => {
            const style = getComputedStyle(document.documentElement);
            return style.getPropertyValue(varName).trim() || null;
        };

        const isDarkMode = document.documentElement.dataset.theme === 'coffee';
        const axisColor = isDarkMode ? 'oklch(0.85 0 0)' : 'oklch(0.4 0 0)';
        const gridColor = isDarkMode ? 'oklch(0.3 0 0 / 0.3)' : 'oklch(0.7 0 0 / 0.3)';

        // daisyUI-Farbpalette
        const paletteBase = Object.freeze([
            'oklch(0.65 0.24 16)',    // primary-ish
            'oklch(0.7 0.18 250)',    // secondary-ish
            'oklch(0.75 0.15 160)',   // accent-ish
            'oklch(0.6 0.2 30)',      // warm
            'oklch(0.65 0.15 200)',   // cool
            'oklch(0.7 0.12 80)',     // yellow
            'oklch(0.55 0.2 300)',    // purple
            'oklch(0.6 0.18 350)',    // pink
            'oklch(0.7 0.14 140)',    // green
            'oklch(0.65 0.16 50)',    // orange
        ]);

        const barDatasetStyles = Object.freeze({
            backgroundColor: 'oklch(0.65 0.2 250 / 0.7)',
            borderColor: 'oklch(0.65 0.2 250)',
            borderWidth: 1,
        });

        const lineDatasetStyles = Object.freeze({
            borderColor: 'oklch(0.65 0.2 250)',
            backgroundColor: 'oklch(0.65 0.2 250 / 0.2)',
            fill: false,
            tension: 0.3,
            pointRadius: 3,
        });

        const formatTooltipLabel = (context) => {
            const value = context.parsed.y ?? context.parsed;
            return `${context.dataset.label}: ${numberFormatter.format(value)}`;
        };

        const getCommonOptions = ({ showLegend = false, additionalOptions = {} } = {}) => {
            const baseOptions = {
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
            };

            if (showLegend) {
                baseOptions.plugins.legend = { display: true, labels: { color: axisColor } };
            }

            return {
                ...baseOptions,
                ...additionalOptions,
                scales: {
                    ...baseOptions.scales,
                    ...(additionalOptions.scales ?? {}),
                },
                plugins: {
                    ...baseOptions.plugins,
                    ...(additionalOptions.plugins ?? {}),
                },
            };
        };

        const getPalette = (length) => {
            if (length <= paletteBase.length) {
                return paletteBase.slice(0, length);
            }

            const extended = [...paletteBase];
            while (extended.length < length) {
                extended.push(paletteBase[extended.length % paletteBase.length]);
            }

            return extended.slice(0, length);
        };

        const renderDoughnutChart = (canvasId, entries) => {
            const canvas = document.getElementById(canvasId);
            if (!canvas || !Array.isArray(entries) || entries.length === 0) {
                return;
            }

            const labels = entries.map(entry => entry.label);
            const values = entries.map(entry => entry.value);
            const colors = getPalette(labels.length);
            const total = values.reduce((sum, value) => sum + value, 0) || 1;

            new Chart(canvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [
                        {
                            data: values,
                            backgroundColor: colors,
                            borderWidth: 0,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: axisColor,
                                usePointStyle: true,
                                padding: 16,
                            },
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const value = context.parsed;
                                    const percentage = total === 0 ? 0 : ((value / total) * 100).toFixed(1);
                                    return `${context.label}: ${numberFormatter.format(value)} Mitglieder (${percentage}%)`;
                                },
                            },
                        },
                    },
                },
            });
        };

        // Seitenaufrufe nach Route
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
                        ...barDatasetStyles,
                    }],
                },
                options: getCommonOptions(),
            });
        }

        // Browser-Charts
        renderDoughnutChart('browserUsageChart', browserUsageByBrowser);
        renderDoughnutChart('browserFamilyChart', browserUsageByFamily);
        renderDoughnutChart('deviceUsageChart', deviceUsage);

        // Nutzer-Auswahl
        const userSelect = document.getElementById('userSelect');
        const userVisitsEmptyMessage = document.getElementById('userVisitsEmptyMessage');
        const userVisitsChartElement = document.getElementById('userVisitsChart');
        const userPaths = [...new Set(userVisitData.map(v => v.path))];
        const userOptions = [...new Map(userVisitData.map(v => [String(v.user_id), v.user.name]))];

        if (userVisitsChartElement && userSelect) {
            const ctx2 = userVisitsChartElement.getContext('2d');
            if (ctx2) {
                const userChart = new Chart(ctx2, {
                    type: 'bar',
                    data: {
                        labels: userPaths,
                        datasets: [],
                    },
                    options: getCommonOptions(),
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
                            ...barDatasetStyles,
                        }];
                        userChart.update();
                    };

                    updateUserChart(userOptions[0][0]);
                    userSelect.addEventListener('change', (event) => updateUserChart(event.target.value));
                    userVisitsEmptyMessage?.classList.add('hidden');
                }
            }
        }

        // Wochentag-Auswahl
        const weekdaySelect = document.getElementById('weekdaySelect');
        const dayNames = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
        if (weekdaySelect) {
            const allOption = document.createElement('option');
            allOption.value = 'all';
            allOption.textContent = 'Alle';
            allOption.selected = true;
            weekdaySelect.appendChild(allOption);

            dayNames.forEach((day, index) => {
                const option = document.createElement('option');
                option.value = String(index);
                option.textContent = day;
                weekdaySelect.appendChild(option);
            });
        }

        // Aktive Mitglieder nach Uhrzeit
        const hours = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0') + ':00');
        const activeUsersChartElement = document.getElementById('activeUsersChart');
        const ctx3 = activeUsersChartElement?.getContext?.('2d');
        if (ctx3) {
            const activeChart = new Chart(ctx3, {
                type: 'line',
                data: {
                    labels: hours,
                    datasets: [{
                        label: 'Alle',
                        data: activityData['all'] ?? [],
                        ...lineDatasetStyles,
                    }],
                },
                options: getCommonOptions({ showLegend: true }),
            });

            const updateActiveChart = (selected) => {
                const dataset = activityData[selected] ?? [];
                activeChart.data.datasets[0].data = dataset;
                if (selected === 'all') {
                    activeChart.data.datasets[0].label = 'Alle';
                } else {
                    const dayIndex = Number.parseInt(selected, 10);
                    activeChart.data.datasets[0].label = Number.isNaN(dayIndex) ? 'Alle' : dayNames[dayIndex];
                }
                activeChart.update();
            };

            updateActiveChart('all');
            weekdaySelect?.addEventListener('change', (event) => updateActiveChart(event.target.value));
        }

        // Aktive Mitglieder nach Wochentag
        const activeUsersWeekdayElement = document.getElementById('activeUsersWeekdayChart');
        const ctx4 = activeUsersWeekdayElement?.getContext?.('2d');
        if (ctx4) {
            const weekdayLabels = [];
            const weekdayValues = [];

            activityTimeline.forEach((entry) => {
                const dayLabel = dayNames[entry.weekday] ?? '';
                const hourLabel = hours[entry.hour] ?? '';
                weekdayLabels.push(`${dayLabel}\n${hourLabel}`);
                weekdayValues.push(entry.total ?? 0);
            });

            new Chart(ctx4, {
                type: 'line',
                data: {
                    labels: weekdayLabels,
                    datasets: [
                        {
                            label: 'Aktive Mitglieder',
                            data: weekdayValues,
                            ...lineDatasetStyles,
                        },
                    ],
                },
                options: getCommonOptions({
                    showLegend: true,
                    additionalOptions: {
                        scales: {
                            x: {
                                ticks: {
                                    color: axisColor,
                                    autoSkip: true,
                                    maxRotation: 0,
                                    callback(value) {
                                        const label = this.getLabelForValue(value);
                                        return typeof label === 'string' ? label.split('\n') : label;
                                    },
                                },
                                grid: {
                                    color: gridColor,
                                    drawOnChartArea: false,
                                },
                            },
                        },
                    },
                }),
            });
        }
    </script>
</x-app-layout>
