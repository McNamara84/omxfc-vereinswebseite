<div class="pb-8" wire:poll.5s>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-[#ff4b63]">
                Kompendium-Administration
            </h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                Verwalte die Romantexte für die Kompendium-Volltextsuche.
            </p>
        </div>

        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 border-l-4 border-green-500 text-green-700 dark:text-green-300 rounded">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-4 p-4 bg-red-100 dark:bg-red-900 border-l-4 border-red-500 text-red-700 dark:text-red-300 rounded whitespace-pre-line">
                {{ session('error') }}
            </div>
        @endif
        @if (session('info'))
            <div class="mb-4 p-4 bg-blue-100 dark:bg-blue-900 border-l-4 border-blue-500 text-blue-700 dark:text-blue-300 rounded">
                {{ session('info') }}
            </div>
        @endif
        @if (session('warning'))
            <div class="mb-4 p-4 bg-yellow-100 dark:bg-yellow-900 border-l-4 border-yellow-500 text-yellow-700 dark:text-yellow-300 rounded">
                {{ session('warning') }}
            </div>
        @endif

        {{-- Statistik-Karten --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->statistiken['gesamt'] }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Gesamt</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $this->statistiken['indexiert'] }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Indexiert</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $this->statistiken['hochgeladen'] }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Hochgeladen</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $this->statistiken['in_bearbeitung'] }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">In Bearbeitung</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $this->statistiken['fehler'] }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Fehler</div>
            </div>
        </div>

        {{-- Upload-Bereich --}}
        <div class="bg-white dark:bg-gray-800 shadow-xl rounded-lg p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                Romane hochladen
            </h2>

            <form wire:submit="hochladen">
                <div class="grid md:grid-cols-2 gap-4 mb-4">
                    {{-- Serien-Auswahl --}}
                    <div>
                        <label for="serie" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Serie (falls nicht automatisch erkannt)
                        </label>
                        <select id="serie" wire:model="ausgewaehlteSerie"
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-[#8B0116] focus:ring-[#8B0116]">
                            @foreach($this->serien as $key => $name)
                                <option value="{{ $key }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Die Serie wird automatisch erkannt, wenn der Roman in der Datenbank existiert.
                        </p>
                    </div>

                    {{-- Datei-Upload --}}
                    <div>
                        <label for="uploads" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            TXT-Dateien auswählen
                        </label>
                        <input type="file" id="uploads" wire:model="uploads" multiple accept=".txt"
                               class="w-full text-sm text-gray-500 dark:text-gray-400
                                      file:mr-4 file:py-2 file:px-4
                                      file:rounded-md file:border-0
                                      file:text-sm file:font-semibold
                                      file:bg-[#8B0116] file:text-white
                                      hover:file:bg-[#6d0111]
                                      dark:file:bg-[#ff4b63] dark:file:text-gray-900">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Format: <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">001 - Der Gott aus dem Eis.txt</code>
                        </p>
                        @error('uploads.*') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Upload-Preview --}}
                @if(count($uploads) > 0)
                    <div class="mb-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ count($uploads) }} Datei(en) ausgewählt:
                        </p>
                        <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                            @foreach($uploads as $upload)
                                <li class="flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    {{ $upload->getClientOriginalName() }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <button type="submit"
                        class="px-4 py-2 bg-[#8B0116] text-white rounded-md hover:bg-[#6d0111] focus:outline-none focus:ring-2 focus:ring-[#8B0116] focus:ring-offset-2 disabled:opacity-50"
                        wire:loading.attr="disabled"
                        @if(count($uploads) === 0) disabled @endif>
                    <span wire:loading.remove wire:target="hochladen">Hochladen</span>
                    <span wire:loading wire:target="hochladen">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Wird hochgeladen...
                    </span>
                </button>
            </form>
        </div>

        {{-- Filter & Aktionen --}}
        <div class="bg-white dark:bg-gray-800 shadow-xl rounded-lg p-6 mb-6">
            <div class="flex flex-wrap items-end gap-4">
                {{-- Suche --}}
                <div class="flex-1 min-w-[200px]">
                    <label for="suchbegriff" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Suche
                    </label>
                    <input type="text" id="suchbegriff" wire:model.live.debounce.300ms="suchbegriff"
                           placeholder="Titel oder Nummer..."
                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-[#8B0116] focus:ring-[#8B0116]">
                </div>

                {{-- Serie-Filter --}}
                <div class="min-w-[180px]">
                    <label for="filterSerie" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Serie
                    </label>
                    <select id="filterSerie" wire:model.live="filterSerie"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-[#8B0116] focus:ring-[#8B0116]">
                        <option value="">Alle Serien</option>
                        @foreach($this->serien as $key => $name)
                            <option value="{{ $key }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Status-Filter --}}
                <div class="min-w-[150px]">
                    <label for="filterStatus" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Status
                    </label>
                    <select id="filterStatus" wire:model.live="filterStatus"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-[#8B0116] focus:ring-[#8B0116]">
                        <option value="">Alle Status</option>
                        <option value="hochgeladen">Hochgeladen</option>
                        <option value="indexiert">Indexiert</option>
                        <option value="indexierung_laeuft">In Bearbeitung</option>
                        <option value="fehler">Fehler</option>
                    </select>
                </div>

                {{-- Massen-Aktionen --}}
                <div class="flex gap-2">
                    <button wire:click="alleIndexieren"
                            wire:confirm="Alle nicht-indexierten Romane indexieren?"
                            class="px-3 py-2 text-sm bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                        Alle indexieren
                    </button>
                    <button wire:click="alleDeIndexieren"
                            wire:confirm="Alle indexierten Romane de-indexieren?"
                            class="px-3 py-2 text-sm bg-yellow-600 text-white rounded-md hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500">
                        Alle de-indexieren
                    </button>
                </div>
            </div>
        </div>

        {{-- Romanliste --}}
        <div class="bg-white dark:bg-gray-800 shadow-xl rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nr.</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Titel</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Serie</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Zyklus</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Hochgeladen</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($this->romane as $roman)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white font-mono">
                                    {{ str_pad($roman->roman_nr, 3, '0', STR_PAD_LEFT) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                    {{ $roman->titel }}
                                    @if($roman->fehler_nachricht)
                                        <span class="block text-xs text-red-500 mt-1" title="{{ $roman->fehler_nachricht }}">
                                            ⚠️ {{ \Illuminate\Support\Str::limit($roman->fehler_nachricht, 50) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $this->serien[$roman->serie] ?? $roman->serie }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $roman->zyklus ?? '-' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @switch($roman->status)
                                        @case('indexiert')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                ✓ Indexiert
                                            </span>
                                            @break
                                        @case('hochgeladen')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                ○ Hochgeladen
                                            </span>
                                            @break
                                        @case('indexierung_laeuft')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 animate-pulse">
                                                ⟳ Läuft...
                                            </span>
                                            @break
                                        @case('fehler')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                ✗ Fehler
                                            </span>
                                            @break
                                    @endswitch
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $roman->hochgeladen_am->format('d.m.Y H:i') }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium space-x-1">
                                    @if($roman->status === 'hochgeladen')
                                        <button wire:click="indexieren({{ $roman->id }})"
                                                class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                                                title="Indexieren">
                                            <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </button>
                                    @elseif($roman->status === 'indexiert')
                                        <button wire:click="deIndexieren({{ $roman->id }})"
                                                class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300"
                                                title="De-Indexieren">
                                            <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </button>
                                    @elseif($roman->status === 'fehler')
                                        <button wire:click="retryFehler({{ $roman->id }})"
                                                class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                                title="Erneut versuchen">
                                            <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                            </svg>
                                        </button>
                                    @endif

                                    @if($roman->status !== 'indexierung_laeuft')
                                        <button wire:click="loeschen({{ $roman->id }})"
                                                wire:confirm="Roman '{{ $roman->titel }}' wirklich löschen? Die Datei wird ebenfalls entfernt."
                                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                                title="Löschen">
                                            <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    @if($filterSerie || $filterStatus || $suchbegriff)
                                        Keine Romane gefunden, die den Filterkriterien entsprechen.
                                    @else
                                        Noch keine Romane hochgeladen. Nutze das Upload-Formular oben, um TXT-Dateien hinzuzufügen.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($this->romane->hasPages())
                <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                    {{ $this->romane->links() }}
                </div>
            @endif
        </div>

        {{-- Hinweis zur Verarbeitung --}}
        @if($this->statistiken['in_bearbeitung'] > 0)
            <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/50 rounded-lg text-sm text-blue-700 dark:text-blue-300">
                <div class="flex items-center">
                    <svg class="animate-spin h-5 w-5 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>
                        <strong>{{ $this->statistiken['in_bearbeitung'] }}</strong> Roman(e) werden gerade indexiert.
                        Diese Seite aktualisiert sich automatisch alle 5 Sekunden.
                    </span>
                </div>
            </div>
        @endif

        {{-- Link zurück zum Kompendium --}}
        <div class="mt-6">
            <a href="{{ route('kompendium.index') }}" class="text-[#8B0116] dark:text-[#ff4b63] hover:underline">
                ← Zurück zum Kompendium
            </a>
        </div>
    </div>
</div>
