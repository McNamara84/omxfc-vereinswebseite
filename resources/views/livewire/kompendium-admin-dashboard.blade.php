<div class="pb-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <x-header title="Kompendium-Administration" subtitle="Verwalte die Romantexte für die Kompendium-Volltextsuche." separator data-testid="page-header">
            <x-slot:actions>
                <x-button label="Zurück zum Kompendium" link="{{ route('kompendium.index') }}" icon="o-arrow-left" class="btn-ghost" />
            </x-slot:actions>
        </x-header>

        {{-- Flash Messages --}}
        @if (session('success'))
            <x-alert icon="o-check-circle" class="alert-success mb-4" dismissible>
                {{ session('success') }}
            </x-alert>
        @endif
        @if (session('error'))
            <x-alert icon="o-exclamation-triangle" class="alert-error mb-4" dismissible>
                <span class="whitespace-pre-line">{{ session('error') }}</span>
            </x-alert>
        @endif
        @if (session('info'))
            <x-alert icon="o-information-circle" class="alert-info mb-4" dismissible>
                {{ session('info') }}
            </x-alert>
        @endif
        @if (session('warning'))
            <x-alert icon="o-exclamation-triangle" class="alert-warning mb-4" dismissible>
                {{ session('warning') }}
            </x-alert>
        @endif

        {{-- Statistik-Karten --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6" wire:poll.5s data-testid="stats-section">
            <x-stat title="Gesamt" :value="$this->statistiken['gesamt']" icon="o-document-text" />
            <x-stat title="Indexiert" :value="$this->statistiken['indexiert']" icon="o-check-circle" color="text-success" />
            <x-stat title="Hochgeladen" :value="$this->statistiken['hochgeladen']" icon="o-cloud-arrow-up" color="text-info" />
            <x-stat title="In Bearbeitung" :value="$this->statistiken['in_bearbeitung']" icon="o-arrow-path" color="text-warning" />
            <x-stat title="Fehler" :value="$this->statistiken['fehler']" icon="o-x-circle" color="text-error" />
        </div>

        {{-- Fortschritts-Dashboard --}}
        <x-card title="Indexierungs-Fortschritt" class="mb-6" shadow data-testid="progress-section">
            <div class="space-y-2">
                @forelse($this->zyklenFortschritt as $eintrag)
                    <div class="flex items-center gap-3">
                        <span class="w-28 text-sm truncate font-medium" title="{{ $eintrag['zyklus'] }}">
                            {{ $eintrag['zyklus'] }}
                        </span>
                        <div class="flex-1 bg-base-200 rounded-full h-4 overflow-hidden"
                            role="progressbar"
                            aria-valuenow="{{ $eintrag['prozent'] }}"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-label="{{ $eintrag['zyklus'] }}: {{ $eintrag['ist'] }} von {{ $eintrag['soll'] }} indexiert ({{ $eintrag['prozent'] }}%)">
                            <div
                                @class([
                                    'h-4 rounded-full transition-all duration-500',
                                    'bg-success' => $eintrag['status'] === 'vollstaendig',
                                    'bg-warning' => $eintrag['status'] === 'teilweise',
                                    'bg-base-300' => $eintrag['status'] === 'leer',
                                ])
                                style="width: {{ min((float) $eintrag['prozent'], 100) }}%">
                            </div>
                        </div>
                        <span class="w-16 text-xs text-right tabular-nums">
                            {{ $eintrag['ist'] }}/{{ $eintrag['soll'] }}
                        </span>
                        <span class="w-12 text-xs text-right tabular-nums text-base-content/60">
                            {{ $eintrag['prozent'] }}%
                        </span>
                    </div>
                @empty
                    <p class="text-base-content/60 text-sm text-center py-4">
                        Noch keine Seriendaten verfügbar. Bitte Romane hochladen.
                    </p>
                @endforelse
            </div>
        </x-card>

        {{-- Upload-Bereich --}}
        <x-card title="Romane hochladen" class="mb-6" shadow data-testid="upload-card">
            <form wire:submit="hochladen">
                <div class="grid md:grid-cols-2 gap-4 mb-4">
                    {{-- Serien-Auswahl --}}
                    @php
                        $serienOptions = collect($this->serien)->map(fn($name, $key) => ['id' => $key, 'name' => $name])->values()->toArray();
                    @endphp
                    <x-select
                        label="Serie (falls nicht automatisch erkannt)"
                        :options="$serienOptions"
                        wire:model="ausgewaehlteSerie"
                        hint="Die Serie wird automatisch erkannt. Fuzzy-Match gleicht auch abweichende Titel ab." />

                    {{-- Drag & Drop Upload-Zone --}}
                    <div
                        x-data="{ dragging: false }"
                        x-on:dragover.prevent="dragging = true"
                        x-on:dragleave.prevent="dragging = false"
                        x-on:drop.prevent="
                            dragging = false;
                            const files = $event.dataTransfer.files;
                            @this.uploadMultiple('uploads', files);
                        "
                        :class="{ 'border-primary bg-primary/5': dragging, 'border-base-300': !dragging }"
                        class="border-2 border-dashed rounded-lg p-6 text-center transition-colors"
                        data-testid="drop-zone">
                        <x-icon name="o-cloud-arrow-up" class="w-8 h-8 mx-auto mb-2 opacity-50" />
                        <p class="text-sm text-base-content/70 mb-2">TXT-Dateien hierher ziehen</p>
                        <x-file
                            label=""
                            wire:model="uploads"
                            multiple
                            accept=".txt"
                            hint="Format: 001 - Der Gott aus dem Eis.txt" />
                        @error('uploads.*') <span class="text-error text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Upload-Preview --}}
                @if(count($uploads) > 0)
                    <x-alert icon="o-document-text" class="alert-info mb-4">
                        <div>
                            <p class="font-medium mb-2">{{ count($uploads) }} Datei(en) ausgewählt:</p>
                            <ul class="text-sm space-y-1">
                                @foreach($uploads as $upload)
                                    <li class="flex items-center">
                                        <x-icon name="o-document" class="w-4 h-4 mr-2" />
                                        {{ $upload->getClientOriginalName() }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </x-alert>
                @endif

                <x-button
                    label="Hochladen"
                    type="submit"
                    icon="o-cloud-arrow-up"
                    class="btn-primary"
                    spinner="hochladen"
                    :disabled="count($uploads) === 0" />
            </form>
        </x-card>

        {{-- Serien-Tabs --}}
        <div class="flex flex-wrap gap-1 mb-4" role="tablist" data-testid="series-tabs">
            @foreach($this->serien as $key => $name)
                <button
                    wire:click="$set('filterSerie', '{{ $key }}')"
                    role="tab"
                    aria-selected="{{ $filterSerie === $key ? 'true' : 'false' }}"
                    @class([
                        'btn btn-sm',
                        'btn-primary' => $filterSerie === $key,
                        'btn-ghost' => $filterSerie !== $key,
                    ])
                    data-testid="tab-{{ $key }}">
                    {{ $name }}
                    @if(isset($this->romanZahlenProSerie[$key]))
                        <x-badge :value="$this->romanZahlenProSerie[$key]" class="badge-sm ml-1" />
                    @endif
                </button>
            @endforeach
        </div>

        {{-- Filter & Aktionen --}}
        <x-card class="mb-6" shadow data-testid="filter-section">
            <div class="flex flex-wrap items-end gap-4">
                {{-- Suche --}}
                <div class="flex-1 min-w-[200px]">
                    <x-input
                        label="Suche"
                        wire:model.live.debounce.300ms="suchbegriff"
                        placeholder="Titel oder Nummer..."
                        icon="o-magnifying-glass"
                        data-testid="search-input" />
                </div>

                {{-- Status-Filter --}}
                @php
                    $statusOptions = [
                        ['id' => '', 'name' => 'Alle Status'],
                        ['id' => 'hochgeladen', 'name' => 'Hochgeladen'],
                        ['id' => 'indexiert', 'name' => 'Indexiert'],
                        ['id' => 'indexierung_laeuft', 'name' => 'In Bearbeitung'],
                        ['id' => 'fehler', 'name' => 'Fehler'],
                    ];
                @endphp
                <div class="min-w-[150px]">
                    <x-select
                        label="Status"
                        :options="$statusOptions"
                        wire:model.live="filterStatus"
                        data-testid="status-filter" />
                </div>

                {{-- Massen-Aktionen --}}
                <div class="flex gap-2">
                    <x-button
                        label="Alle indexieren"
                        wire:click="alleIndexieren"
                        wire:confirm="Alle nicht-indexierten Romane indexieren?"
                        class="btn-success btn-sm" />
                    <x-button
                        label="Alle de-indexieren"
                        wire:click="alleDeIndexieren"
                        wire:confirm="Alle indexierten Romane de-indexieren?"
                        class="btn-warning btn-sm" />
                </div>
            </div>
        </x-card>

        {{-- Romanliste --}}
        <x-card shadow data-testid="novels-table-card" wire:poll.5s>
            <div class="overflow-x-auto">
                <table class="table" data-testid="novels-table">
                    <thead>
                        <tr>
                            <th>Nr.</th>
                            <th>Titel</th>
                            <th>Zyklus</th>
                            <th>Status</th>
                            <th>Hochgeladen</th>
                            <th class="text-right">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->romane as $roman)
                            <tr class="hover">
                                <td class="font-mono">
                                    {{ str_pad($roman->roman_nr, 3, '0', STR_PAD_LEFT) }}
                                </td>
                                <td>
                                    {{ $roman->titel }}
                                    @if($roman->fehler_nachricht)
                                        <span class="block text-xs text-error mt-1" title="{{ $roman->fehler_nachricht }}">
                                            ⚠️ {{ \Illuminate\Support\Str::limit($roman->fehler_nachricht, 50) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-base-content">
                                    {{ $roman->zyklus ?? '-' }}
                                </td>
                                <td>
                                    @switch($roman->status)
                                        @case('indexiert')
                                            <x-badge value="✓ Indexiert" class="badge-success" />
                                            @break
                                        @case('hochgeladen')
                                            <x-badge value="○ Hochgeladen" class="badge-info" />
                                            @break
                                        @case('indexierung_laeuft')
                                            <x-badge value="⟳ Läuft..." class="badge-warning animate-pulse" />
                                            @break
                                        @case('fehler')
                                            <x-badge value="✗ Fehler" class="badge-error" />
                                            @break
                                    @endswitch
                                </td>
                                <td class="text-base-content">
                                    {{ $roman->hochgeladen_am->format('d.m.Y H:i') }}
                                </td>
                                <td class="text-right space-x-1">
                                    {{-- Bearbeiten (immer außer bei laufender Indexierung) --}}
                                    @if($roman->status !== 'indexierung_laeuft')
                                        <x-button
                                            wire:click="bearbeiten({{ $roman->id }})"
                                            icon="o-pencil-square"
                                            class="btn-ghost btn-xs text-info"
                                            title="Bearbeiten"
                                            data-testid="edit-btn-{{ $roman->id }}" />
                                    @endif

                                    @if($roman->status === 'hochgeladen')
                                        <x-button
                                            wire:click="indexieren({{ $roman->id }})"
                                            icon="o-check-circle"
                                            class="btn-ghost btn-xs text-success"
                                            title="Indexieren" />
                                    @elseif($roman->status === 'indexiert')
                                        <x-button
                                            wire:click="deIndexieren({{ $roman->id }})"
                                            icon="o-x-circle"
                                            class="btn-ghost btn-xs text-warning"
                                            title="De-Indexieren" />
                                    @elseif($roman->status === 'fehler')
                                        <x-button
                                            wire:click="retryFehler({{ $roman->id }})"
                                            icon="o-arrow-path"
                                            class="btn-ghost btn-xs text-info"
                                            title="Erneut versuchen" />
                                    @endif

                                    @if($roman->status !== 'indexierung_laeuft')
                                        <x-button
                                            wire:click="loeschen({{ $roman->id }})"
                                            wire:confirm="Roman '{{ $roman->titel }}' wirklich löschen? Die Datei wird ebenfalls entfernt."
                                            icon="o-trash"
                                            class="btn-ghost btn-xs text-error"
                                            title="Löschen" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-8 text-base-content">
                                    @if($filterStatus || $suchbegriff)
                                        <x-icon name="o-funnel" class="w-12 h-12 mx-auto mb-2 opacity-30" />
                                        Keine Romane gefunden, die den Filterkriterien entsprechen.
                                    @else
                                        <x-icon name="o-document-plus" class="w-12 h-12 mx-auto mb-2 opacity-30" />
                                        Noch keine Romane in dieser Serie hochgeladen. Nutze das Upload-Formular oben, um TXT-Dateien hinzuzufügen.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($this->romane->hasPages())
                <div class="mt-4 pt-4 border-t border-base-200">
                    {{ $this->romane->links() }}
                </div>
            @endif
        </x-card>

        {{-- Hinweis zur Verarbeitung --}}
        @if($this->statistiken['in_bearbeitung'] > 0)
            <x-alert icon="o-arrow-path" class="alert-info mt-4">
                <strong>{{ $this->statistiken['in_bearbeitung'] }}</strong> Roman(e) werden gerade indexiert.
                Diese Seite aktualisiert sich automatisch alle 5 Sekunden.
            </x-alert>
        @endif

        {{-- Edit-Modal --}}
        <x-modal wire:model="showEditModal" title="Roman bearbeiten" separator data-testid="edit-modal">
            <div class="space-y-4">
                @php
                    $editSerienOptions = collect($this->serien)->map(fn($name, $key) => ['id' => $key, 'name' => $name])->values()->toArray();
                @endphp
                <x-select
                    label="Serie"
                    :options="$editSerienOptions"
                    wire:model="editSerie"
                    data-testid="edit-serie" />

                <x-input
                    label="Zyklus"
                    wire:model="editZyklus"
                    placeholder="z.B. Euree, Meeraka..."
                    hint="Leer lassen falls Miniserie ohne Zyklen"
                    data-testid="edit-zyklus" />

                <x-input
                    label="Roman-Nummer"
                    type="number"
                    wire:model="editNummer"
                    min="1"
                    data-testid="edit-nummer" />

                <x-input
                    label="Titel"
                    wire:model="editTitel"
                    data-testid="edit-titel" />

                @error('editTitel')
                    <span class="text-error text-sm">{{ $message }}</span>
                @enderror
            </div>

            <x-slot:actions>
                <x-button label="Abbrechen" @click="$wire.showEditModal = false" />
                <x-button label="Speichern" wire:click="speichern" class="btn-primary" spinner="speichern" data-testid="edit-save" />
            </x-slot:actions>
        </x-modal>
    </div>
</div>
