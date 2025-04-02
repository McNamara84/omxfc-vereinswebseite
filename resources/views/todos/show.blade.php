<x-app-layout>
    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

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

            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
                <!-- Titel und Status -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81]">{{ $todo->title }}</h2>
                    <div class="mt-2 md:mt-0">
                        @if($todo->status === 'open')
                            <span
                                class="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-full text-sm">Offen</span>
                        @elseif($todo->status === 'assigned')
                            <span
                                class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full text-sm">In
                                Bearbeitung</span>
                        @elseif($todo->status === 'completed')
                            <span
                                class="px-2 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 rounded-full text-sm">Wartet
                                auf Verifizierung</span>
                        @elseif($todo->status === 'verified')
                            <span
                                class="px-2 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-full text-sm">Verifiziert</span>
                        @endif
                    </div>
                </div>

                <!-- Beschreibung -->
                <div class="mb-6">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Beschreibung</h3>
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-md text-gray-800 dark:text-gray-200">
                        @if($todo->description)
                            {!! nl2br(e($todo->description)) !!}
                        @else
                            <span class="text-gray-500 dark:text-gray-400 italic">Keine Beschreibung vorhanden</span>
                        @endif
                    </div>
                </div>

                <!-- Details -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Details</h3>
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                            <div class="mb-2">
                                <span class="text-gray-600 dark:text-gray-400 text-sm">Punkte:</span>
                                <span
                                    class="ml-2 text-gray-800 dark:text-gray-200 font-semibold">{{ $todo->points }}</span>
                            </div>
                            <div class="mb-2">
                                <span class="text-gray-600 dark:text-gray-400 text-sm">Kategorie:</span>
                                <span class="ml-2 text-gray-800 dark:text-gray-200">{{ $todo->category ? $todo->category->name : 'Keine Kategorie' }}</span>
                            </div>
                            <div class="mb-2">
                                <span class="text-gray-600 dark:text-gray-400 text-sm">Erstellt von:</span>
                                <span class="ml-2 text-gray-800 dark:text-gray-200">{{ $todo->creator->name }}</span>
                            </div>
                            <div class="mb-2">
                                <span class="text-gray-600 dark:text-gray-400 text-sm">Erstellt am:</span>
                                <span
                                    class="ml-2 text-gray-800 dark:text-gray-200">{{ $todo->created_at->format('d.m.Y H:i') }}</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</h3>
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                            @if($todo->assigned_to)
                                <div class="mb-2">
                                    <span class="text-gray-600 dark:text-gray-400 text-sm">Zugewiesen an:</span>
                                    <span class="ml-2 text-gray-800 dark:text-gray-200">{{ $todo->assignee->name }}</span>
                                </div>
                            @endif

                            @if($todo->completed_at)
                                <div class="mb-2">
                                    <span class="text-gray-600 dark:text-gray-400 text-sm">Erledigt am:</span>
                                    <span
                                        class="ml-2 text-gray-800 dark:text-gray-200">{{ $todo->completed_at->format('d.m.Y H:i') }}</span>
                                </div>
                            @endif

                            @if($todo->verified_by)
                                <div class="mb-2">
                                    <span class="text-gray-600 dark:text-gray-400 text-sm">Verifiziert von:</span>
                                    <span class="ml-2 text-gray-800 dark:text-gray-200">{{ $todo->verifier->name }}</span>
                                </div>
                                <div class="mb-2">
                                    <span class="text-gray-600 dark:text-gray-400 text-sm">Verifiziert am:</span>
                                    <span
                                        class="ml-2 text-gray-800 dark:text-gray-200">{{ $todo->verified_at->format('d.m.Y H:i') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Aktionen -->
                <div class="flex justify-between mt-8">
                    <a href="{{ route('todos.index') }}"
                        class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-gray-800 dark:text-white hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        <svg class="h-5 w-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Zurück zur Übersicht
                    </a>

                    <div>
                        @if($canAssign)
                            <form action="{{ route('todos.assign', $todo) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Aufgabe übernehmen
                                </button>
                            </form>
                        @endif

                        @if($canComplete)
                            <form action="{{ route('todos.complete', $todo) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-yellow-500 border border-transparent rounded-md font-semibold text-white hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                                    Als erledigt markieren
                                </button>
                            </form>
                        @endif

                        @if($canVerify)
                            <form action="{{ route('todos.verify', $todo) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    Verifizieren und Punkte vergeben
                                </button>
                            </form>
                        @endif
                        @if($todo->assigned_to === Auth::id() && $todo->status === 'assigned')
                            <form action="{{ route('todos.release', $todo) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-gray-500 border border-transparent rounded-md font-semibold text-white hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                    Aufgabe freigeben
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>