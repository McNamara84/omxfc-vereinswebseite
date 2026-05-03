<x-member-page class="max-w-7xl space-y-8">
    <x-ui.page-header
        eyebrow="Sammler-Tools"
        title="3D-Modelle"
        description="Durchsuche freigeschaltete Modelle, behalte dein Baxx-Guthaben im Blick und springe direkt in Vorschau, Download oder Upload."
        data-testid="page-header"
    >
        <x-slot:actions>
            <div class="flex flex-col gap-3 lg:items-end">
                <div class="flex flex-wrap gap-2">
                    <span class="badge badge-outline rounded-full px-3 py-3">{{ $this->models->count() }} Modelle</span>
                    @if ($this->walletWarning)
                        <span class="badge badge-warning badge-outline rounded-full px-3 py-3">Baxx-Guthaben wird geprüft</span>
                    @else
                        <span class="badge badge-primary badge-outline rounded-full px-3 py-3">{{ $this->availableBaxx }} Baxx verfügbar</span>
                    @endif
                </div>

                @can('create', App\Models\ThreeDModel::class)
                    <x-button label="Hochladen" icon="o-plus" link="{{ route('3d-modelle.create') }}" wire:navigate class="btn-primary" data-testid="upload-button" />
                @endcan
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('success'))
        <x-alert icon="o-check-circle" class="alert-success mb-4" dismissible>
            {{ session('success') }}
        </x-alert>
    @endif

    @if ($this->walletWarning)
        <x-alert icon="o-exclamation-triangle" class="alert-warning mb-4" dismissible>
            {{ $this->walletWarning }}
        </x-alert>
    @endif

    @if ($this->models->isEmpty())
        <x-ui.panel title="Noch keine 3D-Modelle vorhanden" description="Sobald erste Modelle hochgeladen wurden, erscheinen sie hier als eigenständige Karten mit Preis, Format und Vorschau.">
            <div class="rounded-[1.5rem] border border-dashed border-base-content/15 bg-base-100/70 px-6 py-8 text-center text-sm leading-relaxed text-base-content/62 sm:text-base">
                Noch keine 3D-Modelle vorhanden.
            </div>
        </x-ui.panel>
    @else
        <x-ui.panel title="Modell-Bibliothek" description="Alle 3D-Modelle sind als Karten mit Vorschau, Dateiformat und Baxx-Status organisiert.">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->models as $model)
                    @php $unlocked = in_array($model->id, $this->unlockedModelIds); @endphp

                    <article class="relative overflow-hidden rounded-[1.75rem] border border-base-content/10 bg-base-100/80 shadow-sm shadow-base-content/5 transition hover:-translate-y-1 hover:shadow-lg {{ $unlocked ? '' : 'opacity-55' }}" data-testid="model-card">
                        <div class="aspect-video overflow-hidden bg-base-200">
                            @if ($model->thumbnail_url)
                                <img src="{{ $model->thumbnail_url }}" alt="{{ $model->name }}" class="h-full w-full object-cover" loading="lazy" />
                            @else
                                <div class="flex h-full items-center justify-center text-base-content/28">
                                    <x-icon name="o-cube" class="h-16 w-16" />
                                </div>
                            @endif
                        </div>

                        <div class="space-y-4 px-5 py-5">
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-display text-xl font-semibold tracking-tight text-base-content">{{ $model->name }}</h3>
                                    <span class="badge badge-outline rounded-full uppercase">{{ strtoupper($model->file_format) }}</span>
                                </div>
                                <p class="line-clamp-3 text-sm leading-relaxed text-base-content/68">{{ $model->description }}</p>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-3">
                                @if ($model->reward)
                                    <x-badge :value="$model->reward->cost_baxx . ' Baxx'" class="{{ $unlocked ? 'badge-success' : 'badge-ghost' }}" icon="o-currency-dollar" />
                                @else
                                    <x-badge value="Kostenlos" class="badge-success" icon="o-gift" />
                                @endif

                                <x-button label="Ansehen" icon="o-eye" link="{{ route('3d-modelle.show', $model) }}" wire:navigate class="btn-sm btn-outline" />
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </x-ui.panel>
    @endif
</x-member-page>
