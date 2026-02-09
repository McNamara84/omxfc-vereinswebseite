<div class="max-w-4xl mx-auto">
    {{-- Header --}}
    <x-header title="VIP-Autoren verwalten" subtitle="Verwalte die Autoren, die als VIP-Gäste beim Fantreffen 2026 angekündigt werden." separator>
        <x-slot:actions>
            <x-button label="Zurück zu Anmeldungen" link="{{ route('admin.fantreffen.2026') }}" icon="o-arrow-left" class="btn-ghost" />
        </x-slot:actions>
    </x-header>

    {{-- Success/Error Messages --}}
    @if (session()->has('success'))
        <x-alert icon="o-check-circle" class="alert-success mb-6" dismissible>
            {{ session('success') }}
        </x-alert>
    @endif

    @if (session()->has('error'))
        <x-alert icon="o-exclamation-triangle" class="alert-error mb-6" dismissible>
            {{ session('error') }}
        </x-alert>
    @endif

    {{-- Add Author Button --}}
    @if (!$showForm)
        <div class="mb-6">
            <x-button label="Neuen Autor hinzufügen" wire:click="openForm" icon="o-plus" class="btn-primary" data-testid="open-form-button" />
        </div>
    @endif

    {{-- Add/Edit Form --}}
    @if ($showForm)
        <x-card title="{{ $editingId ? 'Autor bearbeiten' : 'Neuen Autor hinzufügen' }}" class="mb-6" shadow>
            <form wire:submit="save" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input 
                        label="Name *" 
                        wire:model="name" 
                        placeholder="z.B. Oliver Fröhlich"
                        data-testid="vip-author-name"
                    />
                    <x-input 
                        label="Pseudonym (optional)" 
                        wire:model="pseudonym" 
                        placeholder="z.B. Ian Rolf Hill"
                        data-testid="vip-author-pseudonym"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input 
                        label="Sortierung" 
                        type="number" 
                        wire:model="sort_order" 
                        min="0"
                    />
                    <div class="pt-6 space-y-3">
                        <x-checkbox 
                            label="Auf der Anmeldeseite anzeigen" 
                            wire:model="is_active" 
                        />
                        <x-checkbox 
                            label="Zusage unter Vorbehalt" 
                            wire:model="is_tentative" 
                        />
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <x-button label="Abbrechen" wire:click="closeForm" class="btn-ghost" data-testid="cancel-form-button" />
                    <x-button 
                        label="{{ $editingId ? 'Aktualisieren' : 'Hinzufügen' }}" 
                        type="submit" 
                        icon="o-check" 
                        class="btn-primary" 
                        spinner="save"
                        data-testid="submit-form-button"
                    />
                </div>
            </form>
        </x-card>
    @endif

    {{-- Authors List --}}
    <x-card title="VIP-Autoren ({{ $authors->count() }})" shadow>
        @if ($authors->isEmpty())
            <div class="text-center py-12">
                <x-icon name="o-users" class="w-12 h-12 mx-auto mb-4 opacity-30" />
                <p class="text-lg font-medium">Noch keine VIP-Autoren</p>
                <p class="mt-1 opacity-60">Füge den ersten Autor hinzu, um ihn auf der Anmeldeseite anzukündigen.</p>
            </div>
        @else
            <div class="divide-y divide-base-200">
                @foreach ($authors as $author)
                    <div class="flex items-center justify-between gap-4 py-4 hover:bg-base-200/50 -mx-4 px-4 transition-colors">
                        {{-- Author Info --}}
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <span class="text-sm opacity-60 font-mono w-8">#{{ $author->sort_order }}</span>
                            <div>
                                <p class="font-medium truncate">{{ $author->name }}</p>
                                @if ($author->is_tentative)
                                    <x-badge value="Unter Vorbehalt" class="badge-warning badge-sm" />
                                @endif
                                @if ($author->pseudonym)
                                    <p class="text-sm opacity-60">Pseudonym: {{ $author->pseudonym }}</p>
                                @endif
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="flex items-center gap-1">
                            {{-- Status Toggle --}}
                            <x-button 
                                wire:click="toggleActive({{ $author->id }})" 
                                :label="$author->is_active ? 'Aktiv' : 'Inaktiv'"
                                class="{{ $author->is_active ? 'btn-success' : 'btn-ghost' }} btn-xs"
                                tooltip="{{ $author->is_active ? 'Klicken zum Deaktivieren' : 'Klicken zum Aktivieren' }}"
                            />

                            {{-- Move Up --}}
                            <x-button 
                                wire:click="moveUp({{ $author->id }})" 
                                icon="o-chevron-up" 
                                class="btn-ghost btn-xs" 
                                :disabled="$author->sort_order <= 0"
                                tooltip="Nach oben"
                            />

                            {{-- Move Down --}}
                            <x-button 
                                wire:click="moveDown({{ $author->id }})" 
                                icon="o-chevron-down" 
                                class="btn-ghost btn-xs" 
                                :disabled="$loop->last"
                                tooltip="Nach unten"
                            />

                            {{-- Edit --}}
                            <x-button 
                                wire:click="edit({{ $author->id }})" 
                                icon="o-pencil" 
                                class="btn-ghost btn-xs text-info"
                                tooltip="Bearbeiten"
                            />

                            {{-- Delete --}}
                            <x-button 
                                wire:click="delete({{ $author->id }})" 
                                wire:confirm="Möchtest du den Autor &quot;{{ e($author->name) }}&quot; wirklich löschen?"
                                icon="o-trash" 
                                class="btn-ghost btn-xs text-error"
                                tooltip="Löschen"
                            />
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>

    {{-- Preview Info --}}
    @if ($activeAuthors->isNotEmpty())
        <x-alert icon="o-information-circle" class="alert-info mt-6">
            <x-slot:title>Vorschau auf der Anmeldeseite</x-slot:title>
            <p class="mb-2">Diese Autoren werden prominent auf der Anmeldeseite angezeigt:</p>
            <ul class="list-disc list-inside">
                @foreach ($activeAuthors as $author)
                    <li>{{ $author->display_name }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif
</div>
