<x-card>
    <x-header title="Details der Arbeitsgruppe" subtitle="Erstelle eine neue Arbeitsgruppe, um mit anderen an Projekten zusammenzuarbeiten." size="text-lg" class="!mb-4" />

    <div class="flex items-center mb-6">
        <x-avatar :image="$this->user->profile_photo_url" class="!w-12 !h-12" />
        <div class="ms-4 leading-tight">
            <div class="text-base-content font-medium">{{ $this->user->name }}</div>
            <div class="text-base-content/70 text-sm">{{ $this->user->email }}</div>
        </div>
    </div>

    <form wire:submit="createTeam">
        <x-input label="Name der Arbeitsgruppe" wire:model="state.name" autofocus />

        <div class="mt-6 flex justify-end">
            <x-button type="submit" label="Erstellen" class="btn-primary" />
        </div>
    </form>
</x-card>
