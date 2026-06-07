<div class="pb-8" data-testid="kompendium-search-statistics-dashboard">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <x-ui.page-header
            eyebrow="Adminbereich"
            title="Kompendium-Suchstatistik"
            description="Analysiere Suchbegriffe, Nulltreffer, Filterverhalten und Nutzeraktivitaet der Kompendium-Volltextsuche."
            data-testid="search-statistics-header"
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <x-button label="Kompendium verwalten" link="{{ route('kompendium.admin') }}" wire:navigate icon="o-cog-6-tooth" class="btn-ghost" />
                    <x-button label="Zum Kompendium" link="{{ route('kompendium.index') }}" wire:navigate icon="o-arrow-left" class="btn-ghost" />
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('success'))
            <x-alert icon="o-check-circle" class="alert-success" dismissible data-testid="search-statistics-success">
                {{ session('success') }}
            </x-alert>
        @endif

        <x-ui.panel title="Filter" description="Grenze die Auswertung nach Zeitraum, Nutzer, Quelle oder Nulltreffern ein." data-testid="search-statistics-filters">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <x-input label="Von" type="date" wire:model.live="from" data-testid="search-statistics-from" />
                <x-input label="Bis" type="date" wire:model.live="to" data-testid="search-statistics-to" />

                @php
                    $userOptions = $this->usersForFilter
                        ->map(fn($user) => ['id' => (string) $user->id, 'name' => $user->name])
                        ->prepend(['id' => '', 'name' => 'Alle Nutzer'])
                        ->values()
                        ->toArray();
                @endphp

                <x-select label="Nutzer" :options="$userOptions" wire:model.live="userId" data-testid="search-statistics-user" />
                <x-select label="Quelle" :options="$this->sourceOptions" wire:model.live="source" data-testid="search-statistics-source" />
                <x-input label="Suchbegriff enthaelt" wire:model.live.debounce.300ms="term" icon="o-magnifying-glass" data-testid="search-statistics-term" />

                <div class="flex flex-col gap-3 pt-1">
                    <label class="label cursor-pointer justify-start gap-3">
                        <input type="checkbox" wire:model.live="onlyZeroResults" class="checkbox checkbox-sm" data-testid="search-statistics-zero-only" />
                        <span class="label-text">Nur Nulltreffer</span>
                    </label>
                    <label class="label cursor-pointer justify-start gap-3">
                        <input type="checkbox" wire:model.live="includeAdminSearches" class="checkbox checkbox-sm" data-testid="search-statistics-include-admin" />
                        <span class="label-text">Admin-Suchen einschliessen</span>
                    </label>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap justify-between gap-2 border-t border-base-content/10 pt-4">
                <p class="text-sm leading-relaxed text-base-content/68" data-testid="admin-searches-default-note">
                    Admin-Suchen sind standardmaessig ausgeblendet und koennen ueber den Filter eingeschlossen werden.
                </p>
                <x-button label="Filter zuruecksetzen" wire:click="resetFilters" icon="o-arrow-path" class="btn-ghost btn-sm" />
            </div>
        </x-ui.panel>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-5" data-testid="search-statistics-kpis">
            <x-stat title="Suchanfragen" :value="$this->summary['total']" icon="o-magnifying-glass" />
            <x-stat title="Nutzer" :value="$this->summary['unique_users']" icon="o-users" color="text-info" />
            <x-stat title="Nulltreffer" :value="$this->summary['zero_results']" icon="o-x-circle" color="text-error" />
            <x-stat title="Nulltreffer-Quote" :value="$this->summary['zero_result_rate'] . '%'" icon="o-chart-bar-square" color="text-warning" />
            <x-stat title="Filter/Sortierung" :value="$this->summary['filter_changes']" icon="o-funnel" color="text-primary" />
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
            <x-ui.panel title="Suchanfragen im Zeitraum" description="Eine kompakte Tagesansicht der geloggten Suchaktivitaet." data-testid="searches-over-time-chart">
                @php
                    $timelineMax = max((int) ($this->searchesOverTime->max('total') ?? 0), 1);
                @endphp
                @if($this->searchesOverTime->isEmpty())
                    <p class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-6 text-center text-base-content/60">
                        Keine Suchdaten im gewaehlten Zeitraum.
                    </p>
                @else
                    <div class="flex h-64 items-end gap-2 overflow-x-auto border-b border-base-content/10 pb-3">
                        @foreach($this->searchesOverTime as $entry)
                            @php
                                $height = max(6, ((int) $entry['total'] / $timelineMax) * 100);
                            @endphp
                            <div class="flex min-w-10 flex-1 flex-col items-center justify-end gap-2" title="{{ $entry['day'] }}: {{ $entry['total'] }}">
                                <div class="w-full rounded-t bg-primary/75" style="height: {{ $height }}%"></div>
                                <span class="text-[0.68rem] text-base-content/55">{{ $entry['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.panel>

            <x-ui.panel title="Quellen" description="Wie Suchereignisse entstanden sind." data-testid="source-distribution-chart">
                @php
                    $sourceMax = max((int) ($this->sourceDistribution->max('total') ?? 0), 1);
                @endphp
                <div class="space-y-3">
                    @forelse($this->sourceDistribution as $entry)
                        <div>
                            <div class="mb-1 flex justify-between gap-3 text-sm">
                                <span class="font-medium">{{ $entry['label'] }}</span>
                                <span class="tabular-nums text-base-content/60">{{ $entry['total'] }}</span>
                            </div>
                            <div class="h-3 overflow-hidden rounded-full bg-base-200">
                                <div class="h-full rounded-full bg-accent" style="width: {{ max(4, ($entry['total'] / $sourceMax) * 100) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-6 text-center text-base-content/60">
                            Keine Quellen im gewaehlten Zeitraum.
                        </p>
                    @endforelse
                </div>
            </x-ui.panel>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-ui.panel title="Top-Suchbegriffe" description="Die am haeufigsten eingegebenen Suchbegriffe." data-testid="top-search-queries">
                @php
                    $topMax = max((int) ($this->topQueries->max('total') ?? 0), 1);
                @endphp
                <div class="space-y-4">
                    @forelse($this->topQueries as $entry)
                        <div>
                            <div class="mb-1 flex justify-between gap-3 text-sm">
                                <span class="font-medium">{{ $entry['query'] }}</span>
                                <span class="tabular-nums text-base-content/60">{{ $entry['total'] }}</span>
                            </div>
                            <div class="h-3 overflow-hidden rounded-full bg-base-200">
                                <div class="h-full rounded-full bg-primary" style="width: {{ max(4, ($entry['total'] / $topMax) * 100) }}%"></div>
                            </div>
                            @if($entry['zero_results'] > 0)
                                <p class="mt-1 text-xs text-error">{{ $entry['zero_results'] }} ohne Treffer</p>
                            @endif
                        </div>
                    @empty
                        <p class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-6 text-center text-base-content/60">
                            Keine Suchbegriffe im gewaehlten Zeitraum.
                        </p>
                    @endforelse
                </div>
            </x-ui.panel>

            <x-ui.panel title="Nulltreffer" description="Suchbegriffe, die keine Treffer geliefert haben." data-testid="zero-result-queries">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Suchbegriff</th>
                                <th class="text-right">Anzahl</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($this->zeroResultQueries as $entry)
                                <tr>
                                    <td>{{ $entry['query'] }}</td>
                                    <td class="text-right tabular-nums">{{ $entry['total'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="py-6 text-center text-base-content/60">Keine Nulltreffer im gewaehlten Zeitraum.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.panel>
        </div>

        <x-ui.panel title="Nutzerstatistik" description="Suchaktivitaet je Nutzer, standardmaessig ohne Admin-Suchen." data-testid="user-search-statistics">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nutzer</th>
                            <th class="text-right">Suchen</th>
                            <th class="text-right">Nulltreffer</th>
                            <th class="text-right">Quote</th>
                            <th>Letzte Suche</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->userStats as $entry)
                            <tr>
                                <td>
                                    <span class="font-medium">{{ $entry['user']?->name ?? 'Geloeschter Nutzer' }}</span>
                                    @if($entry['user']?->email)
                                        <span class="block text-xs text-base-content/55">{{ $entry['user']->email }}</span>
                                    @endif
                                </td>
                                <td class="text-right tabular-nums">{{ $entry['total'] }}</td>
                                <td class="text-right tabular-nums">{{ $entry['zero_results'] }}</td>
                                <td class="text-right tabular-nums">{{ $entry['zero_result_rate'] }}%</td>
                                <td>{{ $entry['last_search_at']?->format('d.m.Y H:i') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-base-content/60">Keine Nutzerstatistik im gewaehlten Zeitraum.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.panel>

        <x-ui.panel title="Letzte Suchanfragen" description="Rohansicht der aktuellen Filterauswahl." data-testid="recent-search-logs">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Zeitpunkt</th>
                            <th>Nutzer</th>
                            <th>Suchbegriff</th>
                            <th>Quelle</th>
                            <th class="text-right">Treffer</th>
                            <th>Filter</th>
                            <th>Sortierung</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->recentSearches as $log)
                            <tr>
                                <td class="whitespace-nowrap">{{ $log->created_at->format('d.m.Y H:i') }}</td>
                                <td>
                                    {{ $log->user?->name ?? 'Geloeschter Nutzer' }}
                                    @if($log->is_admin_search)
                                        <x-badge value="Admin" class="badge-outline badge-xs ml-1" />
                                    @endif
                                </td>
                                <td>
                                    <span class="font-medium">{{ $log->query }}</span>
                                    @if($log->status !== 'ok')
                                        <span class="block text-xs text-warning">{{ $log->status }}</span>
                                    @endif
                                </td>
                                <td>{{ $this->sourceLabel($log->source) }}</td>
                                <td class="text-right tabular-nums">{{ $log->results_count }}</td>
                                <td>
                                    @if(!empty($log->selected_serien))
                                        {{ implode(', ', $log->selected_serien) }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $log->sort }} / {{ $log->direction }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-6 text-center text-base-content/60">Keine Suchlogs im gewaehlten Zeitraum.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($this->recentSearches->hasPages())
                <div class="mt-4 border-t border-base-content/10 pt-4">
                    {{ $this->recentSearches->links() }}
                </div>
            @endif
        </x-ui.panel>

        <x-ui.panel title="Suchlogs zuruecksetzen" description="Loescht alle gespeicherten Suchlogs dauerhaft. Diese Aktion betrifft die komplette Suchstatistik." data-testid="reset-search-logs-panel">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm leading-relaxed text-base-content/70">
                    Einzelne Eintraege koennen nicht geloescht werden. Der Reset entfernt alle Suchlogs gesammelt.
                </p>
                <x-button
                    label="Alle Suchlogs zuruecksetzen"
                    icon="o-trash"
                    class="btn-error"
                    wire:click="resetLogs"
                    wire:confirm="Alle Kompendium-Suchlogs wirklich dauerhaft loeschen?"
                    data-testid="reset-search-logs-button"
                />
            </div>
        </x-ui.panel>
    </div>
</div>
