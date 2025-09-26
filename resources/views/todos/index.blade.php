<x-app-layout>
    <x-member-page>
            @if(session('status'))
                <div role="status" aria-live="polite"
                    class="mb-4 p-4 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-600 text-green-800 dark:text-green-200 rounded">
                    {{ session('status') }}
                </div>
            @endif
            @if(session('error'))
                <div role="alert"
                    class="mb-4 p-4 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-600 text-red-800 dark:text-red-200 rounded">
                    {{ session('error') }}
                </div>
            @endif
            @php
                $currentFilter = $activeFilter ?? 'all';
            @endphp
            <header class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Challenges &amp; Baxx</h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Behalte deine Fortschritte und die Ziele des Vereins im Blick.
                    </p>
                </div>
                @if($canCreateTodos)
                    <div>
                        <a href="{{ route('todos.create') }}"
                            class="inline-flex items-center px-4 py-2 bg-[#8B0116] dark:bg-[#C41E3A] border border-transparent rounded-md font-semibold text-white hover:bg-[#A50019] dark:hover:bg-[#D63A4D] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#8B0116] dark:focus:ring-[#FF6B81]">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Neue Challenge erstellen
                        </a>
                    </div>
                @endif
            </header>
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
                class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 id="todo-dashboard-heading"
                            class="text-xl font-semibold text-gray-900 dark:text-gray-100">Vereins-Dashboard</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Fortschritt, Vergleich und Ziele deines Vereins auf einen Blick.
                        </p>
                    </div>
                </div>
                <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-4" data-todo-dashboard>
                    <article class="rounded-lg border border-gray-200 dark:border-gray-700 p-5 bg-gray-50 dark:bg-gray-900 md:col-span-2 xl:col-span-1"
                        aria-labelledby="weekly-goal-heading">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h3 id="weekly-goal-heading"
                                    class="text-lg font-semibold text-[#8B0116] dark:text-[#FF6B81]">
                                    Wochenziel
                                </h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Sammle kontinuierlich Baxx, um dein Wochenziel zu erreichen.
                                </p>
                            </div>
                            <div class="flex flex-col items-end text-right gap-1">
                                <span class="text-2xl font-bold leading-none tracking-tight text-gray-900 dark:text-gray-100 sm:text-3xl xl:text-4xl">
                                    {{ number_format($weeklyTotal, 0, ',', '.') }}
                                </span>
                                <p class="text-xs text-gray-600 dark:text-gray-400">
                                    von {{ number_format($weeklyTarget, 0, ',', '.') }} Baxx
                                </p>
                            </div>
                        </div>
                        <div class="mt-4" data-progress-bar data-progress-value="{{ $weeklyTotal }}"
                            data-progress-max="{{ max($weeklyTarget, 1) }}"
                            data-progress-label="Fortschritt Richtung Wochenziel">
                            <div class="h-2 w-full bg-white dark:bg-gray-800 rounded-full overflow-hidden"
                                aria-hidden="true">
                                <div data-progress-fill
                                    class="h-full bg-[#8B0116] dark:bg-[#FF6B81]"
                                    style="width: {{ $weeklyProgress }}%"></div>
                            </div>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                {{ number_format($weeklyTotal, 0, ',', '.') }} von {{ number_format($weeklyTarget, 0, ',', '.') }} Baxx gesammelt.
                            </p>
                        </div>
                    </article>
                    <article class="rounded-lg border border-gray-200 dark:border-gray-700 p-5 bg-gray-50 dark:bg-gray-900"
                        aria-labelledby="team-average-heading">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h3 id="team-average-heading"
                                    class="text-lg font-semibold text-[#8B0116] dark:text-[#FF6B81]">
                                    Vereinsdurchschnitt
                                </h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Vergleiche deine Punkte mit dem Durchschnitt des Vereins.
                                </p>
                            </div>
                            <div class="flex flex-col items-end text-right gap-1">
                                <span class="text-2xl font-bold leading-none tracking-tight text-gray-900 dark:text-gray-100 sm:text-3xl xl:text-4xl">
                                    {{ number_format($teamAverage, 1, ',', '.') }}
                                </span>
                                <p class="text-xs text-gray-600 dark:text-gray-400">Ø Vereins-Baxx</p>
                            </div>
                        </div>
                        <div class="mt-4" data-progress-bar data-progress-value="{{ $userTotalPoints }}"
                            data-progress-max="{{ max($teamAverage, 1) }}"
                            data-progress-label="Vergleich zum Vereinsdurchschnitt">
                            <div class="h-2 w-full bg-white dark:bg-gray-800 rounded-full overflow-hidden"
                                aria-hidden="true">
                                <div data-progress-fill
                                    class="h-full bg-[#006D77] dark:bg-[#33BBC5]"
                                    style="width: {{ $teamAverageProgress }}%"></div>
                            </div>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                @if(! is_null($teamAverageRatio))
                                    Du liegst bei {{ number_format($teamAverageRatio, 1, ',', '.') }} % des
                                    Vereinsdurchschnitts.
                                @else
                                    Sobald Vereinsmitglieder Punkte gesammelt haben, erscheint hier der Vergleich.
                                @endif
                            </p>
                        </div>
                    </article>
                    <article class="rounded-lg border border-gray-200 dark:border-gray-700 p-5 bg-gray-50 dark:bg-gray-900"
                        aria-labelledby="personal-points-heading">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h3 id="personal-points-heading"
                                    class="text-lg font-semibold text-[#8B0116] dark:text-[#FF6B81]">
                                    Dein Punktestand
                                </h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Alle gesammelten Baxx deines Vereinskontos.
                                </p>
                            </div>
                            <div class="flex flex-col items-end text-right gap-1">
                                <span class="text-2xl font-bold leading-none tracking-tight text-gray-900 dark:text-gray-100 sm:text-3xl xl:text-4xl">
                                    {{ number_format($userTotalPoints, 0, ',', '.') }}
                                </span>
                                <p class="text-xs text-gray-600 dark:text-gray-400">gesammelte Baxx</p>
                            </div>
                        </div>
                        @if($teamAverage > 0)
                            <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                                Du liegst {{ $userTotalPoints >= $teamAverage ? 'über' : 'unter' }} dem Vereinsdurchschnitt
                                von {{ number_format($teamAverage, 1, ',', '.') }} Baxx.
                            </p>
                        @else
                            <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                                Sobald Baxx gesammelt wurden, erscheint hier dein Vergleich zum Verein.
                            </p>
                        @endif
                    </article>
                    <article class="rounded-lg border border-gray-200 dark:border-gray-700 p-5 bg-gray-50 dark:bg-gray-900 md:col-span-2 xl:col-span-1"
                        aria-labelledby="leaderboard-heading">
                        <h3 id="leaderboard-heading"
                            class="text-lg font-semibold text-[#8B0116] dark:text-[#FF6B81]">
                            Rangliste
                        </h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            So steht dein Verein aktuell da.
                        </p>
                        <ol class="mt-4 space-y-3" role="list">
                            @forelse($dashboard['leaderboard'] as $entry)
                                <li
                                    class="flex items-center justify-between gap-3 rounded-md px-3 py-2 @if($entry['is_current_user']) bg-[#8B0116] text-white dark:bg-[#FF6B81] dark:text-gray-900 @else bg-white text-gray-800 dark:bg-gray-800 dark:text-gray-100 @endif border border-gray-200 dark:border-gray-700">
                                    <div class="flex items-center gap-3">
                                        <span class="text-sm font-semibold @if($entry['is_current_user']) text-white dark:text-gray-900 @else text-gray-600 dark:text-gray-400 @endif">
                                            {{ $entry['rank'] ? '#' . $entry['rank'] : '–' }}
                                        </span>
                                        <span class="text-sm font-semibold">{{ $entry['name'] }}</span>
                                    </div>
                                    <span class="text-sm font-semibold">{{ number_format($entry['points'], 0, ',', '.') }} Baxx</span>
                                </li>
                            @empty
                                <li class="text-sm text-gray-600 dark:text-gray-400">
                                    Sobald Punkte gesammelt wurden, erscheint hier die Rangliste.
                                </li>
                            @endforelse
                        </ol>
                    </article>
                </div>
            </section>
            <section class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6" data-todo-filter-wrapper>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="space-y-1">
                        <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81]">Challenges filtern</h2>
                        <p id="todo-filter-status" data-todo-filter-status role="status" aria-live="polite"
                            class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $currentFilter === 'pending' ? 'Zeigt Challenges, die auf eine Verifizierung warten.' : 'Zeigt alle verfügbaren Challenges.' }}
                        </p>
                    </div>
                    <button type="button" data-todo-filter-toggle aria-expanded="true" aria-controls="todo-filter-panel"
                        data-label-open="Filter anzeigen" data-label-close="Filter verbergen"
                        class="inline-flex items-center gap-2 self-start rounded-md border border-[#8B0116] bg-white px-4 py-2 text-sm font-semibold text-[#8B0116] shadow-sm transition hover:bg-[#8B0116] hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8B0116] dark:border-[#FF6B81] dark:bg-gray-900 dark:text-[#FF6B81] dark:hover:bg-[#FF6B81] dark:hover:text-gray-900 dark:focus-visible:outline-[#FF6B81]">
                        <svg class="h-4 w-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-linecap="round"
                            stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M4 6h16M6 12h12M10 18h4" />
                        </svg>
                        <span data-todo-filter-toggle-text>Filter anzeigen</span>
                    </button>
                </div>
                <div id="todo-filter-panel" data-todo-filter-panel
                    class="mt-6 border-t border-gray-200 pt-6 dark:border-gray-700">
                    <form method="GET" action="{{ route('todos.index') }}" data-todo-filter-form
                        data-current-filter="{{ $currentFilter }}">
                        <fieldset class="space-y-4">
                            <legend class="sr-only">Challenges filtern</legend>
                            <div class="flex flex-wrap gap-2" role="group" aria-label="Challenges filtern">
                                <button type="submit" name="filter" value="" data-todo-filter data-filter="all"
                                    data-active="{{ $currentFilter === 'all' ? 'true' : 'false' }}"
                                    class="px-4 py-2 rounded-md border border-[#8B0116] text-[#8B0116] dark:text-[#FF6B81] dark:border-[#FF6B81] font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8B0116] dark:focus-visible:outline-[#FF6B81] data-[active=true]:bg-[#8B0116] data-[active=true]:text-white dark:data-[active=true]:bg-[#FF6B81] dark:data-[active=true]:text-gray-900">
                                    Alle
                                </button>
                                <button type="button" data-todo-filter data-filter="assigned" data-active="false"
                                    class="px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8B0116] dark:focus-visible:outline-[#FF6B81] data-[active=true]:bg-[#8B0116] data-[active=true]:text-white dark:data-[active=true]:bg-[#FF6B81] dark:data-[active=true]:text-gray-900">
                                    Eigene Challenges
                                </button>
                                <button type="button" data-todo-filter data-filter="open" data-active="false"
                                    class="px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8B0116] dark:focus-visible:outline-[#FF6B81] data-[active=true]:bg-[#8B0116] data-[active=true]:text-white dark:data-[active=true]:bg-[#FF6B81] dark:data-[active=true]:text-gray-900">
                                    Offene Challenges
                                </button>
                                @if($canVerifyTodos)
                                    <button type="submit" name="filter" value="pending" data-todo-filter
                                        data-filter="pending"
                                        data-active="{{ $currentFilter === 'pending' ? 'true' : 'false' }}"
                                        class="px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#8B0116] dark:focus-visible:outline-[#FF6B81] data-[active=true]:bg-[#8B0116] data-[active=true]:text-white dark:data-[active=true]:bg-[#FF6B81] dark:data-[active=true]:text-gray-900">
                                        Zu verifizieren
                                    </button>
                                @endif
                            </div>
                            <noscript>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Für weitere Filteroptionen aktiviere JavaScript in deinem Browser.
                                </p>
                            </noscript>
                        </fieldset>
                    </form>
                </div>
            </section>
            <!-- Deine Challenges -->
            <section data-todo-section="assigned" aria-labelledby="todo-assigned-heading"
                class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                <h2 id="todo-assigned-heading"
                    class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4">Deine Challenges</h2>
                @if($assignedTodos->isEmpty())
                    <p class="text-gray-600 dark:text-gray-400">Du hast aktuell keine übernommenen Challenges.</p>
                @else
                    <!-- Desktop-Ansicht (versteckt auf Mobilgeräten) -->
                    <div class="hidden md:block">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Titel</th>
                                    <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Status</th>
                                    <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Baxx</th>
                                    <th class="px-4 py-2 text-center text-gray-800 dark:text-gray-200">Aktion</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($assignedTodos as $todo)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $todo->title }}</td>
                                        <td class="px-4 py-2">
                                            @if($todo->status->value === 'assigned')
                                                <span
                                                    class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full text-xs">In
                                                    Bearbeitung</span>
                                            @elseif($todo->status->value === 'completed')
                                                <span
                                                    class="px-2 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded-full text-xs">Wartet
                                                    auf Verifizierung</span>
                                            @elseif($todo->status->value === 'verified')
                                                <span
                                                    class="px-2 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-full text-xs">Verifiziert</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $todo->points }}</td>
                                        <td class="px-4 py-2 text-center">
                                            <a href="{{ route('todos.show', $todo) }}"
                                                class="text-[#8B0116] dark:text-[#FF6B81] hover:underline">
                                                Details
                                            </a>
                                            @if($todo->created_by === Auth::id())
                                                <a href="{{ route('todos.edit', $todo) }}" class="ml-2 text-blue-600 dark:text-blue-400 hover:underline">Bearbeiten</a>
                                            @endif
                                            @if($todo->status->value === 'assigned')
                                                <form action="{{ route('todos.complete', $todo) }}" method="POST" class="inline-block ml-2">
                                                    @csrf
                                                    <button type="submit" class="text-green-600 dark:text-green-400 hover:underline">Als erledigt markieren</button>
                                                </form>
                                                <form action="{{ route('todos.release', $todo) }}" method="POST" class="inline-block ml-2">
                                                    @csrf
                                                    <button type="submit" class="text-red-600 dark:text-red-400 hover:underline">Freigeben</button>
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
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow">
                                <div class="mb-2">
                                    <h3 class="font-semibold text-gray-800 dark:text-white">{{ $todo->title }}</h3>
                                </div>
                                <div class="mb-2">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Status:</span>
                                    <div class="mt-1">
                                        @if($todo->status->value === 'assigned')
                                            <span
                                                class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full text-xs">In
                                                Bearbeitung</span>
                                        @elseif($todo->status->value === 'completed')
                                            <span
                                                class="px-2 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded-full text-xs">Wartet
                                                auf Verifizierung</span>
                                        @elseif($todo->status->value === 'verified')
                                            <span
                                                class="px-2 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-full text-xs">Verifiziert</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Baxx:</span>
                                    <div class="mt-1 font-semibold text-gray-800 dark:text-white">{{ $todo->points }}</div>
                                </div>
                                <div class="flex gap-2">
                                    <a href="{{ route('todos.show', $todo) }}"
                                        class="inline-block bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 text-gray-800 dark:text-white py-1 px-3 rounded text-sm">
                                        Details
                                    </a>
                                    @if($todo->created_by === Auth::id())
                                        <a href="{{ route('todos.edit', $todo) }}" class="inline-block bg-blue-500 hover:bg-blue-600 text-white py-1 px-3 rounded text-sm">Bearbeiten</a>
                                    @endif
                                    @if($todo->status->value === 'assigned')
                                        <div class="flex gap-2 mt-2">
                                            <form action="{{ route('todos.complete', $todo) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="inline-block bg-green-500 hover:bg-green-600 text-white py-1 px-3 rounded text-sm">Als erledigt markieren</button>
                                            </form>
                                            <form action="{{ route('todos.release', $todo) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="inline-block bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded text-sm">Freigeben</button>
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
                class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                <h2 id="todo-open-heading"
                    class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4">Offene Challenges</h2>
                @if($unassignedTodos->isEmpty())
                    <p class="text-gray-600 dark:text-gray-400">Es sind aktuell keine offenen Challenges verfügbar.</p>
                @else
                    <!-- Desktop-Ansicht (versteckt auf Mobilgeräten) -->
                    <div class="hidden md:block">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Titel</th>
                                    <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Kategorie</th>
                                    <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Erstellt von</th>
                                    <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Baxx</th>
                                    <th class="px-4 py-2 text-center text-gray-800 dark:text-gray-200">Aktion</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($unassignedTodos as $todo)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $todo->title }}</td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $todo->category ? $todo->category->name : '-' }}</td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300"><a href="{{ route('profile.view', $todo->creator->id) }}" class="text-[#8B0116] hover:underline">{{ $todo->creator->name }}</a></td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $todo->points }}</td>
                                        <td class="px-4 py-2 text-center">
                                            <a href="{{ route('todos.show', $todo) }}"
                                                class="text-[#8B0116] dark:text-[#FF6B81] hover:underline mr-2">
                                                Details
                                            </a>
                                            @if($todo->created_by === Auth::id())
                                                <a href="{{ route('todos.edit', $todo) }}" class="text-blue-600 dark:text-blue-400 hover:underline mr-2">Bearbeiten</a>
                                            @endif
                                            <form action="{{ route('todos.assign', $todo) }}" method="POST"
                                                class="inline-block">
                                                @csrf
                                                <button type="submit" class="text-blue-600 dark:text-blue-400 hover:underline">
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
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow">
                                <div class="mb-2">
                                    <h3 class="font-semibold text-gray-800 dark:text-white">{{ $todo->title }}</h3>
                                </div>
                                <div class="mb-2">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Kategorie:</span>
                                    <div class="mt-1 text-gray-800 dark:text-gray-200">{{ $todo->category ? $todo->category->name : '-' }}</div>
                                </div>
                                <div class="mb-2">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Erstellt von:</span>
                                    <div class="mt-1 text-gray-800 dark:text-gray-200"><a href="{{ route('profile.view', $todo->creator->id) }}" class="text-[#8B0116] hover:underline">{{ $todo->creator->name }}</a></div>
                                </div>
                                <div class="mb-3">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Baxx:</span>
                                    <div class="mt-1 font-semibold text-gray-800 dark:text-white">{{ $todo->points }}</div>
                                </div>
                                <div class="flex gap-2">
                                    <a href="{{ route('todos.show', $todo) }}"
                                        class="inline-block bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 text-gray-800 dark:text-white py-1 px-3 rounded text-sm">
                                        Details
                                    </a>
                                    @if($todo->created_by === Auth::id())
                                        <a href="{{ route('todos.edit', $todo) }}" class="inline-block bg-blue-500 hover:bg-blue-600 text-white py-1 px-3 rounded text-sm">Bearbeiten</a>
                                    @endif
                                    <form action="{{ route('todos.assign', $todo) }}" method="POST">
                                        @csrf
                                        <button type="submit"
                                            class="inline-block bg-blue-500 hover:bg-blue-600 text-white py-1 px-3 rounded text-sm">
                                            Übernehmen
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
            <!-- Erledigte Challenges (nur wenn Verifizierungsrechte vorhanden) -->
            @if($canVerifyTodos && $completedTodos->where('status', 'completed')->isNotEmpty())
                <section data-todo-section="pending" aria-labelledby="todo-pending-heading"
                    class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 id="todo-pending-heading"
                        class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4">Zu verifizierende Challenges
                    </h2>
                    <!-- Desktop-Ansicht (versteckt auf Mobilgeräten) -->
                    <div class="hidden md:block">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Titel</th>
                                    <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Bearbeitet von</th>
                                    <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Erledigt am</th>
                                    <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Baxx</th>
                                    <th class="px-4 py-2 text-center text-gray-800 dark:text-gray-200">Aktion</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($completedTodos->where('status', 'completed') as $todo)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $todo->title }}</td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300"><a href="{{ route('profile.view', $todo->assignee->id) }}" class="text-[#8B0116] hover:underline">{{ $todo->assignee->name }}</a></td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                                            {{ $todo->completed_at->format('d.m.Y H:i') }}</td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $todo->points }}</td>
                                        <td class="px-4 py-2 text-center">
                                            <a href="{{ route('todos.show', $todo) }}"
                                                class="text-[#8B0116] dark:text-[#FF6B81] hover:underline mr-2">
                                                Details
                                            </a>
                                            @if($todo->created_by === Auth::id())
                                                <a href="{{ route('todos.edit', $todo) }}" class="text-blue-600 dark:text-blue-400 hover:underline mr-2">Bearbeiten</a>
                                            @endif
                                            <form action="{{ route('todos.verify', $todo) }}" method="POST"
                                                class="inline-block">
                                                @csrf
                                                <button type="submit"
                                                    class="text-green-600 dark:text-green-400 hover:underline">
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
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow">
                                <div class="mb-2">
                                    <h3 class="font-semibold text-gray-800 dark:text-white">{{ $todo->title }}</h3>
                                </div>
                                <div class="mb-2">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Bearbeitet von:</span>
                                    <div class="mt-1 text-gray-800 dark:text-gray-200"><a href="{{ route('profile.view', $todo->assignee->id) }}" class="text-[#8B0116] hover:underline">{{ $todo->assignee->name }}</a></div>
                                </div>
                                <div class="mb-2">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Erledigt am:</span>
                                    <div class="mt-1 text-gray-800 dark:text-gray-200">
                                        {{ $todo->completed_at->format('d.m.Y H:i') }}</div>
                                </div>
                                <div class="mb-3">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Baxx:</span>
                                    <div class="mt-1 font-semibold text-gray-800 dark:text-white">{{ $todo->points }}</div>
                                </div>
                                <div class="flex gap-2">
                                    <a href="{{ route('todos.show', $todo) }}"
                                        class="inline-block bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 text-gray-800 dark:text-white py-1 px-3 rounded text-sm">
                                        Details
                                    </a>
                                    @if($todo->created_by === Auth::id())
                                        <a href="{{ route('todos.edit', $todo) }}" class="inline-block bg-blue-500 hover:bg-blue-600 text-white py-1 px-3 rounded text-sm">Bearbeiten</a>
                                    @endif
                                    <form action="{{ route('todos.verify', $todo) }}" method="POST">
                                        @csrf
                                        <button type="submit"
                                            class="inline-block bg-green-500 hover:bg-green-600 text-white py-1 px-3 rounded text-sm">
                                            Verifizieren
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <!-- In Bearbeitung befindliche Challenges - Nur anzeigen, wenn es welche gibt -->
            @php
                $inProgressTodos = $todos->where('status', 'assigned')->where('assigned_to', '!=', Auth::id());
            @endphp
            @if($inProgressTodos->isNotEmpty())
                <section data-todo-section="in-progress" aria-labelledby="todo-progress-heading"
                    class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
                    <h2 id="todo-progress-heading"
                        class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4">In Bearbeitung befindliche Challenges</h2>
                    <!-- Desktop-Ansicht (versteckt auf Mobilgeräten) -->
                    <div class="hidden md:block">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Titel</th>
                                    <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Kategorie</th>
                                    <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Bearbeitet von</th>
                                    <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Baxx</th>
                                    <th class="px-4 py-2 text-center text-gray-800 dark:text-gray-200">Aktion</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($inProgressTodos as $todo)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $todo->title }}</td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $todo->category ? $todo->category->name : '-' }}</td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300"><a href="{{ route('profile.view', $todo->assignee->id) }}" class="text-[#8B0116] hover:underline">{{ $todo->assignee->name }}</a></td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $todo->points }}</td>
                                        <td class="px-4 py-2 text-center">
                                            <a href="{{ route('todos.show', $todo) }}"
                                                class="text-[#8B0116] dark:text-[#FF6B81] hover:underline">
                                                Details
                                            </a>
                                            @if($todo->created_by === Auth::id())
                                                <a href="{{ route('todos.edit', $todo) }}" class="ml-2 text-blue-600 dark:text-blue-400 hover:underline">Bearbeiten</a>
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
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow">
                                <div class="mb-2">
                                    <h3 class="font-semibold text-gray-800 dark:text-white">{{ $todo->title }}</h3>
                                </div>
                                <div class="mb-2">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Kategorie:</span>
                                    <div class="mt-1 text-gray-800 dark:text-gray-200">{{ $todo->category ? $todo->category->name : '-' }}</div>
                                </div>
                                <div class="mb-2">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Bearbeitet von:</span>
                                    <div class="mt-1 text-gray-800 dark:text-gray-200"><a href="{{ route('profile.view', $todo->assignee->id) }}" class="text-[#8B0116] hover:underline">{{ $todo->assignee->name }}</a></div>
                                </div>
                                <div class="mb-3">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Baxx:</span>
                                    <div class="mt-1 font-semibold text-gray-800 dark:text-white">{{ $todo->points }}</div>
                                </div>
                                <div class="flex gap-2">
                                    <a href="{{ route('todos.show', $todo) }}"
                                        class="inline-block bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 text-gray-800 dark:text-white py-1 px-3 rounded text-sm">
                                        Details
                                    </a>
                                    @if($todo->created_by === Auth::id())
                                        <a href="{{ route('todos.edit', $todo) }}" class="inline-block bg-blue-500 hover:bg-blue-600 text-white py-1 px-3 rounded text-sm">Bearbeiten</a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
    </x-member-page>
</x-app-layout>