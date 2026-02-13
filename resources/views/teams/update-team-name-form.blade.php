<x-card>
    <x-header title="Name der Arbeitsgruppe" subtitle="Name und Ersteller der Arbeitsgruppe." size="text-lg" class="!mb-4" />

    <div class="flex items-center mb-6">
        <x-avatar :image="$team->owner->profile_photo_url" class="!w-12 !h-12" />
        <div class="ms-4 leading-tight">
            <div class="text-base-content font-medium">{{ $team->owner->name }}</div>
            <div class="text-base-content/70 text-sm">{{ $team->owner->email }}</div>
        </div>
    </div>

    <form wire:submit="updateTeamName">
        <x-input
            label="Name der Arbeitsgruppe"
            wire:model="state.name"
            errorField="name"
            :disabled="! Gate::check('update', $team)"
        />

        @if (Gate::check('update', $team))
            <div class="mt-6 flex items-center justify-end gap-3">
                <x-icon name="o-check-circle" class="w-5 h-5 text-success" x-cloak x-show="$wire.recentlySuccessful" aria-hidden="true" />
                <span class="text-sm text-success" x-cloak x-show="$wire.recentlySuccessful">Gespeichert.</span>

                <x-button type="submit" label="Speichern" class="btn-primary" />
            </div>
        @endif
    </form>
</x-card>
