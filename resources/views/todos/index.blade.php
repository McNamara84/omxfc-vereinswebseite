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
            <header class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-base-content">Challenges &amp; Baxx</h1>
                    <p class="text-sm text-base-content/60">
                        Behalte deine Fortschritte und die Ziele des Vereins im Blick.
                    </p>
                </div>
                @if($canCreateTodos)
                    <div>
                        <x-button link="{{ route('todos.create') }}" icon="o-plus" class="btn-primary">
                            Neue Challenge erstellen
                        </x-button>
                    </div>
                @endif
            </header>
            <section class="bg-base-100 shadow-xl sm:rounded-lg p-6 mb-6" data-todo-filter-wrapper>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="space-y-1">
                        <h2 class="text-xl font-semibold text-primary">Challenges filtern</h2>
                        <p id="todo-filter-status" data-todo-filter-status role="status" aria-live="polite"
                            class="text-sm text-base-content/60">
                            {{ $currentFilter === 'pending' ? 'Zeigt Challenges, die auf eine Verifizierung warten.' : 'Zeigt alle verfügbaren Challenges.' }}
                        </p>
                    </div>
                </div>
                <details class="group mt-4" data-todo-filter-details>
                    <summary data-todo-filter-summary
                        class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-primary bg-white px-4 py-2 text-sm font-semibold text-primary shadow-sm transition hover:bg-primary hover:text-primary-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary  dark:bg-base-200    ">
                        <svg class="h-4 w-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-linecap="round"
                            stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M4 6h16M6 12h12M10 18h4" />
                        </svg>
                        <span class="group-open:hidden">Filter anzeigen</span>
                        <span class="hidden group-open:inline">Filter ausblenden</span>
                    </summary>
                    <div id="todo-filter-panel" class="mt-6 border-t border-base-content/10 pt-6">
                        <form method="GET" action="{{ route('todos.index') }}" data-todo-filter-form
                            data-current-filter="{{ $currentFilter }}">
                            <fieldset class="space-y-4">
                                <legend class="sr-only">Challenges filtern</legend>
                                <div class="flex flex-wrap gap-2" role="group" aria-label="Challenges filtern">
                                    <button type="submit" name="filter" value="" data-todo-filter data-filter="all"
                                        data-active="{{ $currentFilter === 'all' ? 'true' : 'false' }}"
                                        class="px-4 py-2 rounded-md border border-primary text-primary  font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary  data-[active=true]:bg-primary data-[active=true]:text-primary-content  ">
                                        Alle
                                    </button>
                                    <button type="button" data-todo-filter data-filter="assigned" data-active="false"
                                        class="px-4 py-2 rounded-md border border-base-content/20 text-base-content/80 font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary   data-[active=true]:bg-primary data-[active=true]:text-primary-content  ">
                                        Eigene Challenges
                                    </button>
                                    <button type="button" data-todo-filter data-filter="open" data-active="false"
                                        class="px-4 py-2 rounded-md border border-base-content/20 text-base-content/80 font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary data-[active=true]:bg-primary data-[active=true]:text-primary-content">
                                        Offene Challenges
                                    </button>
                                    @if($canVerifyTodos)
                                        <button type="submit" name="filter" value="pending" data-todo-filter
                                            data-filter="pending"
                                            data-active="{{ $currentFilter === 'pending' ? 'true' : 'false' }}"
                                            class="px-4 py-2 rounded-md border border-base-content/20 text-base-content/80 font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary data-[active=true]:bg-primary data-[active=true]:text-primary-content">
                                            Zu verifizieren
                                        </button>
                                    @endif
                                </div>
                                <noscript>
                                    <p class="text-xs text-base-content/60">
                                        Für weitere Filteroptionen aktiviere JavaScript in deinem Browser.
                                    </p>
                                </noscript>
                            </fieldset>
                        </form>
                    </div>
                </details>
            </section>

            <!-- Zu verifizierende Challenges (nur wenn Verifizierungsrechte vorhanden) -->
            @if($canVerifyTodos && $completedTodos->where('status', 'completed')->isNotEmpty())
                <section data-todo-section="pending" aria-labelledby="todo-pending-heading"
                    class="bg-base-100 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 id="todo-pending-heading"
                        class="text-xl font-semibold text-primary mb-4">Zu verifizierende Challenges
                    </h2>
                    <!-- Desktop-Ansicht (versteckt auf Mobilgeräten) -->
                    <div class="hidden md:block">
                        <table class="min-w-full ">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-base-content">Titel</th>
                                    <th class="px-4 py-2 text-left text-base-content">Bearbeitet von</th>
                                    <th class="px-4 py-2 text-left text-base-content">Erledigt am</th>
                                    <th class="px-4 py-2 text-left text-base-content">Baxx</th>
                                    <th class="px-4 py-2 text-center text-base-content">Aktion</th>
                                </tr>
                            </thead>
                            <tbody class="">
                                @foreach($completedTodos->where('status', 'completed') as $todo)
                                    <tr>
                                        <td class="px-4 py-2 text-base-content/80">{{ $todo->title }}</td>
                                        <td class="px-4 py-2 text-base-content/80"><a href="{{ route('profile.view', $todo->assignee->id) }}" class="text-primary hover:underline">{{ $todo->assignee->name }}</a></td>
                                        <td class="px-4 py-2 text-base-content/80">
                                            {{ $todo->completed_at->format('d.m.Y H:i') }}</td>
                                        <td class="px-4 py-2 text-base-content/80">{{ $todo->points }}</td>
                                        <td class="px-4 py-2 text-center">
                                            <a href="{{ route('todos.show', $todo) }}"
                                                class="text-primary hover:underline mr-2">
                                                Details
                                            </a>
                                            @if($todo->created_by === Auth::id())
                                                <a href="{{ route('todos.edit', $todo) }}" class="text-info hover:underline mr-2">Bearbeiten</a>
                                            @endif
                                            <form action="{{ route('todos.verify', $todo) }}" method="POST"
                                                class="inline-block">
                                                @csrf
                                                <button type="submit"
                                                    class="text-success hover:underline">
                                                    Verifizieren
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <!-- Mobile-Ansicht (nur auf Mobilgeräten sichtbar) -->
                    <div class="md:hidden space-y-4">
                        @foreach($completedTodos->where('status', 'completed') as $todo)
                            <div class="bg-base-200 p-4 rounded-lg shadow">
                                <div class="mb-2">
                                    <h3 class="font-semibold text-base-content">{{ $todo->title }}</h3>
                                </div>
                                <div class="mb-2">
                                    <span class="text-sm text-base-content/60">Bearbeitet von:</span>
                                    <div class="mt-1 text-base-content"><a href="{{ route('profile.view', $todo->assignee->id) }}" class="text-primary hover:underline">{{ $todo->assignee->name }}</a></div>
                                </div>
                                <div class="mb-2">
                                    <span class="text-sm text-base-content/60">Erledigt am:</span>
                                    <div class="mt-1 text-base-content">
                                        {{ $todo->completed_at->format('d.m.Y H:i') }}</div>
                                </div>
                                <div class="mb-3">
                                    <span class="text-sm text-base-content/60">Baxx:</span>
                                    <div class="mt-1 font-semibold text-base-content">{{ $todo->points }}</div>
                                </div>
                                <div class="flex gap-2">
                                    <a href="{{ route('todos.show', $todo) }}"
                                        class="btn btn-ghost btn-sm py-1 px-3 rounded text-sm">
                                        Details
                                    </a>
                                    @if($todo->created_by === Auth::id())
                                        <a href="{{ route('todos.edit', $todo) }}" class="btn btn-info btn-sm py-1 px-3 rounded text-sm">Bearbeiten</a>
                                    @endif
                                    <form action="{{ route('todos.verify', $todo) }}" method="POST">
                                        @csrf
                                        <button type="submit"
                                            class="btn btn-success btn-sm py-1 px-3 rounded text-sm">
                                            Verifizieren
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <!-- In Bearbeitung befindliche Challenges (andere Nutzer) - nur anzeigen, wenn es welche gibt -->
            @php
                $inProgressTodos = $todos->where('status', 'assigned')->where('assigned_to', '!=', Auth::id());
            @endphp
            @if($inProgressTodos->isNotEmpty())
                <section data-todo-section="in-progress" aria-labelledby="todo-progress-heading"
                    class="bg-base-100 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 id="todo-progress-heading"
                        class="text-xl font-semibold text-primary mb-4">In Bearbeitung befindliche Challenges</h2>
                    <!-- Desktop-Ansicht (versteckt auf Mobilgeräten) -->
                    <div class="hidden md:block">
                        <table class="min-w-full ">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-base-content">Titel</th>
                                    <th class="px-4 py-2 text-left text-base-content">Kategorie</th>
                                    <th class="px-4 py-2 text-left text-base-content">Bearbeitet von</th>
                                    <th class="px-4 py-2 text-left text-base-content">Baxx</th>
                                    <th class="px-4 py-2 text-center text-base-content">Aktion</th>
                                </tr>
                            </thead>
                            <tbody class="">
                                @foreach($inProgressTodos as $todo)
                                    <tr>
                                        <td class="px-4 py-2 text-base-content/80">{{ $todo->title }}</td>
                                        <td class="px-4 py-2 text-base-content/80">{{ $todo->category ? $todo->category->name : '-' }}</td>
                                        <td class="px-4 py-2 text-base-content/80"><a href="{{ route('profile.view', $todo->assignee->id) }}" class="text-primary hover:underline">{{ $todo->assignee->name }}</a></td>
                                        <td class="px-4 py-2 text-base-content/80">{{ $todo->points }}</td>
                                        <td class="px-4 py-2 text-center">
                                            <a href="{{ route('todos.show', $todo) }}"
                                                class="text-primary hover:underline">
                                                Details
                                            </a>
                                            @if($todo->created_by === Auth::id())
                                                <a href="{{ route('todos.edit', $todo) }}" class="ml-2 text-info hover:underline">Bearbeiten</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <!-- Mobile-Ansicht (nur auf Mobilgeräten sichtbar) -->
                    <div class="md:hidden space-y-4">
                        @foreach($inProgressTodos as $todo)
                            <div class="bg-base-200 p-4 rounded-lg shadow">
                                <div class="mb-2">
                                    <h3 class="font-semibold text-base-content">{{ $todo->title }}</h3>
                                </div>
                                <div class="mb-2">
                                    <span class="text-sm text-base-content/60">Kategorie:</span>
                                    <div class="mt-1 text-base-content">{{ $todo->category ? $todo->category->name : '-' }}</div>
                                </div>
                                <div class="mb-2">
                                    <span class="text-sm text-base-content/60">Bearbeitet von:</span>
                                    <div class="mt-1 text-base-content"><a href="{{ route('profile.view', $todo->assignee->id) }}" class="text-primary hover:underline">{{ $todo->assignee->name }}</a></div>
                                </div>
                                <div class="mb-3">
                                    <span class="text-sm text-base-content/60">Baxx:</span>
                                    <div class="mt-1 font-semibold text-base-content">{{ $todo->points }}</div>
                                </div>
                                <div class="flex gap-2">
                                    <a href="{{ route('todos.show', $todo) }}"
                                        class="btn btn-ghost btn-sm py-1 px-3 rounded text-sm">
                                        Details
                                    </a>
                                    @if($todo->created_by === Auth::id())
                                        <a href="{{ route('todos.edit', $todo) }}" class="btn btn-info btn-sm py-1 px-3 rounded text-sm">Bearbeiten</a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
            <!-- Deine Challenges -->
            <section data-todo-section="assigned" aria-labelledby="todo-assigned-heading"
                class="bg-base-100 shadow-xl sm:rounded-lg p-6 mb-6">
                <h2 id="todo-assigned-heading"
                    class="text-xl font-semibold text-primary mb-4">Deine Challenges</h2>
                @if($assignedTodos->isEmpty())
                    <p class="text-base-content/60">Du hast aktuell keine übernommenen Challenges.</p>
                @else
                    <!-- Desktop-Ansicht (versteckt auf Mobilgeräten) -->
                    <div class="hidden md:block">
                        <table class="min-w-full ">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-base-content">Titel</th>
                                    <th class="px-4 py-2 text-left text-base-content">Status</th>
                                    <th class="px-4 py-2 text-left text-base-content">Baxx</th>
                                    <th class="px-4 py-2 text-center text-base-content">Aktion</th>
                                </tr>
                            </thead>
                            <tbody class="">
                                @foreach($assignedTodos as $todo)
                                    <tr>
                                        <td class="px-4 py-2 text-base-content/80">{{ $todo->title }}</td>
                                        <td class="px-4 py-2">
                                            @if($todo->status->value === 'assigned')
                                                <span
                                                    class="badge badge-info">In
                                                    Bearbeitung</span>
                                            @elseif($todo->status->value === 'completed')
                                                <span
                                                    class="badge badge-warning">Wartet
                                                    auf Verifizierung</span>
                                            @elseif($todo->status->value === 'verified')
                                                <span
                                                    class="badge badge-success">Verifiziert</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-base-content/80">{{ $todo->points }}</td>
                                        <td class="px-4 py-2 text-center">
                                            <a href="{{ route('todos.show', $todo) }}"
                                                class="text-primary hover:underline">
                                                Details
                                            </a>
                                            @if($todo->created_by === Auth::id())
                                                <a href="{{ route('todos.edit', $todo) }}" class="ml-2 text-info hover:underline">Bearbeiten</a>
                                            @endif
                                            @if($todo->status->value === 'assigned')
                                                <form action="{{ route('todos.complete', $todo) }}" method="POST" class="inline-block ml-2">
                                                    @csrf
                                                    <button type="submit" class="text-success hover:underline">Als erledigt markieren</button>
                                                </form>
                                                <form action="{{ route('todos.release', $todo) }}" method="POST" class="inline-block ml-2">
                                                    @csrf
                                                    <button type="submit" class="text-error hover:underline">Freigeben</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <!-- Mobile-Ansicht (nur auf Mobilgeräten sichtbar) -->
                    <div class="md:hidden space-y-4">
                        @foreach($assignedTodos as $todo)
                            <div class="bg-base-200 p-4 rounded-lg shadow">
                                <div class="mb-2">
                                    <h3 class="font-semibold text-base-content">{{ $todo->title }}</h3>
                                </div>
                                <div class="mb-2">
                                    <span class="text-sm text-base-content/60">Status:</span>
                                    <div class="mt-1">
                                        @if($todo->status->value === 'assigned')
                                            <span
                                                class="badge badge-info">In
                                                Bearbeitung</span>
                                        @elseif($todo->status->value === 'completed')
                                            <span
                                                class="badge badge-warning">Wartet
                                                auf Verifizierung</span>
                                        @elseif($todo->status->value === 'verified')
                                            <span
                                                class="badge badge-success">Verifiziert</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <span class="text-sm text-base-content/60">Baxx:</span>
                                    <div class="mt-1 font-semibold text-base-content">{{ $todo->points }}</div>
                                </div>
                                <div class="flex gap-2">
                                    <a href="{{ route('todos.show', $todo) }}"
                                        class="btn btn-ghost btn-sm py-1 px-3 rounded text-sm">
                                        Details
                                    </a>
                                    @if($todo->created_by === Auth::id())
                                        <a href="{{ route('todos.edit', $todo) }}" class="btn btn-info btn-sm py-1 px-3 rounded text-sm">Bearbeiten</a>
                                    @endif
                                    @if($todo->status->value === 'assigned')
                                        <div class="flex gap-2 mt-2">
                                            <form action="{{ route('todos.complete', $todo) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-sm py-1 px-3 rounded text-sm">Als erledigt markieren</button>
                                            </form>
                                            <form action="{{ route('todos.release', $todo) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-error btn-sm py-1 px-3 rounded text-sm">Freigeben</button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
            <!-- Offene Challenges -->
            <section data-todo-section="open" aria-labelledby="todo-open-heading"
                class="bg-base-100 shadow-xl sm:rounded-lg p-6 mb-6">
                <h2 id="todo-open-heading"
                    class="text-xl font-semibold text-primary mb-4">Offene Challenges</h2>
                @if($unassignedTodos->isEmpty())
                    <p class="text-base-content/60">Es sind aktuell keine offenen Challenges verfügbar.</p>
                @else
                    <!-- Desktop-Ansicht (versteckt auf Mobilgeräten) -->
                    <div class="hidden md:block">
                        <table class="min-w-full ">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-base-content">Titel</th>
                                    <th class="px-4 py-2 text-left text-base-content">Kategorie</th>
                                    <th class="px-4 py-2 text-left text-base-content">Erstellt von</th>
                                    <th class="px-4 py-2 text-left text-base-content">Baxx</th>
                                    <th class="px-4 py-2 text-center text-base-content">Aktion</th>
                                </tr>
                            </thead>
                            <tbody class="">
                                @foreach($unassignedTodos as $todo)
                                    <tr>
                                        <td class="px-4 py-2 text-base-content/80">{{ $todo->title }}</td>
                                        <td class="px-4 py-2 text-base-content/80">{{ $todo->category ? $todo->category->name : '-' }}</td>
                                        <td class="px-4 py-2 text-base-content/80"><a href="{{ route('profile.view', $todo->creator->id) }}" class="text-primary hover:underline">{{ $todo->creator->name }}</a></td>
                                        <td class="px-4 py-2 text-base-content/80">{{ $todo->points }}</td>
                                        <td class="px-4 py-2 text-center">
                                            <a href="{{ route('todos.show', $todo) }}"
                                                class="text-primary hover:underline mr-2">
                                                Details
                                            </a>
                                            @if($todo->created_by === Auth::id())
                                                <a href="{{ route('todos.edit', $todo) }}" class="text-info hover:underline mr-2">Bearbeiten</a>
                                            @endif
                                            <form action="{{ route('todos.assign', $todo) }}" method="POST"
                                                class="inline-block">
                                                @csrf
                                                <button type="submit" class="text-info hover:underline">
                                                    Übernehmen
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <!-- Mobile-Ansicht (nur auf Mobilgeräten sichtbar) -->
                    <div class="md:hidden space-y-4">
                        @foreach($unassignedTodos as $todo)
                            <div class="bg-base-200 p-4 rounded-lg shadow">
                                <div class="mb-2">
                                    <h3 class="font-semibold text-base-content">{{ $todo->title }}</h3>
                                </div>
                                <div class="mb-2">
                                    <span class="text-sm text-base-content/60">Kategorie:</span>
                                    <div class="mt-1 text-base-content">{{ $todo->category ? $todo->category->name : '-' }}</div>
                                </div>
                                <div class="mb-2">
                                    <span class="text-sm text-base-content/60">Erstellt von:</span>
                                    <div class="mt-1 text-base-content"><a href="{{ route('profile.view', $todo->creator->id) }}" class="text-primary hover:underline">{{ $todo->creator->name }}</a></div>
                                </div>
                                <div class="mb-3">
                                    <span class="text-sm text-base-content/60">Baxx:</span>
                                    <div class="mt-1 font-semibold text-base-content">{{ $todo->points }}</div>
                                </div>
                                <div class="flex gap-2">
                                    <a href="{{ route('todos.show', $todo) }}"
                                        class="btn btn-ghost btn-sm py-1 px-3 rounded text-sm">
                                        Details
                                    </a>
                                    @if($todo->created_by === Auth::id())
                                        <a href="{{ route('todos.edit', $todo) }}" class="btn btn-info btn-sm py-1 px-3 rounded text-sm">Bearbeiten</a>
                                    @endif
                                    <form action="{{ route('todos.assign', $todo) }}" method="POST">
                                        @csrf
                                        <button type="submit"
                                            class="btn btn-info btn-sm py-1 px-3 rounded text-sm">
                                            Übernehmen
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

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
            <section aria-labelledby="todo-dashboard-heading"
                class="bg-base-100 shadow-xl sm:rounded-lg p-6 mb-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 id="todo-dashboard-heading"
                            class="text-xl font-semibold text-base-content">Vereins-Dashboard</h2>
                        <p class="text-sm text-base-content/60">
                            Fortschritt, Vergleich und Ziele deines Vereins auf einen Blick.
                        </p>
                    </div>
                </div>
                <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-4" data-todo-dashboard>
                    <article class="rounded-lg border border-base-content/10 p-5 bg-base-200 md:col-span-2 xl:col-span-1"
                        aria-labelledby="weekly-goal-heading">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h3 id="weekly-goal-heading"
                                    class="text-lg font-semibold text-primary">
                                    Wochenziel
                                </h3>
                                <p class="mt-1 text-sm text-base-content/60">
                                    Sammle kontinuierlich Baxx, um dein Wochenziel zu erreichen.
                                </p>
                            </div>
                            <div class="flex flex-col items-end text-right gap-1">
                                <span class="text-2xl font-bold leading-none tracking-tight text-base-content sm:text-3xl xl:text-4xl">
                                    {{ number_format($weeklyTotal, 0, ',', '.') }}
                                </span>
                                <p class="text-xs text-base-content/60">
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
                            <p class="mt-2 text-sm text-base-content/60">
                                {{ number_format($weeklyTotal, 0, ',', '.') }} von {{ number_format($weeklyTarget, 0, ',', '.') }} Baxx gesammelt.
                            </p>
                        </div>
                    </article>
                    <article class="rounded-lg border border-base-content/10 p-5 bg-base-200"
                        aria-labelledby="team-average-heading">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h3 id="team-average-heading"
                                    class="text-lg font-semibold text-primary">
                                    Vereinsdurchschnitt
                                </h3>
                                <p class="mt-1 text-sm text-base-content/60">
                                    Vergleiche deine Punkte mit dem Durchschnitt des Vereins.
                                </p>
                            </div>
                            <div class="flex flex-col items-end text-right gap-1">
                                <span class="text-2xl font-bold leading-none tracking-tight text-base-content sm:text-3xl xl:text-4xl">
                                    {{ number_format($teamAverage, 1, ',', '.') }}
                                </span>
                                <p class="text-xs text-base-content/60">Ø Vereins-Baxx</p>
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
                            <p class="mt-2 text-sm text-base-content/60">
                                @if(! is_null($teamAverageRatio))
                                    Du liegst bei {{ number_format($teamAverageRatio, 1, ',', '.') }} % des
                                    Vereinsdurchschnitts.
                                @else
                                    Sobald Vereinsmitglieder Punkte gesammelt haben, erscheint hier der Vergleich.
                                @endif
                            </p>
                        </div>
                    </article>
                    <article class="rounded-lg border border-base-content/10 p-5 bg-base-200"
                        aria-labelledby="personal-points-heading">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h3 id="personal-points-heading"
                                    class="text-lg font-semibold text-primary">
                                    Dein Punktestand
                                </h3>
                                <p class="mt-1 text-sm text-base-content/60">
                                    Alle gesammelten Baxx deines Vereinskontos.
                                </p>
                            </div>
                            <div class="flex flex-col items-end text-right gap-1">
                                <span class="text-2xl font-bold leading-none tracking-tight text-base-content sm:text-3xl xl:text-4xl">
                                    {{ number_format($userTotalPoints, 0, ',', '.') }}
                                </span>
                                <p class="text-xs text-base-content/60">gesammelte Baxx</p>
                            </div>
                        </div>
                        @if($teamAverage > 0)
                            <p class="mt-4 text-sm text-base-content/60">
                                Du liegst {{ $userTotalPoints >= $teamAverage ? 'über' : 'unter' }} dem Vereinsdurchschnitt
                                von {{ number_format($teamAverage, 1, ',', '.') }} Baxx.
                            </p>
                        @else
                            <p class="mt-4 text-sm text-base-content/60">
                                Sobald Baxx gesammelt wurden, erscheint hier dein Vergleich zum Verein.
                            </p>
                        @endif
                    </article>
                    <article class="rounded-lg border border-base-content/10 p-5 bg-base-200 md:col-span-2 xl:col-span-1"
                        aria-labelledby="leaderboard-heading">
                        <h3 id="leaderboard-heading"
                            class="text-lg font-semibold text-primary">
                            Rangliste
                        </h3>
                        <p class="mt-1 text-sm text-base-content/60">
                            So steht dein Verein aktuell da.
                        </p>
                        <ol class="mt-4 space-y-3" role="list">
                            @forelse($dashboard['leaderboard'] as $entry)
                                <li
                                    class="flex items-center justify-between gap-3 rounded-md px-3 py-2 @if($entry['is_current_user']) bg-primary text-primary-content @else bg-base-100 text-base-content @endif border border-base-content/10">
                                    <div class="flex items-center gap-3">
                                        <span class="text-sm font-semibold @if($entry['is_current_user']) text-primary-content @else text-base-content/60 @endif">
                                            {{ $entry['rank'] ? '#' . $entry['rank'] : '–' }}
                                        </span>
                                        <span class="text-sm font-semibold">{{ $entry['name'] }}</span>
                                    </div>
                                    <span class="text-sm font-semibold">{{ number_format($entry['points'], 0, ',', '.') }} Baxx</span>
                                </li>
                            @empty
                                <li class="text-sm text-base-content/60">
                                    Sobald Punkte gesammelt wurden, erscheint hier die Rangliste.
                                </li>
                            @endforelse
                        </ol>
                    </article>
                </div>
            </section>
    </x-member-page>
</x-app-layout>