<x-app-layout>
    <x-member-page class="max-w-3xl">

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

            <x-card shadow>
                <!-- Titel und Status -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <h2 class="text-xl font-semibold text-primary">{{ $todo->title }}</h2>
                    <div class="mt-2 md:mt-0">
                        @if($todo->status->value === 'open')
                            <span class="badge badge-ghost">Offen</span>
                        @elseif($todo->status->value === 'assigned')
                            <span class="badge badge-info">In Bearbeitung</span>
                        @elseif($todo->status->value === 'completed')
                            <span class="badge badge-warning">Wartet auf Verifizierung</span>
                        @elseif($todo->status->value === 'verified')
                            <span class="badge badge-success">Verifiziert</span>
                        @endif
                    </div>
                </div>

                <!-- Beschreibung -->
                <div class="mb-6">
                    <h3 class="text-sm font-medium text-base-content mb-2">Beschreibung</h3>
                    <div class="bg-base-200 p-4 rounded-md text-base-content">
                        @if($todo->description)
                            {!! nl2br(e($todo->description)) !!}
                        @else
                            <span class="text-base-content italic">Keine Beschreibung vorhanden</span>
                        @endif
                    </div>
                </div>

                <!-- Details -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <h3 class="text-sm font-medium text-base-content mb-2">Details</h3>
                        <div class="bg-base-200 p-4 rounded-md">
                            <div class="mb-2">
                                <span class="text-base-content text-sm">Baxx:</span>
                                <span
                                    class="ml-2 text-base-content font-semibold">{{ $todo->points }}</span>
                            </div>
                            <div class="mb-2">
                                <span class="text-base-content text-sm">Kategorie:</span>
                                <span class="ml-2 text-base-content">{{ $todo->category ? $todo->category->name : 'Keine Kategorie' }}</span>
                            </div>
                            <div class="mb-2">
                                <span class="text-base-content text-sm">Erstellt von:</span>
                                <span class="ml-2 text-base-content"><a href="{{ route('profile.view', $todo->creator->id) }}" class="text-primary hover:underline">{{ $todo->creator->name }}</a></span>
                            </div>
                            <div class="mb-2">
                                <span class="text-base-content text-sm">Erstellt am:</span>
                                <span
                                    class="ml-2 text-base-content">{{ $todo->created_at->format('d.m.Y H:i') }}</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-base-content mb-2">Status</h3>
                        <div class="bg-base-200 p-4 rounded-md">
                            @if($todo->assigned_to)
                                <div class="mb-2">
                                    <span class="text-base-content text-sm">Zugewiesen an:</span>
                                    <span class="ml-2 text-base-content"><a href="{{ route('profile.view', $todo->assignee->id) }}" class="text-primary hover:underline">{{ $todo->assignee->name }}</a></span>
                                </div>
                            @endif

                            @if($todo->completed_at)
                                <div class="mb-2">
                                    <span class="text-base-content text-sm">Erledigt am:</span>
                                    <span
                                        class="ml-2 text-base-content">{{ $todo->completed_at->format('d.m.Y H:i') }}</span>
                                </div>
                            @endif

                            @if($todo->verified_by)
                                <div class="mb-2">
                                    <span class="text-base-content text-sm">Verifiziert von:</span>
                                    <span class="ml-2 text-base-content"><a href="{{ route('profile.view', $todo->verifier->id) }}" class="text-primary hover:underline">{{ $todo->verifier->name }}</a></span>
                                </div>
                                <div class="mb-2">
                                    <span class="text-base-content text-sm">Verifiziert am:</span>
                                    <span
                                        class="ml-2 text-base-content">{{ $todo->verified_at->format('d.m.Y H:i') }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Aktionen -->
                <div class="mt-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-button link="{{ route('todos.index') }}" icon="o-arrow-left" class="btn-ghost">
                            Zurück zur Übersicht
                        </x-button>
                        @if($canEdit)
                            <x-button link="{{ route('todos.edit', $todo) }}" icon="o-pencil" class="btn-info">
                                Bearbeiten
                            </x-button>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-2 md:justify-end">
                        @if($canAssign)
                            <form action="{{ route('todos.assign', $todo) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="btn btn-info">
                                    Challenge übernehmen
                                </button>
                            </form>
                        @endif

                        @if($canComplete)
                            <form action="{{ route('todos.complete', $todo) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="btn btn-warning">
                                    Als erledigt markieren
                                </button>
                            </form>
                        @endif

                        @if($canVerify)
                            <form action="{{ route('todos.verify', $todo) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="btn btn-success">
                                    Verifizieren und Baxx vergeben
                                </button>
                            </form>
                        @endif
                        @if($todo->assigned_to === Auth::id() && $todo->status->value === 'assigned')
                            <form action="{{ route('todos.release', $todo) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="btn btn-ghost">
                                    Challenge freigeben
                                </button>
                            </form>
                        @endif
                        @if($canDelete)
                            <form action="{{ route('todos.destroy', $todo) }}" method="POST" class="inline"
                                onsubmit="return confirm('Möchtest du diese Challenge wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-error">
                                    <x-icon name="o-trash" class="w-5 h-5" />
                                    Challenge löschen
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </x-card>
    </x-member-page>
</x-app-layout>