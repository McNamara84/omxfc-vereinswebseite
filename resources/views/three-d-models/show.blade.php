<x-app-layout>
    <x-member-page class="max-w-5xl">
        <x-header :title="$model->name" separator data-testid="page-header">
            <x-slot:actions>
                <x-button label="Zurück" icon="o-arrow-left" link="{{ route('3d-modelle.index') }}"
                    class="btn-ghost" />

                @if ($isUnlocked)
                    <x-button label="Herunterladen" icon="o-arrow-down-tray"
                        link="{{ route('3d-modelle.download', $model) }}"
                        class="btn-primary" data-testid="download-button" />
                @endif

                @can('update', $model)
                    <x-button label="Bearbeiten" icon="o-pencil"
                        link="{{ route('3d-modelle.edit', $model) }}"
                        class="btn-warning btn-sm" />
                @endcan
            </x-slot:actions>
        </x-header>

        {{-- 3D-Viewer (nur wenn freigeschaltet) --}}
        @if ($isUnlocked)
            <div data-three-d-viewer
                data-file-url="{{ route('3d-modelle.preview', $model) }}"
                data-format="{{ $model->file_format }}"
                class="w-full aspect-video rounded-xl overflow-hidden border border-base-300 mb-6"
                data-testid="three-d-viewer">
            </div>
        @else
            <x-card class="mb-6">
                <div class="text-center py-12">
                    <x-icon name="o-lock-closed" class="w-16 h-16 mx-auto text-base-content/30 mb-4" />
                    <p class="text-lg font-semibold">Dieses Modell erfordert {{ $model->required_baxx }} Baxx</p>
                    <p class="text-base-content/60 mt-1">
                        Du hast aktuell {{ $userPoints }} Baxx. Sammle mehr, um die 3D-Vorschau und den Download freizuschalten!
                    </p>
                </div>
            </x-card>
        @endif

        {{-- Metadaten --}}
        <x-card title="Details" data-testid="model-details">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="text-sm text-base-content/60">Format</span>
                    <p class="font-semibold">{{ strtoupper($model->file_format) }}</p>
                </div>
                <div>
                    <span class="text-sm text-base-content/60">Dateigröße</span>
                    <p class="font-semibold">{{ $model->file_size_formatted }}</p>
                </div>
                <div>
                    <span class="text-sm text-base-content/60">Benötigte Baxx</span>
                    <div class="mt-1">
                        <x-badge :value="$model->required_baxx . ' Baxx'"
                            class="{{ $isUnlocked ? 'badge-success' : 'badge-ghost' }}" />
                    </div>
                </div>
                <div>
                    <span class="text-sm text-base-content/60">Hochgeladen von</span>
                    <p class="font-semibold">{{ $model->uploader->name }}</p>
                </div>
            </div>
            <div class="mt-4">
                <span class="text-sm text-base-content/60">Beschreibung</span>
                <p class="mt-1">{{ $model->description }}</p>
            </div>

            @can('delete', $model)
                <div class="mt-6 pt-4 border-t border-base-300">
                    <form method="POST" action="{{ route('3d-modelle.destroy', $model) }}"
                        onsubmit="return confirm('Soll dieses 3D-Modell wirklich gelöscht werden?')">
                        @csrf
                        @method('DELETE')
                        <x-button label="Löschen" icon="o-trash" type="submit"
                            class="btn-error btn-sm" data-testid="delete-button" />
                    </form>
                </div>
            @endcan
        </x-card>
    </x-member-page>

    {{-- Three.js nur laden wenn freigeschaltet --}}
    @if ($isUnlocked)
        @vite('resources/js/three-d-viewer.js')
    @endif
</x-app-layout>
