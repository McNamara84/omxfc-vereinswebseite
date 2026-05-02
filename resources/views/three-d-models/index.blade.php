<x-app-layout>
    <x-member-page class="max-w-6xl">
        <x-ui.page-header
            eyebrow="Sammlerbereich"
            title="3D-Modelle"
            description="Entdecke freischaltbare Modelle, prüfe dein Baxx-Guthaben und springe direkt in Upload oder Detailansicht."
            data-testid="page-header"
        >
            <x-slot:actions>
                <div class="flex items-center gap-2 text-sm text-base-content/70">
                    <span>Verfügbares Baxx-Guthaben</span>
                    <x-badge :value="$availableBaxx" class="badge-primary" icon="o-currency-dollar" />
                </div>

                @can('create', App\Models\ThreeDModel::class)
                    <x-button label="Hochladen" icon="o-plus" link="{{ route('3d-modelle.create') }}" wire:navigate
                        class="btn-primary" data-testid="upload-button" />
                @endcan
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('success'))
            <x-alert icon="o-check-circle" class="alert-success mb-4" dismissible>
                {{ session('success') }}
            </x-alert>
        @endif

        @if ($models->isEmpty())
            <x-ui.panel>
                <p class="text-center text-base-content/60">Noch keine 3D-Modelle vorhanden.</p>
            </x-ui.panel>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($models as $model)
                    @php $unlocked = in_array($model->id, $unlockedModelIds); @endphp
                    <x-ui.panel class="{{ $unlocked ? 'h-full' : 'h-full opacity-50' }}" data-testid="model-card">
                        <div class="flex h-full flex-col">
                            {{-- Thumbnail oder Platzhalter --}}
                            <div class="aspect-video bg-base-200 rounded-lg overflow-hidden flex items-center justify-center">
                                @if ($model->thumbnail_url)
                                    <img src="{{ $model->thumbnail_url }}" alt="{{ $model->name }}"
                                        class="w-full h-full object-cover" loading="lazy" />
                                @else
                                    <div class="text-4xl text-base-content/30">
                                        <x-icon name="o-cube" class="w-16 h-16" />
                                    </div>
                                @endif
                            </div>

                            <div class="mt-4 flex-1">
                                <h3 class="text-lg font-semibold tracking-tight text-base-content">{{ $model->name }}</h3>
                                <p class="mt-1 text-sm text-base-content/60 line-clamp-2">{{ $model->description }}</p>
                                <div class="mt-3 flex items-center justify-between">
                                    @if ($model->reward)
                                        <x-badge :value="$model->reward->cost_baxx . ' Baxx'"
                                            class="{{ $unlocked ? 'badge-success' : 'badge-ghost' }}" icon="o-currency-dollar" />
                                    @else
                                        <x-badge value="Kostenlos" class="badge-success" icon="o-gift" />
                                    @endif
                                    <span class="text-xs font-mono uppercase text-base-content/40">{{ strtoupper($model->file_format) }}</span>
                                </div>
                            </div>

                            <div class="mt-4 pt-4 border-t border-base-content/10">
                                <x-button label="Ansehen" icon="o-eye"
                                    link="{{ route('3d-modelle.show', $model) }}" wire:navigate
                                    class="btn-sm btn-outline" />
                            </div>
                        </div>
                    </x-ui.panel>
                @endforeach
            </div>
        @endif
    </x-member-page>
</x-app-layout>
