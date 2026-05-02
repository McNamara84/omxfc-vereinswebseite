@use('App\Enums\TodoStatus')
<x-member-page class="space-y-8">
    @php
        $todoFilterMessages = [
            'all' => 'Zeigt alle verfügbaren Challenges.',
            'assigned' => 'Zeigt deine übernommenen Challenges.',
            'open' => 'Zeigt offene Challenges, die noch übernommen werden können.',
            'pending' => 'Zeigt Challenges, die auf eine Verifizierung warten.',
        ];
        $pendingCount = $this->completedTodos->where('status', TodoStatus::Completed)->count();
        $assignedCount = $this->assignedTodos->count();
        $openCount = $this->unassignedTodos->count();
        $inProgressCount = $this->inProgressTodos->count();
        $dashboard = $this->dashboardMetrics;
        $weeklyProgress = $dashboard['weekly']['progress'] ?? 0;
        $weeklyTarget = $dashboard['weekly']['target'] ?? 0;
        $weeklyTotal = $dashboard['weekly']['total'] ?? 0;
        $teamAverage = $dashboard['team_average'] ?? 0;
        $teamAverageProgress = $dashboard['team_average_progress'] ?? 0;
        $teamAverageRatio = $dashboard['team_average_ratio'] ?? null;
        $userTotalPoints = $this->userPoints;
    @endphp

    <div x-data="{ filter: @entangle('filter'), messages: @js($todoFilterMessages), filterPanelOpen: false }" class="space-y-8">
        <x-ui.page-header
            eyebrow="Mitgliederbereich"
            title="Challenges & Baxx"
            description="Behalte deine Fortschritte, offene Vereinsaufgaben und die wichtigsten Baxx-Signale in einer gemeinsamen Arbeitsfläche im Blick."
        >
            <x-slot:actions>
                <div class="flex flex-col gap-3 lg:items-end">
                    <div class="flex flex-wrap gap-2">
                        <span class="badge badge-outline rounded-full px-3 py-3">{{ $assignedCount }} eigene</span>
                        <span class="badge badge-outline rounded-full px-3 py-3">{{ $openCount }} offen</span>
                        @if($this->canVerifyTodos)
                            <span class="badge badge-outline rounded-full px-3 py-3">{{ $pendingCount }} zu prüfen</span>
                        @endif
                    </div>

                    @if($this->canCreateTodos)
                        <x-button link="{{ route('todos.create') }}" wire:navigate icon="o-plus" class="btn-primary" label="Neue Challenge erstellen" />
                    @endif
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.panel
            eyebrow="Arbeitsmodus"
            title="Challenges filtern"
            description="Wechsle zwischen deinen eigenen Aufgaben, offenen Challenges und dem Verifizierungsstapel. Der Statushinweis passt sich direkt an deine Auswahl an."
        >
            <div class="space-y-4">
                <p role="status" aria-live="polite" class="text-sm leading-relaxed text-base-content/72" data-todo-filter-status x-text="messages[filter] ?? messages.all">
                    {{ $todoFilterMessages[$filter] ?? $todoFilterMessages['all'] }}
                </p>

                <details class="group" data-todo-filter-details x-bind:open="filterPanelOpen">
                    <summary data-todo-filter-summary x-on:click.prevent="filterPanelOpen = !filterPanelOpen" x-bind:aria-expanded="filterPanelOpen.toString()" class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-primary/25 bg-base-100 px-4 py-2 text-sm font-semibold text-primary shadow-sm transition hover:border-primary/45 hover:bg-primary hover:text-primary-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                        <x-icon name="o-funnel" class="h-4 w-4" />
                        <span class="group-open:hidden">Filter anzeigen</span>
                        <span class="hidden group-open:inline">Filter ausblenden</span>
                    </summary>

                    <div class="mt-5 border-t border-base-content/10 pt-5">
                        <nav class="flex flex-wrap gap-2" aria-label="Challenges filtern">
                            <button wire:click="$set('filter', 'all')"
                                class="btn {{ $filter === 'all' ? 'btn-primary font-semibold border border-primary' : 'btn-ghost font-semibold border border-primary/20 text-primary' }}"
                                @if($filter === 'all') aria-current="page" @endif
                            >Alle</button>
                            <button wire:click="$set('filter', 'assigned')"
                                class="btn {{ $filter === 'assigned' ? 'btn-primary font-semibold border border-primary' : 'btn-ghost font-semibold border border-base-content/20' }}"
                                @if($filter === 'assigned') aria-current="page" @endif
                            >Eigene Challenges</button>
                            <button wire:click="$set('filter', 'open')"
                                class="btn {{ $filter === 'open' ? 'btn-primary font-semibold border border-primary' : 'btn-ghost font-semibold border border-base-content/20' }}"
                                @if($filter === 'open') aria-current="page" @endif
                            >Offene Challenges</button>
                            @if($this->canVerifyTodos)
                                <button wire:click="$set('filter', 'pending')"
                                    class="btn {{ $filter === 'pending' ? 'btn-primary font-semibold border border-primary' : 'btn-ghost font-semibold border border-base-content/20' }}"
                                    @if($filter === 'pending') aria-current="page" @endif
                                >Zu verifizieren</button>
                            @endif
                        </nav>
                    </div>
                </details>
            </div>
        </x-ui.panel>

        @if($this->canVerifyTodos && $pendingCount > 0 && in_array($filter, ['all', 'pending']))
            <x-ui.panel
                x-show="filter === 'all' || filter === 'pending'"
                x-cloak
            >
                <x-slot:header>
                    <div class="space-y-2">
                        <h2 id="todo-pending-heading" class="font-display text-2xl font-semibold tracking-tight text-base-content">Zu verifizierende Challenges</h2>
                        <p class="max-w-3xl text-sm leading-relaxed text-base-content/72">Hier warten abgeschlossene Aufgaben auf die finale Prüfung und Baxx-Gutschrift.</p>
                    </div>
                </x-slot:header>

                <div class="overflow-hidden rounded-[1.5rem] border border-base-content/10 bg-base-100/80">
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Titel</th>
                                    <th>Bearbeitet von</th>
                                    <th>Erledigt am</th>
                                    <th>Baxx</th>
                                    <th class="text-center">Aktion</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->completedTodos->where('status', TodoStatus::Completed) as $todo)
                                    <tr wire:key="pending-{{ $todo->id }}">
                                        <td class="font-medium text-base-content">{{ $todo->title }}</td>
                                        <td><a href="{{ route('profile.view', $todo->assignee->id) }}" wire:navigate class="text-primary hover:underline">{{ $todo->assignee->name }}</a></td>
                                        <td>{{ $todo->completed_at->format('d.m.Y H:i') }}</td>
                                        <td><span class="badge badge-outline rounded-full">{{ $todo->points }} Baxx</span></td>
                                        <td class="text-center">
                                            <div class="flex flex-wrap justify-center gap-1">
                                                <x-button label="Details" link="{{ route('todos.show', $todo) }}" wire:navigate class="btn-ghost btn-sm" />
                                                @if($todo->created_by === Auth::id())
                                                    <x-button label="Bearbeiten" link="{{ route('todos.edit', $todo) }}" wire:navigate class="btn-info btn-sm" />
                                                @endif
                                                <x-button label="Verifizieren" wire:click="verify({{ $todo->id }})" class="btn-success btn-sm" wire:loading.attr="disabled" wire:target="verify({{ $todo->id }})" />
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </x-ui.panel>
        @endif

        @if($inProgressCount > 0 && $filter === 'all')
            <x-ui.panel
                x-show="filter === 'all'"
                x-cloak
            >
                <x-slot:header>
                    <div class="space-y-2">
                        <h2 id="todo-progress-heading" class="font-display text-2xl font-semibold tracking-tight text-base-content">In Bearbeitung befindliche Challenges</h2>
                        <p class="max-w-3xl text-sm leading-relaxed text-base-content/72">Diese Aufgaben sind bereits übernommen und geben dir einen schnellen Überblick über laufende Vereinsarbeit.</p>
                    </div>
                </x-slot:header>

                <div class="overflow-hidden rounded-[1.5rem] border border-base-content/10 bg-base-100/80">
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Titel</th>
                                    <th>Kategorie</th>
                                    <th>Bearbeitet von</th>
                                    <th>Baxx</th>
                                    <th class="text-center">Aktion</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->inProgressTodos as $todo)
                                    <tr wire:key="progress-{{ $todo->id }}">
                                        <td class="font-medium text-base-content">{{ $todo->title }}</td>
                                        <td>{{ $todo->category ? $todo->category->name : '-' }}</td>
                                        <td><a href="{{ route('profile.view', $todo->assignee->id) }}" wire:navigate class="text-primary hover:underline">{{ $todo->assignee->name }}</a></td>
                                        <td><span class="badge badge-outline rounded-full">{{ $todo->points }} Baxx</span></td>
                                        <td class="text-center">
                                            <div class="flex flex-wrap justify-center gap-1">
                                                <x-button label="Details" link="{{ route('todos.show', $todo) }}" wire:navigate class="btn-ghost btn-sm" />
                                                @if($todo->created_by === Auth::id())
                                                    <x-button label="Bearbeiten" link="{{ route('todos.edit', $todo) }}" wire:navigate class="btn-info btn-sm" />
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </x-ui.panel>
        @endif

        @if(in_array($filter, ['all', 'assigned']))
            <x-ui.panel
                x-show="filter === 'all' || filter === 'assigned'"
                x-cloak
                data-todo-section="assigned"
            >
                <x-slot:header>
                    <div class="space-y-2">
                        <h2 id="todo-assigned-heading" class="font-display text-2xl font-semibold tracking-tight text-base-content">Deine Challenges</h2>
                        <p class="max-w-3xl text-sm leading-relaxed text-base-content/72">Alles, was du gerade bearbeitest, bereits abgeschlossen hast oder zur Verifizierung vorbereitet ist.</p>
                    </div>
                </x-slot:header>

                @if($this->assignedTodos->isEmpty())
                    <div class="rounded-[1.5rem] border border-dashed border-base-content/15 bg-base-100/65 px-5 py-6 text-sm leading-relaxed text-base-content/68">
                        Du hast aktuell keine übernommenen Challenges.
                    </div>
                @else
                    <div class="overflow-hidden rounded-[1.5rem] border border-base-content/10 bg-base-100/80">
                        <div class="overflow-x-auto">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Titel</th>
                                        <th>Status</th>
                                        <th>Baxx</th>
                                        <th class="text-center">Aktion</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->assignedTodos as $todo)
                                        <tr wire:key="assigned-{{ $todo->id }}">
                                            <td class="font-medium text-base-content">{{ $todo->title }}</td>
                                            <td>
                                                @if($todo->status->value === 'assigned')
                                                    <x-badge value="In Bearbeitung" class="badge-info" icon="o-arrow-path" />
                                                @elseif($todo->status->value === 'completed')
                                                    <x-badge value="Wartet auf Verifizierung" class="badge-warning" icon="o-eye" />
                                                @elseif($todo->status->value === 'verified')
                                                    <x-badge value="Verifiziert" class="badge-success" icon="o-check-circle" />
                                                @endif
                                            </td>
                                            <td><span class="badge badge-outline rounded-full">{{ $todo->points }} Baxx</span></td>
                                            <td class="text-center">
                                                <div class="flex flex-wrap justify-center gap-1">
                                                    <x-button label="Details" link="{{ route('todos.show', $todo) }}" wire:navigate class="btn-ghost btn-sm" />
                                                    @if($todo->created_by === Auth::id())
                                                        <x-button label="Bearbeiten" link="{{ route('todos.edit', $todo) }}" wire:navigate class="btn-info btn-sm" />
                                                    @endif
                                                    @if($todo->status->value === 'assigned')
                                                        <x-button label="Als erledigt markieren" wire:click="complete({{ $todo->id }})" class="btn-success btn-sm" wire:loading.attr="disabled" wire:target="complete({{ $todo->id }})" />
                                                        <x-button label="Freigeben" wire:click="release({{ $todo->id }})" class="btn-error btn-sm" wire:loading.attr="disabled" wire:target="release({{ $todo->id }})" />
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </x-ui.panel>
        @endif

        @if(in_array($filter, ['all', 'open']))
            <x-ui.panel
                x-show="filter === 'all' || filter === 'open'"
                x-cloak
                data-todo-section="open"
            >
                <x-slot:header>
                    <div class="space-y-2">
                        <h2 id="todo-open-heading" class="font-display text-2xl font-semibold tracking-tight text-base-content">Offene Challenges</h2>
                        <p class="max-w-3xl text-sm leading-relaxed text-base-content/72">Alle aktuell verfügbaren Aufgaben, die noch übernommen werden können.</p>
                    </div>
                </x-slot:header>

                @if($this->unassignedTodos->isEmpty())
                    <div class="rounded-[1.5rem] border border-dashed border-base-content/15 bg-base-100/65 px-5 py-6 text-sm leading-relaxed text-base-content/68">
                        Es sind aktuell keine offenen Challenges verfügbar.
                    </div>
                @else
                    <div class="overflow-hidden rounded-[1.5rem] border border-base-content/10 bg-base-100/80">
                        <div class="overflow-x-auto">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Titel</th>
                                        <th>Kategorie</th>
                                        <th>Erstellt von</th>
                                        <th>Baxx</th>
                                        <th class="text-center">Aktion</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->unassignedTodos as $todo)
                                        <tr wire:key="open-{{ $todo->id }}">
                                            <td class="font-medium text-base-content">{{ $todo->title }}</td>
                                            <td>{{ $todo->category ? $todo->category->name : '-' }}</td>
                                            <td><a href="{{ route('profile.view', $todo->creator->id) }}" wire:navigate class="text-primary hover:underline">{{ $todo->creator->name }}</a></td>
                                            <td><span class="badge badge-outline rounded-full">{{ $todo->points }} Baxx</span></td>
                                            <td class="text-center">
                                                <div class="flex flex-wrap justify-center gap-1">
                                                    <x-button label="Details" link="{{ route('todos.show', $todo) }}" wire:navigate class="btn-ghost btn-sm" />
                                                    @if($todo->created_by === Auth::id())
                                                        <x-button label="Bearbeiten" link="{{ route('todos.edit', $todo) }}" wire:navigate class="btn-info btn-sm" />
                                                    @endif
                                                    <x-button label="Übernehmen" wire:click="assign({{ $todo->id }})" class="btn-info btn-sm" wire:loading.attr="disabled" wire:target="assign({{ $todo->id }})" />
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </x-ui.panel>
        @endif

        <x-ui.panel>
            <x-slot:header>
                <div class="space-y-2">
                    <h2 id="todo-dashboard-heading" class="font-display text-2xl font-semibold tracking-tight text-base-content">Vereins-Dashboard</h2>
                    <p class="max-w-3xl text-sm leading-relaxed text-base-content/72">Fortschritt, Vergleich und Ziele deines Vereins auf einen Blick.</p>
                </div>
            </x-slot:header>

            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4" data-todo-dashboard>
                <article class="rounded-[1.5rem] border border-base-content/10 bg-base-100/78 p-5 shadow-sm shadow-base-content/5 md:col-span-2 xl:col-span-1" aria-labelledby="weekly-goal-heading">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 id="weekly-goal-heading" class="text-lg font-semibold text-primary">Wochenziel</h3>
                            <p class="mt-1 text-sm text-base-content/68">Sammle kontinuierlich Baxx, um dein Wochenziel zu erreichen.</p>
                        </div>
                        <div class="flex flex-col items-end text-right gap-1">
                            <span class="text-2xl font-bold leading-none tracking-tight text-base-content sm:text-3xl xl:text-4xl">{{ number_format($weeklyTotal, 0, ',', '.') }}</span>
                            <p class="text-xs text-base-content/55">von {{ number_format($weeklyTarget, 0, ',', '.') }} Baxx</p>
                        </div>
                    </div>
                    <div class="mt-4" data-progress-bar data-progress-value="{{ $weeklyTotal }}" data-progress-max="{{ max($weeklyTarget, 1) }}" data-progress-label="Fortschritt Richtung Wochenziel">
                        <div class="h-2 w-full overflow-hidden rounded-full bg-base-200" aria-hidden="true">
                            <div data-progress-fill class="h-full bg-primary" style="width: {{ $weeklyProgress }}%"></div>
                        </div>
                        <p class="mt-2 text-sm text-base-content/68">{{ number_format($weeklyTotal, 0, ',', '.') }} von {{ number_format($weeklyTarget, 0, ',', '.') }} Baxx gesammelt.</p>
                    </div>
                </article>

                <article class="rounded-[1.5rem] border border-base-content/10 bg-base-100/78 p-5 shadow-sm shadow-base-content/5" aria-labelledby="team-average-heading">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 id="team-average-heading" class="text-lg font-semibold text-primary">Vereinsdurchschnitt</h3>
                            <p class="mt-1 text-sm text-base-content/68">Vergleiche deine Punkte mit dem Durchschnitt des Vereins.</p>
                        </div>
                        <div class="flex flex-col items-end text-right gap-1">
                            <span class="text-2xl font-bold leading-none tracking-tight text-base-content sm:text-3xl xl:text-4xl">{{ number_format($teamAverage, 1, ',', '.') }}</span>
                            <p class="text-xs text-base-content/55">Ø Vereins-Baxx</p>
                        </div>
                    </div>
                    <div class="mt-4" data-progress-bar data-progress-value="{{ $userTotalPoints }}" data-progress-max="{{ max($teamAverage, 1) }}" data-progress-label="Vergleich zum Vereinsdurchschnitt">
                        <div class="h-2 w-full overflow-hidden rounded-full bg-base-200" aria-hidden="true">
                            <div data-progress-fill class="h-full bg-secondary" style="width: {{ $teamAverageProgress }}%"></div>
                        </div>
                        <p class="mt-2 text-sm text-base-content/68">
                            @if(! is_null($teamAverageRatio))
                                Du liegst bei {{ number_format($teamAverageRatio, 1, ',', '.') }} % des Vereinsdurchschnitts.
                            @else
                                Sobald Vereinsmitglieder Punkte gesammelt haben, erscheint hier der Vergleich.
                            @endif
                        </p>
                    </div>
                </article>

                <article class="rounded-[1.5rem] border border-base-content/10 bg-base-100/78 p-5 shadow-sm shadow-base-content/5" aria-labelledby="personal-points-heading">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 id="personal-points-heading" class="text-lg font-semibold text-primary">Dein Punktestand</h3>
                            <p class="mt-1 text-sm text-base-content/68">Alle gesammelten Baxx deines Vereinskontos.</p>
                        </div>
                        <div class="flex flex-col items-end text-right gap-1">
                            <span class="text-2xl font-bold leading-none tracking-tight text-base-content sm:text-3xl xl:text-4xl">{{ number_format($userTotalPoints, 0, ',', '.') }}</span>
                            <p class="text-xs text-base-content/55">gesammelte Baxx</p>
                        </div>
                    </div>
                    @if($teamAverage > 0)
                        <p class="mt-4 text-sm text-base-content/68">
                            Du liegst {{ $userTotalPoints >= $teamAverage ? 'über' : 'unter' }} dem Vereinsdurchschnitt von {{ number_format($teamAverage, 1, ',', '.') }} Baxx.
                        </p>
                    @else
                        <p class="mt-4 text-sm text-base-content/68">
                            Sobald Baxx gesammelt wurden, erscheint hier dein Vergleich zum Verein.
                        </p>
                    @endif
                </article>

                <article class="rounded-[1.5rem] border border-base-content/10 bg-base-100/78 p-5 shadow-sm shadow-base-content/5 md:col-span-2 xl:col-span-1" aria-labelledby="leaderboard-heading">
                    <h3 id="leaderboard-heading" class="text-lg font-semibold text-primary">Rangliste</h3>
                    <p class="mt-1 text-sm text-base-content/68">So steht dein Verein aktuell da.</p>
                    <ol class="mt-4 space-y-3" role="list">
                        @forelse($dashboard['leaderboard'] as $entry)
                            <li class="flex items-center justify-between gap-3 rounded-[1rem] border border-base-content/10 px-3 py-2 @if($entry['is_current_user']) bg-primary text-primary-content @else bg-base-200/75 text-base-content @endif">
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-semibold @if($entry['is_current_user']) text-primary-content @else text-base-content @endif">{{ $entry['rank'] ? '#' . $entry['rank'] : '–' }}</span>
                                    <span class="text-sm font-semibold">{{ $entry['name'] }}</span>
                                </div>
                                <span class="text-sm font-semibold">{{ number_format($entry['points'], 0, ',', '.') }} Baxx</span>
                            </li>
                        @empty
                            <li class="text-sm text-base-content/68">Sobald Punkte gesammelt wurden, erscheint hier die Rangliste.</li>
                        @endforelse
                    </ol>
                </article>
            </div>
        </x-ui.panel>
    </div>
</x-member-page>
