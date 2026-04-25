<x-member-page class="max-w-5xl">
    <x-header :title="$this->model->name" separator data-testid="page-header">
        <x-slot:actions>
            <x-button label="Zurück" icon="o-arrow-left" link="{{ route('3d-modelle.index') }}" wire:navigate
                class="btn-ghost" />

            @if ($this->isUnlocked)
                <x-button label="Herunterladen" icon="o-arrow-down-tray"
                    link="{{ route('3d-modelle.download', $this->model) }}"
                    class="btn-primary" data-testid="download-button" />
            @endif

            @if ($this->canManage)
                <x-button label="Bearbeiten" icon="o-pencil"
                    link="{{ route('3d-modelle.edit', $this->model) }}" wire:navigate
                    class="btn-warning btn-sm" />
            @endif
        </x-slot:actions>
    </x-header>

    @if (session('success'))
        <x-alert icon="o-check-circle" class="alert-success mb-4" dismissible>
            {{ session('success') }}
        </x-alert>
    @endif

    {{-- Fehlermeldungen (z.B. Kauf fehlgeschlagen, Download/Preview abgelehnt) --}}
    @error('reward')
        <x-alert icon="o-exclamation-triangle" class="alert-warning mb-4" data-testid="reward-error">
            {{ $message }}
        </x-alert>
    @enderror

    {{-- 3D-Viewer (nur wenn freigeschaltet) --}}
    @if ($this->isUnlocked)
        <div data-three-d-viewer
            data-file-url="{{ route('3d-modelle.preview', $this->model) }}"
            data-format="{{ $this->model->file_format }}"
            class="w-full aspect-video rounded-xl overflow-hidden border border-base-300 mb-6"
            data-testid="three-d-viewer">
        </div>
    @else
        <x-card class="mb-6">
            <div class="text-center py-12">
                <x-icon name="o-lock-closed" class="w-16 h-16 mx-auto text-base-content/30 mb-4" />
                @if ($this->model->reward)
                    <p class="text-lg font-semibold">Dieses Modell kostet {{ $this->model->reward->cost_baxx }} Baxx</p>
                    <p class="text-base-content/60 mt-1">
                        Du hast aktuell {{ $this->availableBaxx }} verfügbare Baxx.
                    </p>
                    @if (! $this->model->reward->is_active)
                        <p class="text-sm text-base-content/40 mt-2">
                            Dieses Modell ist derzeit nicht verfügbar.
                        </p>
                    @elseif ($this->availableBaxx >= $this->model->reward->cost_baxx)
                        <div class="mt-4">
                            <x-button label="Für {{ $this->model->reward->cost_baxx }} Baxx freischalten"
                                icon="o-lock-open" wire:click="purchase"
                                wire:confirm="Möchtest du dieses 3D-Modell für {{ $this->model->reward->cost_baxx }} Baxx freischalten?"
                                class="btn-primary"
                                spinner="purchase"
                                data-testid="purchase-button" />
                        </div>
                    @else
                        <p class="text-sm text-base-content/40 mt-2">
                            Dir fehlen noch {{ $this->model->reward->cost_baxx - $this->availableBaxx }} Baxx.
                        </p>
                    @endif
                @else
                    <p class="text-lg font-semibold">Dieses Modell ist nicht verfügbar</p>
                @endif
            </div>
        </x-card>
    @endif

    {{-- Metadaten --}}
    <x-card title="Details" data-testid="model-details">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <span class="text-sm text-base-content/60">Format</span>
                <p class="font-semibold">{{ strtoupper($this->model->file_format) }}</p>
            </div>
            <div>
                <span class="text-sm text-base-content/60">Dateigröße</span>
                <p class="font-semibold">{{ $this->model->file_size_formatted }}</p>
            </div>
            <div>
                <span class="text-sm text-base-content/60">Preis</span>
                <div class="mt-1">
                    @if ($this->model->reward)
                        <x-badge :value="$this->model->reward->cost_baxx . ' Baxx'"
                            class="{{ $this->isUnlocked ? 'badge-success' : 'badge-ghost' }}" icon="o-currency-dollar" />
                    @else
                        <x-badge value="Kostenlos" class="badge-success" icon="o-gift" />
                    @endif
                </div>
            </div>
            <div>
                <span class="text-sm text-base-content/60">Hochgeladen von</span>
                <p class="font-semibold">{{ $this->model->uploader->name }}</p>
            </div>
        </div>
        <div class="mt-4">
            <span class="text-sm text-base-content/60">Beschreibung</span>
            <p class="mt-1">{{ $this->model->description }}</p>
        </div>

        @if ($this->model->maddraxikon_url)
            <div class="mt-4">
                <span class="text-sm text-base-content/60">Maddraxikon</span>
                <p class="mt-1">
                    <a href="{{ $this->model->maddraxikon_url }}" target="_blank" rel="noopener noreferrer"
                        class="link link-primary inline-flex items-center gap-1"
                        data-testid="maddraxikon-link">
                        <x-icon name="o-arrow-top-right-on-square" class="w-4 h-4" />
                        Im Maddraxikon ansehen
                    </a>
                </p>
            </div>
        @endif

        @if ($this->canManage)
            <div class="mt-6 pt-4 border-t border-base-300">
                <x-button label="Löschen" icon="o-trash"
                    wire:click="$set('confirmingDelete', true)"
                    class="btn-error btn-sm" data-testid="delete-button" />

                <x-modal wire:model="confirmingDelete" title="3D-Modell löschen">
                    <p>Soll dieses 3D-Modell wirklich gelöscht werden?</p>
                    <x-slot:actions>
                        <x-button label="Abbrechen" wire:click="$set('confirmingDelete', false)" class="btn-ghost" />
                        <x-button label="Endgültig löschen" wire:click="deleteModel" class="btn-error" spinner="deleteModel" />
                    </x-slot:actions>
                </x-modal>
            </div>
        @endif
    </x-card>
</x-member-page>
