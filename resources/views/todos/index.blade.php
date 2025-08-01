<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('status'))
                <div
                    class="mb-4 p-4 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-600 text-green-800 dark:text-green-200 rounded">
                    {{ session('status') }}
                </div>
            @endif
            @if(session('error'))
                <div
                    class="mb-4 p-4 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-600 text-red-800 dark:text-red-200 rounded">
                    {{ session('error') }}
                </div>
            @endif
            <!-- Kopfzeile mit Baxx und Aktionen -->
            <div
                class="bg-maddrax-black border border-maddrax-red shadow-xl sm:rounded-lg p-6 mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-1">Deine Baxx</h2>
                    <div class="text-4xl font-bold text-gray-800 dark:text-gray-200">
                        {{ $userPoints }}
                    </div>
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
            </div>
            <!-- Deine Challenges -->
            <div class="bg-maddrax-black border border-maddrax-red shadow-xl sm:rounded-lg p-6 mb-6">
                <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4">Deine Challenges</h2>
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
                                        <td class="px-4 py-2 text-maddrax-sand">{{ $todo->title }}</td>
                                        <td class="px-4 py-2">
                                            @if($todo->status === 'assigned')
                                                <span
                                                    class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full text-xs">In
                                                    Bearbeitung</span>
                                            @elseif($todo->status === 'completed')
                                                <span
                                                    class="px-2 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded-full text-xs">Wartet
                                                    auf Verifizierung</span>
                                            @elseif($todo->status === 'verified')
                                                <span
                                                    class="px-2 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-full text-xs">Verifiziert</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-maddrax-sand">{{ $todo->points }}</td>
                                        <td class="px-4 py-2 text-center">
                                            <a href="{{ route('todos.show', $todo) }}"
                                                class="text-[#8B0116] dark:text-[#FF6B81] hover:underline">
                                                Details
                                            </a>
                                            @if($todo->created_by === Auth::id())
                                                <a href="{{ route('todos.edit', $todo) }}" class="ml-2 text-blue-600 dark:text-blue-400 hover:underline">Bearbeiten</a>
                                            @endif
                                            @if($todo->status === 'assigned')
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
                                        @if($todo->status === 'assigned')
                                            <span
                                                class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full text-xs">In
                                                Bearbeitung</span>
                                        @elseif($todo->status === 'completed')
                                            <span
                                                class="px-2 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded-full text-xs">Wartet
                                                auf Verifizierung</span>
                                        @elseif($todo->status === 'verified')
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
                                    @if($todo->status === 'assigned')
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
            </div>
            <!-- Offene Challenges -->
            <div class="bg-maddrax-black border border-maddrax-red shadow-xl sm:rounded-lg p-6 mb-6">
                <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4">Offene Challenges</h2>
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
                                        <td class="px-4 py-2 text-maddrax-sand">{{ $todo->title }}</td>
                                        <td class="px-4 py-2 text-maddrax-sand">{{ $todo->category ? $todo->category->name : '-' }}</td>
                                        <td class="px-4 py-2 text-maddrax-sand">{{ $todo->creator->name }}</td>
                                        <td class="px-4 py-2 text-maddrax-sand">{{ $todo->points }}</td>
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
                                    <div class="mt-1 text-gray-800 dark:text-gray-200">{{ $todo->creator->name }}</div>
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
            </div>
            <!-- Erledigte Challenges (nur wenn Verifizierungsrechte vorhanden) -->
            @if($canVerifyTodos && $completedTodos->where('status', 'completed')->isNotEmpty())
                <div class="bg-maddrax-black border border-maddrax-red shadow-xl sm:rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4">Zu verifizierende Challenges
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
                                        <td class="px-4 py-2 text-maddrax-sand">{{ $todo->title }}</td>
                                        <td class="px-4 py-2 text-maddrax-sand">{{ $todo->assignee->name }}</td>
                                        <td class="px-4 py-2 text-maddrax-sand">
                                            {{ $todo->completed_at->format('d.m.Y H:i') }}</td>
                                        <td class="px-4 py-2 text-maddrax-sand">{{ $todo->points }}</td>
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
                                    <div class="mt-1 text-gray-800 dark:text-gray-200">{{ $todo->assignee->name }}</div>
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
                </div>
            @endif

            <!-- In Bearbeitung befindliche Challenges - Nur anzeigen, wenn es welche gibt -->
            @php
                $inProgressTodos = $todos->where('status', 'assigned')->where('assigned_to', '!=', Auth::id());
            @endphp
            @if($inProgressTodos->isNotEmpty())
                <div class="bg-maddrax-black border border-maddrax-red shadow-xl sm:rounded-lg p-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4">In Bearbeitung befindliche Challenges</h2>
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
                                        <td class="px-4 py-2 text-maddrax-sand">{{ $todo->title }}</td>
                                        <td class="px-4 py-2 text-maddrax-sand">{{ $todo->category ? $todo->category->name : '-' }}</td>
                                        <td class="px-4 py-2 text-maddrax-sand">{{ $todo->assignee->name }}</td>
                                        <td class="px-4 py-2 text-maddrax-sand">{{ $todo->points }}</td>
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
                                    <div class="mt-1 text-gray-800 dark:text-gray-200">{{ $todo->assignee->name }}</div>
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
                </div>
            @endif
        </div>
    </div>
</x-app-layout>