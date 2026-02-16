<x-app-layout>
    <x-member-page>
            @if(session('status'))
                <x-alert class="alert-success mb-4" role="status" aria-live="polite">
                    {{ session('status') }}
                </x-alert>
            @endif
            @if(session('error'))
                <x-alert class="alert-error mb-4" role="alert">
                    {{ session('error') }}
                </x-alert>
            @endif
            @php
                $currentFilter = $activeFilter ?? 'all';
            @endphp
            <x-header title="Challenges & Baxx" subtitle="Behalte deine Fortschritte und die Ziele des Vereins im Blick." separator class="mb-6">
                @if($canCreateTodos)
                    <x-slot:actions>
                        <x-button link="{{ route('todos.create') }}" icon="o-plus" class="btn-primary" label="Neue Challenge erstellen" />
                    </x-slot:actions>
                @endif
            </x-header>
            <x-card shadow class="mb-6" data-todo-filter-wrapper>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="space-y-1">
                        <h2 class="text-xl font-semibold text-primary">Challenges filtern</h2>
                        <p id="todo-filter-status" data-todo-filter-status role="status" aria-live="polite"
                            class="text-sm text-base-content">
                            {{ $currentFilter === 'pending' ? 'Zeigt Challenges, die auf eine Verifizierung warten.' : 'Zeigt alle verfügbaren Challenges.' }}
                        </p>
                    </div>
                </div>
                <details class="group mt-4" data-todo-filter-details>
                    <summary data-todo-filter-summary
                        class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-primary bg-white px-4 py-2 text-sm font-semibold text-primary shadow-sm transition hover:bg-primary hover:text-primary-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary  dark:bg-base-200    ">
                        <x-icon name="o-funnel" class="h-4 w-4" />
                        <span class="group-open:hidden">Filter anzeigen</span>
                        <span class="hidden group-open:inline">Filter ausblenden</span>
                    </summary>
                    <div id="todo-filter-panel" class="mt-6 border-t border-base-content/10 pt-6">
                        <form method="GET" action="{{ route('todos.index') }}" data-todo-filter-form
                            data-current-filter="{{ $currentFilter }}">
                            <fieldset class="space-y-4">
                                <legend class="sr-only">Challenges filtern</legend>
                                <div class="flex flex-wrap gap-2" role="group" aria-label="Challenges filtern">
                                    <x-button
                                        type="submit"
                                        name="filter"
                                        value=""
                                        label="Alle"
                                        data-todo-filter
                                        data-filter="all"
                                        data-active="{{ $currentFilter === 'all' ? 'true' : 'false' }}"
                                        class="font-semibold border border-primary data-[active=true]:bg-primary data-[active=true]:text-primary-content {{ $currentFilter === 'all' ? 'btn-primary' : 'btn-ghost text-primary' }}"
                                    />
                                    <x-button
                                        type="button"
                                        label="Eigene Challenges"
                                        data-todo-filter
                                        data-filter="assigned"
                                        data-active="false"
                                        class="font-semibold border border-base-content/20 btn-ghost data-[active=true]:bg-primary data-[active=true]:text-primary-content"
                                    />
                                    <x-button
                                        type="button"
                                        label="Offene Challenges"
                                        data-todo-filter
                                        data-filter="open"
                                        data-active="false"
                                        class="font-semibold border border-base-content/20 btn-ghost data-[active=true]:bg-primary data-[active=true]:text-primary-content"
                                    />
                                    @if($canVerifyTodos)
                                        <x-button
                                            type="submit"
                                            name="filter"
                                            value="pending"
                                            label="Zu verifizieren"
                                            data-todo-filter
                                            data-filter="pending"
                                            data-active="{{ $currentFilter === 'pending' ? 'true' : 'false' }}"
                                            class="font-semibold border border-base-content/20 btn-ghost data-[active=true]:bg-primary data-[active=true]:text-primary-content"
                                        />
                                    @endif
                                </div>
                                <noscript>
                                    <p class="text-xs text-base-content">
                                        Für weitere Filteroptionen aktiviere JavaScript in deinem Browser.
                                    </p>
                                </noscript>
                            </fieldset>
                        </form>
                    </div>
                </details>
            </x-card>

            <!-- Zu verifizierende Challenges (nur wenn Verifizierungsrechte vorhanden) -->
            @if($canVerifyTodos && $completedTodos->where('status', 'completed')->isNotEmpty())
                <x-card shadow class="mb-6" data-todo-section="pending" aria-labelledby="todo-pending-heading">
                    <h2 id="todo-pending-heading"
                        class="text-xl font-semibold text-primary mb-4">Zu verifizierende Challenges
                    </h2>
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
                                @foreach($completedTodos->where('status', 'completed') as $todo)
                                    <tr>
                                        <td>{{ $todo->title }}</td>
                                        <td><a href="{{ route('profile.view', $todo->assignee->id) }}" class="text-primary hover:underline">{{ $todo->assignee->name }}</a></td>
                                        <td>{{ $todo->completed_at->format('d.m.Y H:i') }}</td>
                                        <td>{{ $todo->points }}</td>
                                        <td class="text-center">
                                            <div class="flex flex-wrap justify-center gap-1">
                                                <x-button label="Details" link="{{ route('todos.show', $todo) }}" class="btn-ghost btn-sm" />
                                                @if($todo->created_by === Auth::id())
                                                    <x-button label="Bearbeiten" link="{{ route('todos.edit', $todo) }}" class="btn-info btn-sm" />
                                                @endif
                                                <form action="{{ route('todos.verify', $todo) }}" method="POST" class="inline-block">
                                                    @csrf
                                                    <x-button label="Verifizieren" type="submit" class="btn-success btn-sm" />
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif

            <!-- In Bearbeitung befindliche Challenges (andere Nutzer) - nur anzeigen, wenn es welche gibt -->
            @php
                $inProgressTodos = $todos->where('status', 'assigned')->where('assigned_to', '!=', Auth::id());
            @endphp
            @if($inProgressTodos->isNotEmpty())
                <x-card shadow class="mb-6" data-todo-section="in-progress" aria-labelledby="todo-progress-heading">
                    <h2 id="todo-progress-heading"
                        class="text-xl font-semibold text-primary mb-4">In Bearbeitung befindliche Challenges</h2>
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
                                @foreach($inProgressTodos as $todo)
                                    <tr>
                                        <td>{{ $todo->title }}</td>
                                        <td>{{ $todo->category ? $todo->category->name : '-' }}</td>
                                        <td><a href="{{ route('profile.view', $todo->assignee->id) }}" class="text-primary hover:underline">{{ $todo->assignee->name }}</a></td>
                                        <td>{{ $todo->points }}</td>
                                        <td class="text-center">
                                            <div class="flex flex-wrap justify-center gap-1">
                                                <x-button label="Details" link="{{ route('todos.show', $todo) }}" class="btn-ghost btn-sm" />
                                                @if($todo->created_by === Auth::id())
                                                    <x-button label="Bearbeiten" link="{{ route('todos.edit', $todo) }}" class="btn-info btn-sm" />
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
            <!-- Deine Challenges -->
            <x-card shadow class="mb-6" data-todo-section="assigned" aria-labelledby="todo-assigned-heading">
                <h2 id="todo-assigned-heading"
                    class="text-xl font-semibold text-primary mb-4">Deine Challenges</h2>
                @if($assignedTodos->isEmpty())
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
                                @foreach($assignedTodos as $todo)
                                    <tr>
                                        <td>{{ $todo->title }}</td>
                                        <td>
                                            @if($todo->status->value === 'assigned')
                                                <x-badge value="In Bearbeitung" class="badge-info" />
                                            @elseif($todo->status->value === 'completed')
                                                <x-badge value="Wartet auf Verifizierung" class="badge-warning" />
                                            @elseif($todo->status->value === 'verified')
                                                <x-badge value="Verifiziert" class="badge-success" />
                                            @endif
                                        </td>
                                        <td>{{ $todo->points }}</td>
                                        <td class="text-center">
                                            <div class="flex flex-wrap justify-center gap-1">
                                                <x-button label="Details" link="{{ route('todos.show', $todo) }}" class="btn-ghost btn-sm" />
                                                @if($todo->created_by === Auth::id())
                                                    <x-button label="Bearbeiten" link="{{ route('todos.edit', $todo) }}" class="btn-info btn-sm" />
                                                @endif
                                                @if($todo->status->value === 'assigned')
                                                    <form action="{{ route('todos.complete', $todo) }}" method="POST" class="inline-block">
                                                        @csrf
                                                        <x-button label="Als erledigt markieren" type="submit" class="btn-success btn-sm" />
                                                    </form>
                                                    <form action="{{ route('todos.release', $todo) }}" method="POST" class="inline-block">
                                                        @csrf
                                                        <x-button label="Freigeben" type="submit" class="btn-error btn-sm" />
                                                    </form>
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
            <!-- Offene Challenges -->
            <x-card shadow class="mb-6" data-todo-section="open" aria-labelledby="todo-open-heading">
                <h2 id="todo-open-heading"
                    class="text-xl font-semibold text-primary mb-4">Offene Challenges</h2>
                @if($unassignedTodos->isEmpty())
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
                                @foreach($unassignedTodos as $todo)
                                    <tr>
                                        <td>{{ $todo->title }}</td>
                                        <td>{{ $todo->category ? $todo->category->name : '-' }}</td>
                                        <td><a href="{{ route('profile.view', $todo->creator->id) }}" class="text-primary hover:underline">{{ $todo->creator->name }}</a></td>
                                        <td>{{ $todo->points }}</td>
                                        <td class="text-center">
                                            <div class="flex flex-wrap justify-center gap-1">
                                                <x-button label="Details" link="{{ route('todos.show', $todo) }}" class="btn-ghost btn-sm" />
                                                @if($todo->created_by === Auth::id())
                                                    <x-button label="Bearbeiten" link="{{ route('todos.edit', $todo) }}" class="btn-info btn-sm" />
                                                @endif
                                                <form action="{{ route('todos.assign', $todo) }}" method="POST" class="inline-block">
                                                    @csrf
                                                    <x-button label="Übernehmen" type="submit" class="btn-info btn-sm" />
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>

            @php
                $dashboard = $dashboardMetrics ?? [
                    'trend' => [],
                    'trend_max' => 0,
                    'weekly' => ['progress' => 0, 'target' => 0, 'total' => 0],
                    'team_average' => 0,
                    'team_average_progress' => 0,
                    'team_average_ratio' => null,
                    'leaderboard' => [],
                    'user_rank' => null,
                    'points_to_next_rank' => null,
                    'next_rank_points' => null,
                    'user_points' => $userPoints,
                ];
                $weeklyProgress = $dashboard['weekly']['progress'] ?? 0;
                $weeklyTarget = $dashboard['weekly']['target'] ?? 0;
                $weeklyTotal = $dashboard['weekly']['total'] ?? 0;
                $teamAverage = $dashboard['team_average'] ?? 0;
                $teamAverageProgress = $dashboard['team_average_progress'] ?? 0;
                $teamAverageRatio = $dashboard['team_average_ratio'] ?? null;
                $trendMax = max($dashboard['trend_max'] ?? 0, 1);
                $userRank = $dashboard['user_rank'] ?? null;
                $pointsToNext = $dashboard['points_to_next_rank'] ?? null;
                $userTotalPoints = $dashboard['user_points'] ?? $userPoints;
            @endphp
            <x-card shadow class="mb-6" aria-labelledby="todo-dashboard-heading">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 id="todo-dashboard-heading"
                            class="text-xl font-semibold text-base-content">Vereins-Dashboard</h2>
                        <p class="text-sm text-base-content">
                            Fortschritt, Vergleich und Ziele deines Vereins auf einen Blick.
                        </p>
                    </div>
                </div>
                <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-4" data-todo-dashboard>
                    <x-card class="bg-base-200 md:col-span-2 xl:col-span-1"
                        aria-labelledby="weekly-goal-heading">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h3 id="weekly-goal-heading"
                                    class="text-lg font-semibold text-primary">
                                    Wochenziel
                                </h3>
                                <p class="mt-1 text-sm text-base-content">
                                    Sammle kontinuierlich Baxx, um dein Wochenziel zu erreichen.
                                </p>
                            </div>
                            <div class="flex flex-col items-end text-right gap-1">
                                <span class="text-2xl font-bold leading-none tracking-tight text-base-content sm:text-3xl xl:text-4xl">
                                    {{ number_format($weeklyTotal, 0, ',', '.') }}
                                </span>
                                <p class="text-xs text-base-content">
                                    von {{ number_format($weeklyTarget, 0, ',', '.') }} Baxx
                                </p>
                            </div>
                        </div>
                        <div class="mt-4" data-progress-bar data-progress-value="{{ $weeklyTotal }}"
                            data-progress-max="{{ max($weeklyTarget, 1) }}"
                            data-progress-label="Fortschritt Richtung Wochenziel">
                            <div class="h-2 w-full bg-base-100 rounded-full overflow-hidden"
                                aria-hidden="true">
                                <div data-progress-fill
                                    class="h-full bg-primary"
                                    style="width: {{ $weeklyProgress }}%"></div>
                            </div>
                            <p class="mt-2 text-sm text-base-content">
                                {{ number_format($weeklyTotal, 0, ',', '.') }} von {{ number_format($weeklyTarget, 0, ',', '.') }} Baxx gesammelt.
                            </p>
                        </div>
                    </x-card>
                    <article class="rounded-lg border border-base-content/10 p-5 bg-base-200"
                        aria-labelledby="team-average-heading">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h3 id="team-average-heading"
                                    class="text-lg font-semibold text-primary">
                                    Vereinsdurchschnitt
                                </h3>
                                <p class="mt-1 text-sm text-base-content">
                                    Vergleiche deine Punkte mit dem Durchschnitt des Vereins.
                                </p>
                            </div>
                            <div class="flex flex-col items-end text-right gap-1">
                                <span class="text-2xl font-bold leading-none tracking-tight text-base-content sm:text-3xl xl:text-4xl">
                                    {{ number_format($teamAverage, 1, ',', '.') }}
                                </span>
                                <p class="text-xs text-base-content">Ø Vereins-Baxx</p>
                            </div>
                        </div>
                        <div class="mt-4" data-progress-bar data-progress-value="{{ $userTotalPoints }}"
                            data-progress-max="{{ max($teamAverage, 1) }}"
                            data-progress-label="Vergleich zum Vereinsdurchschnitt">
                            <div class="h-2 w-full bg-base-100 rounded-full overflow-hidden"
                                aria-hidden="true">
                                <div data-progress-fill
                                    class="h-full bg-secondary"
                                    style="width: {{ $teamAverageProgress }}%"></div>
                            </div>
                            <p class="mt-2 text-sm text-base-content">
                                @if(! is_null($teamAverageRatio))
                                    Du liegst bei {{ number_format($teamAverageRatio, 1, ',', '.') }} % des
                                    Vereinsdurchschnitts.
                                @else
                                    Sobald Vereinsmitglieder Punkte gesammelt haben, erscheint hier der Vergleich.
                                @endif
                            </p>
                        </div>
                    </article>
                    <x-card class="bg-base-200"
                        aria-labelledby="personal-points-heading">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h3 id="personal-points-heading"
                                    class="text-lg font-semibold text-primary">
                                    Dein Punktestand
                                </h3>
                                <p class="mt-1 text-sm text-base-content">
                                    Alle gesammelten Baxx deines Vereinskontos.
                                </p>
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
                    <x-card class="bg-base-200 md:col-span-2 xl:col-span-1"
                        aria-labelledby="leaderboard-heading">
                        <h3 id="leaderboard-heading"
                            class="text-lg font-semibold text-primary">
                            Rangliste
                        </h3>
                        <p class="mt-1 text-sm text-base-content">
                            So steht dein Verein aktuell da.
                        </p>
                        <ol class="mt-4 space-y-3" role="list">
                            @forelse($dashboard['leaderboard'] as $entry)
                                <li
                                    class="flex items-center justify-between gap-3 rounded-md px-3 py-2 @if($entry['is_current_user']) bg-primary text-primary-content @else bg-base-100 text-base-content @endif border border-base-content/10">
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
    </x-member-page>
</x-app-layout>