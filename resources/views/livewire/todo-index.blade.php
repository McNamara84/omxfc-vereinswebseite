@use('App\Enums\TodoStatus')
<x-member-page>
    <div x-data="{
        filter: @entangle('filter'),
        messages: {
            all: 'Zeigt alle verfügbaren Challenges.',
            assigned: 'Zeigt deine übernommenen Challenges.',
            open: 'Zeigt offene Challenges, die noch übernommen werden können.',
            pending: 'Zeigt Challenges, die auf eine Verifizierung warten.',
        }
    }">
        <x-header title="Challenges & Baxx" subtitle="Behalte deine Fortschritte und die Ziele des Vereins im Blick." separator class="mb-6">
            @if($this->canCreateTodos)
                <x-slot:actions>
                    <x-button link="{{ route('todos.create') }}" wire:navigate icon="o-plus" class="btn-primary" label="Neue Challenge erstellen" />
                </x-slot:actions>
            @endif
        </x-header>

        {{-- Filter-Karte --}}
        <x-card shadow class="mb-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="space-y-1">
                    <h2 class="text-xl font-semibold text-primary">Challenges filtern</h2>
                    <p role="status" aria-live="polite" class="text-sm text-base-content" data-todo-filter-status
                        x-text="messages[filter] ?? messages.all">
                        @php
                            $todoFilterMessages = [
                                'all' => 'Zeigt alle verfügbaren Challenges.',
                                'assigned' => 'Zeigt deine übernommenen Challenges.',
                                'open' => 'Zeigt offene Challenges, die noch übernommen werden können.',
                                'pending' => 'Zeigt Challenges, die auf eine Verifizierung warten.',
                            ];
                        @endphp
                        {{ $todoFilterMessages[$filter] ?? $todoFilterMessages['all'] }}
                    </p>
                </div>
            </div>
            <details class="group mt-4" data-todo-filter-details>
                <summary data-todo-filter-summary class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-primary bg-white px-4 py-2 text-sm font-semibold text-primary shadow-sm transition hover:bg-primary hover:text-primary-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary dark:bg-base-200">
                    <x-icon name="o-funnel" class="h-4 w-4" />
                    <span class="group-open:hidden">Filter anzeigen</span>
                    <span class="hidden group-open:inline">Filter ausblenden</span>
                </summary>
                <div class="mt-6 border-t border-base-content/10 pt-6">
                    <nav class="flex flex-wrap gap-2" aria-label="Challenges filtern">
                        <button wire:click="$set('filter', 'all')"
                            class="btn {{ $filter === 'all' ? 'btn-primary font-semibold border border-primary' : 'btn-ghost font-semibold border border-primary text-primary' }}"
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
        </x-card>

        {{-- Zu verifizierende Challenges --}}
        @if($this->canVerifyTodos && $this->completedTodos->where('status', TodoStatus::Completed)->isNotEmpty() && in_array($filter, ['all', 'pending']))
            <x-card shadow class="mb-6" x-show="filter === 'all' || filter === 'pending'" aria-labelledby="todo-pending-heading">
                <h2 id="todo-pending-heading" class="text-xl font-semibold text-primary mb-4">Zu verifizierende Challenges</h2>
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
                                    <td>{{ $todo->title }}</td>
                                    <td><a href="{{ route('profile.view', $todo->assignee->id) }}" wire:navigate class="text-primary hover:underline">{{ $todo->assignee->name }}</a></td>
                                    <td>{{ $todo->completed_at->format('d.m.Y H:i') }}</td>
                                    <td>{{ $todo->points }}</td>
                                    <td class="text-center">
                                        <div class="flex flex-wrap justify-center gap-1">
                                            <x-button label="Details" link="{{ route('todos.show', $todo) }}" wire:navigate class="btn-ghost btn-sm" />
                                            @if($todo->created_by === Auth::id())
                                                <x-button label="Bearbeiten" link="{{ route('todos.edit', $todo) }}" wire:navigate class="btn-info btn-sm" />
                                            @endif
                                            <x-button label="Verifizieren" wire:click="verify({{ $todo->id }})" class="btn-success btn-sm"
                                                wire:loading.attr="disabled" wire:target="verify({{ $todo->id }})" />
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endif

        {{-- In Bearbeitung befindliche Challenges (andere Nutzer) --}}
        @if($this->inProgressTodos->isNotEmpty() && $filter === 'all')
            <x-card shadow class="mb-6" x-show="filter === 'all'" aria-labelledby="todo-progress-heading">
                <h2 id="todo-progress-heading" class="text-xl font-semibold text-primary mb-4">In Bearbeitung befindliche Challenges</h2>
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
                                    <td>{{ $todo->title }}</td>
                                    <td>{{ $todo->category ? $todo->category->name : '-' }}</td>
                                    <td><a href="{{ route('profile.view', $todo->assignee->id) }}" wire:navigate class="text-primary hover:underline">{{ $todo->assignee->name }}</a></td>
                                    <td>{{ $todo->points }}</td>
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
            </x-card>
        @endif

        {{-- Deine Challenges --}}
        @if(in_array($filter, ['all', 'assigned']))
            <x-card shadow class="mb-6" x-show="filter === 'all' || filter === 'assigned'" aria-labelledby="todo-assigned-heading" data-todo-section="assigned">
                <h2 id="todo-assigned-heading" class="text-xl font-semibold text-primary mb-4">Deine Challenges</h2>
                @if($this->assignedTodos->isEmpty())
                    <p class="text-base-content">Du hast aktuell keine übernommenen Challenges.</p>
                @else
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
                                        <td>{{ $todo->title }}</td>
                                        <td>
                                            @if($todo->status->value === 'assigned')
                                                <x-badge value="In Bearbeitung" class="badge-info" icon="o-arrow-path" />
                                            @elseif($todo->status->value === 'completed')
                                                <x-badge value="Wartet auf Verifizierung" class="badge-warning" icon="o-eye" />
                                            @elseif($todo->status->value === 'verified')
                                                <x-badge value="Verifiziert" class="badge-success" icon="o-check-circle" />
                                            @endif
                                        </td>
                                        <td>{{ $todo->points }}</td>
                                        <td class="text-center">
                                            <div class="flex flex-wrap justify-center gap-1">
                                                <x-button label="Details" link="{{ route('todos.show', $todo) }}" wire:navigate class="btn-ghost btn-sm" />
                                                @if($todo->created_by === Auth::id())
                                                    <x-button label="Bearbeiten" link="{{ route('todos.edit', $todo) }}" wire:navigate class="btn-info btn-sm" />
                                                @endif
                                                @if($todo->status->value === 'assigned')
                                                    <x-button label="Als erledigt markieren" wire:click="complete({{ $todo->id }})" class="btn-success btn-sm"
                                                        wire:loading.attr="disabled" wire:target="complete({{ $todo->id }})" />
                                                    <x-button label="Freigeben" wire:click="release({{ $todo->id }})" class="btn-error btn-sm"
                                                        wire:loading.attr="disabled" wire:target="release({{ $todo->id }})" />
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        @endif

        {{-- Offene Challenges --}}
        @if(in_array($filter, ['all', 'open']))
            <x-card shadow class="mb-6" x-show="filter === 'all' || filter === 'open'" aria-labelledby="todo-open-heading" data-todo-section="open">
                <h2 id="todo-open-heading" class="text-xl font-semibold text-primary mb-4">Offene Challenges</h2>
                @if($this->unassignedTodos->isEmpty())
                    <p class="text-base-content">Es sind aktuell keine offenen Challenges verfügbar.</p>
                @else
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
                                        <td>{{ $todo->title }}</td>
                                        <td>{{ $todo->category ? $todo->category->name : '-' }}</td>
                                        <td><a href="{{ route('profile.view', $todo->creator->id) }}" wire:navigate class="text-primary hover:underline">{{ $todo->creator->name }}</a></td>
                                        <td>{{ $todo->points }}</td>
                                        <td class="text-center">
                                            <div class="flex flex-wrap justify-center gap-1">
                                                <x-button label="Details" link="{{ route('todos.show', $todo) }}" wire:navigate class="btn-ghost btn-sm" />
                                                @if($todo->created_by === Auth::id())
                                                    <x-button label="Bearbeiten" link="{{ route('todos.edit', $todo) }}" wire:navigate class="btn-info btn-sm" />
                                                @endif
                                                <x-button label="Übernehmen" wire:click="assign({{ $todo->id }})" class="btn-info btn-sm"
                                                    wire:loading.attr="disabled" wire:target="assign({{ $todo->id }})" />
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        @endif

        {{-- Dashboard --}}
        @php
            $dashboard = $this->dashboardMetrics;
            $weeklyProgress = $dashboard['weekly']['progress'] ?? 0;
            $weeklyTarget = $dashboard['weekly']['target'] ?? 0;
            $weeklyTotal = $dashboard['weekly']['total'] ?? 0;
            $teamAverage = $dashboard['team_average'] ?? 0;
            $teamAverageProgress = $dashboard['team_average_progress'] ?? 0;
            $teamAverageRatio = $dashboard['team_average_ratio'] ?? null;
            $trendMax = max($dashboard['trend_max'] ?? 0, 1);
            $userRank = $dashboard['user_rank'] ?? null;
            $pointsToNext = $dashboard['points_to_next_rank'] ?? null;
            $userTotalPoints = $this->userPoints;
        @endphp
        <x-card shadow class="mb-6" aria-labelledby="todo-dashboard-heading">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 id="todo-dashboard-heading" class="text-xl font-semibold text-base-content">Vereins-Dashboard</h2>
                    <p class="text-sm text-base-content">Fortschritt, Vergleich und Ziele deines Vereins auf einen Blick.</p>
                </div>
            </div>
            <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-4" data-todo-dashboard>
                {{-- Wochenziel --}}
                <x-card class="bg-base-200 md:col-span-2 xl:col-span-1" aria-labelledby="weekly-goal-heading">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 id="weekly-goal-heading" class="text-lg font-semibold text-primary">Wochenziel</h3>
                            <p class="mt-1 text-sm text-base-content">Sammle kontinuierlich Baxx, um dein Wochenziel zu erreichen.</p>
                        </div>
                        <div class="flex flex-col items-end text-right gap-1">
                            <span class="text-2xl font-bold leading-none tracking-tight text-base-content sm:text-3xl xl:text-4xl">
                                {{ number_format($weeklyTotal, 0, ',', '.') }}
                            </span>
                            <p class="text-xs text-base-content">von {{ number_format($weeklyTarget, 0, ',', '.') }} Baxx</p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="h-2 w-full bg-base-100 rounded-full overflow-hidden" aria-hidden="true">
                            <div class="h-full bg-primary" style="width: {{ $weeklyProgress }}%"></div>
                        </div>
                        <p class="mt-2 text-sm text-base-content">
                            {{ number_format($weeklyTotal, 0, ',', '.') }} von {{ number_format($weeklyTarget, 0, ',', '.') }} Baxx gesammelt.
                        </p>
                    </div>
                </x-card>

                {{-- Vereinsdurchschnitt --}}
                <article class="rounded-lg border border-base-content/10 p-5 bg-base-200" aria-labelledby="team-average-heading">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 id="team-average-heading" class="text-lg font-semibold text-primary">Vereinsdurchschnitt</h3>
                            <p class="mt-1 text-sm text-base-content">Vergleiche deine Punkte mit dem Durchschnitt des Vereins.</p>
                        </div>
                        <div class="flex flex-col items-end text-right gap-1">
                            <span class="text-2xl font-bold leading-none tracking-tight text-base-content sm:text-3xl xl:text-4xl">
                                {{ number_format($teamAverage, 1, ',', '.') }}
                            </span>
                            <p class="text-xs text-base-content">Ø Vereins-Baxx</p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="h-2 w-full bg-base-100 rounded-full overflow-hidden" aria-hidden="true">
                            <div class="h-full bg-secondary" style="width: {{ $teamAverageProgress }}%"></div>
                        </div>
                        <p class="mt-2 text-sm text-base-content">
                            @if(! is_null($teamAverageRatio))
                                Du liegst bei {{ number_format($teamAverageRatio, 1, ',', '.') }} % des Vereinsdurchschnitts.
                            @else
                                Sobald Vereinsmitglieder Punkte gesammelt haben, erscheint hier der Vergleich.
                            @endif
                        </p>
                    </div>
                </article>

                {{-- Punktestand --}}
                <x-card class="bg-base-200" aria-labelledby="personal-points-heading">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 id="personal-points-heading" class="text-lg font-semibold text-primary">Dein Punktestand</h3>
                            <p class="mt-1 text-sm text-base-content">Alle gesammelten Baxx deines Vereinskontos.</p>
                        </div>
                        <div class="flex flex-col items-end text-right gap-1">
                            <span class="text-2xl font-bold leading-none tracking-tight text-base-content sm:text-3xl xl:text-4xl">
                                {{ number_format($userTotalPoints, 0, ',', '.') }}
                            </span>
                            <p class="text-xs text-base-content">gesammelte Baxx</p>
                        </div>
                    </div>
                    @if($teamAverage > 0)
                        <p class="mt-4 text-sm text-base-content">
                            Du liegst {{ $userTotalPoints >= $teamAverage ? 'über' : 'unter' }} dem Vereinsdurchschnitt
                            von {{ number_format($teamAverage, 1, ',', '.') }} Baxx.
                        </p>
                    @else
                        <p class="mt-4 text-sm text-base-content">
                            Sobald Baxx gesammelt wurden, erscheint hier dein Vergleich zum Verein.
                        </p>
                    @endif
                </x-card>

                {{-- Rangliste --}}
                <x-card class="bg-base-200 md:col-span-2 xl:col-span-1" aria-labelledby="leaderboard-heading">
                    <h3 id="leaderboard-heading" class="text-lg font-semibold text-primary">Rangliste</h3>
                    <p class="mt-1 text-sm text-base-content">So steht dein Verein aktuell da.</p>
                    <ol class="mt-4 space-y-3" role="list">
                        @forelse($dashboard['leaderboard'] as $entry)
                            <li class="flex items-center justify-between gap-3 rounded-md px-3 py-2 @if($entry['is_current_user']) bg-primary text-primary-content @else bg-base-100 text-base-content @endif border border-base-content/10">
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-semibold @if($entry['is_current_user']) text-primary-content @else text-base-content @endif">
                                        {{ $entry['rank'] ? '#' . $entry['rank'] : '–' }}
                                    </span>
                                    <span class="text-sm font-semibold">{{ $entry['name'] }}</span>
                                </div>
                                <span class="text-sm font-semibold">{{ number_format($entry['points'], 0, ',', '.') }} Baxx</span>
                            </li>
                        @empty
                            <li class="text-sm text-base-content">
                                Sobald Punkte gesammelt wurden, erscheint hier die Rangliste.
                            </li>
                        @endforelse
                    </ol>
                </x-card>
            </div>
        </x-card>
    </div>
</x-member-page>
