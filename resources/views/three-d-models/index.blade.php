<x-app-layout>
    <x-member-page class="max-w-6xl">
        <x-header title="3D-Modelle" separator data-testid="page-header">
            <x-slot:subtitle>
                Dein aktuelles Baxx-Guthaben: <x-badge :value="$userPoints" class="badge-primary" />
            </x-slot:subtitle>
            @can('create', App\Models\ThreeDModel::class)
                <x-slot:actions>
                    <x-button label="Hochladen" icon="o-plus" link="{{ route('3d-modelle.create') }}"
                        class="btn-primary" data-testid="upload-button" />
                </x-slot:actions>
            @endcan
        </x-header>

        @if (session('success'))
            <x-alert icon="o-check-circle" class="alert-success mb-4" dismissible>
                {{ session('success') }}
            </x-alert>
        @endif

        @if ($models->isEmpty())
            <x-card>
                <p class="text-center text-base-content/60">Noch keine 3D-Modelle vorhanden.</p>
            </x-card>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($models as $model)
                    @php $unlocked = $userPoints >= $model->required_baxx; @endphp
                    <x-card class="{{ $unlocked ? '' : 'opacity-50' }}" data-testid="model-card">
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

                        <div class="mt-3">
                            <h3 class="font-bold text-lg">{{ $model->name }}</h3>
                            <p class="text-sm text-base-content/60 mt-1 line-clamp-2">{{ $model->description }}</p>
                            <div class="flex items-center justify-between mt-3">
                                <x-badge :value="$model->required_baxx . ' Baxx'"
                                    class="{{ $unlocked ? 'badge-success' : 'badge-ghost' }}" />
                                <span class="text-xs text-base-content/40 uppercase font-mono">{{ strtoupper($model->file_format) }}</span>
                            </div>
                        </div>

                        <x-slot:actions>
                            <x-button label="Ansehen" icon="o-eye"
                                link="{{ route('3d-modelle.show', $model) }}"
                                class="btn-sm btn-outline" />
                        </x-slot:actions>
                    </x-card>
                @endforeach
            </div>
        @endif
    </x-member-page>
</x-app-layout>
