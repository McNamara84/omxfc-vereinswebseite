<x-member-page class="max-w-6xl space-y-8">
    <x-ui.page-header
        :title="$this->model->name"
        eyebrow="3D-Bibliothek"
        description="Vorschau, Freischaltung und Modellinformationen sind hier in einer gemeinsamen Detailansicht gebündelt."
        data-testid="page-header"
    >
        <x-slot:actions>
            <div class="flex flex-wrap gap-2 lg:justify-end">
                <x-button label="Zurück" icon="o-arrow-left" link="{{ route('3d-modelle.index') }}" wire:navigate class="btn-ghost" />

                @if ($this->isUnlocked)
                    <x-button label="Herunterladen" icon="o-arrow-down-tray" link="{{ route('3d-modelle.download', $this->model) }}" class="btn-primary" data-testid="download-button" />
                @endif

                @if ($this->canManage)
                    <x-button label="Bearbeiten" icon="o-pencil" link="{{ route('3d-modelle.edit', $this->model) }}" wire:navigate class="btn-warning btn-sm" />
                @endif
            </div>
        </x-slot:actions>
    </x-ui.page-header>

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

    @if ($this->isUnlocked)
        <x-ui.panel title="3D-Vorschau" description="Das Modell wird direkt aus der privaten Dateiablage gestreamt und kann ohne Download betrachtet werden.">
            <div data-three-d-viewer data-file-url="{{ route('3d-modelle.preview', $this->model) }}" data-format="{{ $this->model->file_format }}" class="aspect-video w-full overflow-hidden rounded-[1.5rem] border border-base-content/10 bg-base-100/80" data-testid="three-d-viewer">
            </div>
        </x-ui.panel>
    @else
        <x-ui.panel title="Freischaltung" description="Freie Modelle lassen sich sofort ansehen. Gesperrte Modelle werden über Baxx freigeschaltet.">
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
        </x-ui.panel>
    @endif

    <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,0.78fr)] xl:items-start">
        <x-ui.panel title="Details" description="Format, Dateigröße, Preis und inhaltliche Einordnung des Modells." data-testid="model-details">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/78 px-4 py-3">
                    <span class="text-sm text-base-content/60">Format</span>
                    <p class="mt-1 font-semibold text-base-content">{{ strtoupper($this->model->file_format) }}</p>
                </div>
                <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/78 px-4 py-3">
                    <span class="text-sm text-base-content/60">Dateigröße</span>
                    <p class="mt-1 font-semibold text-base-content">{{ $this->model->file_size_formatted }}</p>
                </div>
                <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/78 px-4 py-3">
                    <span class="text-sm text-base-content/60">Preis</span>
                    <div class="mt-2">
                        @if ($this->model->reward)
                            <x-badge :value="$this->model->reward->cost_baxx . ' Baxx'" class="{{ $this->isUnlocked ? 'badge-success' : 'badge-ghost' }}" icon="o-currency-dollar" />
                        @else
                            <x-badge value="Kostenlos" class="badge-success" icon="o-gift" />
                        @endif
                    </div>
                </div>
                <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/78 px-4 py-3">
                    <span class="text-sm text-base-content/60">Hochgeladen von</span>
                    <p class="mt-1 font-semibold text-base-content">{{ $this->model->uploader->name }}</p>
                </div>
            </div>

            <div class="mt-4 rounded-[1.25rem] border border-base-content/10 bg-base-100/78 px-4 py-4">
                <span class="text-sm text-base-content/60">Beschreibung</span>
                <p class="mt-2 text-sm leading-relaxed text-base-content/78 sm:text-base">{{ $this->model->description }}</p>
            </div>

            @if ($this->model->maddraxikon_url)
                <div class="mt-4 rounded-[1.25rem] border border-base-content/10 bg-base-100/78 px-4 py-4">
                    <span class="text-sm text-base-content/60">Maddraxikon</span>
                    <p class="mt-2">
                        <a href="{{ $this->model->maddraxikon_url }}" target="_blank" rel="noopener noreferrer" class="link link-primary inline-flex items-center gap-1" data-testid="maddraxikon-link">
                            <x-icon name="o-arrow-top-right-on-square" class="w-4 h-4" />
                            Im Maddraxikon ansehen
                        </a>
                    </p>
                </div>
            @endif
        </x-ui.panel>

        <div class="space-y-6 xl:sticky xl:top-6">
            <x-ui.panel title="Zugriff" description="Ob Vorschau und Download direkt verfügbar sind, hängt von Freischaltung und Reward-Status ab.">
                <div class="grid gap-3 text-sm leading-relaxed text-base-content/76 sm:text-base">
                    <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                        <p class="font-medium text-base-content">Verfügbarkeit</p>
                        <p class="mt-1">{{ $this->isUnlocked ? 'Dieses Modell ist für dich freigeschaltet.' : 'Dieses Modell ist noch nicht freigeschaltet.' }}</p>
                    </div>
                    <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                        <p class="font-medium text-base-content">Baxx-Guthaben</p>
                        <p class="mt-1">Aktuell verfügbar: {{ $this->availableBaxx }} Baxx.</p>
                    </div>
                </div>
            </x-ui.panel>

            @if ($this->canManage)
                <x-ui.panel title="Verwaltung" description="Administrative Aktionen für Upload, Pflege und Entfernung des Modells.">
                    <x-button label="Löschen" icon="o-trash" wire:click="$set('confirmingDelete', true)" class="btn-error btn-sm" data-testid="delete-button" />
                </x-ui.panel>
            @endif
        </div>
    </section>

    @if ($this->canManage)
        <x-modal wire:model="confirmingDelete" title="3D-Modell löschen">
            <p>Soll dieses 3D-Modell wirklich gelöscht werden?</p>
            <x-slot:actions>
                <x-button label="Abbrechen" wire:click="$set('confirmingDelete', false)" class="btn-ghost" />
                <x-button label="Endgültig löschen" wire:click="deleteModel" class="btn-error" spinner="deleteModel" />
            </x-slot:actions>
        </x-modal>
    @endif
</x-member-page>
