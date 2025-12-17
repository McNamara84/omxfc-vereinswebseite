<div class="py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                        VIP-Autoren verwalten
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400">
                        Verwalte die Autoren, die als VIP-Gäste beim Fantreffen 2026 angekündigt werden.
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('admin.fantreffen.2026') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Zurück zu Anmeldungen
                    </a>
                </div>
            </div>
        </div>

        {{-- Success/Error Messages --}}
        @if (session()->has('success'))
            <div class="mb-6 bg-green-100 dark:bg-green-900/30 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-400 px-4 py-3 rounded" role="status" aria-live="polite">
                {{ session('success') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mb-6 bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-400 px-4 py-3 rounded" role="alert" aria-live="assertive">
                {{ session('error') }}
            </div>
        @endif

        {{-- Add Author Button --}}
        @if (!$showForm)
            <div class="mb-6">
                <button
                    wire:click="openForm"
                    class="inline-flex items-center px-4 py-2 bg-[#8B0116] text-white rounded-lg hover:bg-[#6b000e] transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#8B0116] dark:focus:ring-offset-gray-900"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Neuen Autor hinzufügen
                </button>
            </div>
        @endif

        {{-- Add/Edit Form --}}
        @if ($showForm)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
                    {{ $editingId ? 'Autor bearbeiten' : 'Neuen Autor hinzufügen' }}
                </h2>

                <form wire:submit="save" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Name *
                            </label>
                            <input
                                type="text"
                                id="name"
                                wire:model="name"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-[#8B0116] focus:border-[#8B0116]"
                                placeholder="z.B. Oliver Fröhlich"
                            >
                            @error('name') <span class="text-red-600 dark:text-red-400 text-sm mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="pseudonym" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Pseudonym (optional)
                            </label>
                            <input
                                type="text"
                                id="pseudonym"
                                wire:model="pseudonym"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-[#8B0116] focus:border-[#8B0116]"
                                placeholder="z.B. Ian Rolf Hill"
                            >
                            @error('pseudonym') <span class="text-red-600 dark:text-red-400 text-sm mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="sort_order" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Sortierung
                            </label>
                            <input
                                type="number"
                                id="sort_order"
                                wire:model="sort_order"
                                min="0"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:ring-[#8B0116] focus:border-[#8B0116]"
                            >
                            @error('sort_order') <span class="text-red-600 dark:text-red-400 text-sm mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex items-center pt-6">
                            <label class="flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    wire:model="is_active"
                                    class="w-5 h-5 text-[#8B0116] border-gray-300 dark:border-gray-600 rounded focus:ring-[#8B0116] dark:bg-gray-700"
                                >
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Auf der Anmeldeseite anzeigen</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex gap-2 pt-4">
                        <button
                            type="submit"
                            class="inline-flex items-center px-4 py-2 bg-[#8B0116] text-white rounded-lg hover:bg-[#6b000e] transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#8B0116] dark:focus:ring-offset-gray-800"
                        >
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ $editingId ? 'Aktualisieren' : 'Hinzufügen' }}
                        </button>
                        <button
                            type="button"
                            wire:click="closeForm"
                            class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors"
                        >
                            Abbrechen
                        </button>
                    </div>
                </form>
            </div>
        @endif

        {{-- Authors List --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    VIP-Autoren ({{ $authors->count() }})
                </h2>
            </div>

            @if ($authors->isEmpty())
                <div class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                    <svg class="w-12 h-12 mx-auto mb-4 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <p class="text-lg font-medium">Noch keine VIP-Autoren</p>
                    <p class="mt-1">Füge den ersten Autor hinzu, um ihn auf der Anmeldeseite anzukündigen.</p>
                </div>
            @else
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($authors as $author)
                        <li class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="flex items-center justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm text-gray-500 dark:text-gray-400 font-mono w-8">
                                            #{{ $author->sort_order }}
                                        </span>
                                        <div>
                                            <p class="text-base font-medium text-gray-900 dark:text-white truncate">
                                                {{ $author->name }}
                                            </p>
                                            @if ($author->pseudonym)
                                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                                    Pseudonym: {{ $author->pseudonym }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    {{-- Status Badge --}}
                                    <button
                                        wire:click="toggleActive({{ $author->id }})"
                                        class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full transition-colors {{ $author->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 hover:bg-green-200 dark:hover:bg-green-900/50' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-600' }}"
                                        title="{{ $author->is_active ? 'Klicken zum Deaktivieren' : 'Klicken zum Aktivieren' }}"
                                    >
                                        {{ $author->is_active ? 'Aktiv' : 'Inaktiv' }}
                                    </button>

                                    {{-- Move Up --}}
                                    <button
                                        wire:click="moveUp({{ $author->id }})"
                                        class="p-1 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors"
                                        title="Nach oben"
                                        @if($author->sort_order <= 0) disabled @endif
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                        </svg>
                                    </button>

                                    {{-- Move Down --}}
                                    <button
                                        wire:click="moveDown({{ $author->id }})"
                                        class="p-1 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors"
                                        title="Nach unten"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>

                                    {{-- Edit Button --}}
                                    <button
                                        wire:click="edit({{ $author->id }})"
                                        class="p-1 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors"
                                        title="Bearbeiten"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>

                                    {{-- Delete Button --}}
                                    <button
                                        wire:click="delete({{ $author->id }})"
                                        wire:confirm="Möchtest du den Autor &quot;{{ $author->name }}&quot; wirklich löschen?"
                                        class="p-1 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition-colors"
                                        title="Löschen"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Preview Info --}}
        @if ($authors->where('is_active', true)->isNotEmpty())
            <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                <h3 class="font-semibold text-blue-900 dark:text-blue-300 mb-2">Vorschau auf der Anmeldeseite</h3>
                <p class="text-sm text-blue-800 dark:text-blue-400 mb-2">
                    Diese Autoren werden prominent auf der Anmeldeseite angezeigt:
                </p>
                <ul class="text-sm text-blue-700 dark:text-blue-300 list-disc list-inside">
                    @foreach ($authors->where('is_active', true) as $author)
                        <li>{{ $author->display_name }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
