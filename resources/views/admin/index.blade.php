<x-app-layout>
    <x-member-page>
        {{-- Header --}}
        <x-header title="Seitenaufrufe" subtitle="Statistiken und Analysen der Website-Nutzung" separator class="mb-6" />

        {{-- Obere Stats-Reihe --}}
        <div class="grid gap-6 lg:grid-cols-4 mb-6">
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
        <x-card title="Aktive Mitglieder (letzte 7 Tage)" class="mb-6">
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

    {{-- Daten für Chart.js als data-Attribute --}}
    <div id="admin-charts-config"
        class="hidden"
        data-visit-data="{{ $visitData->toJson() }}"
        data-user-visit-data="{{ $userVisitData->toJson() }}"
        data-activity-data="{{ json_encode($activityData) }}"
        data-activity-timeline="{{ json_encode($activityTimeline) }}"
        data-browser-usage-by-browser="{{ $browserUsageByBrowser->toJson() }}"
        data-browser-usage-by-family="{{ $browserUsageByFamily->toJson() }}"
        data-device-usage="{{ $deviceUsage->toJson() }}"
    ></div>
</x-app-layout>
