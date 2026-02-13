<x-card>
    <x-header title="Arbeitsgruppe löschen" subtitle="Die Arbeitsgruppe wird dauerhaft gelöscht." size="text-lg" class="!mb-4" />

    <div class="max-w-xl text-sm text-base-content">
        Wenn eine Arbeitsgruppe gelöscht wird, werden alle zugehörigen Daten dauerhaft entfernt. Bitte sichere vorher alle Informationen, die du behalten möchtest.
    </div>

    <div class="mt-5">
        <x-button label="Arbeitsgruppe löschen" class="btn-error" wire:click="$toggle('confirmingTeamDeletion')" wire:loading.attr="disabled" icon="o-trash" />
    </div>

    {{-- Lösch-Bestätigungsdialog --}}
    <x-mary-modal wire:model="confirmingTeamDeletion" title="Arbeitsgruppe löschen" separator>
        <p class="text-base-content">
            Bist du sicher, dass du diese Arbeitsgruppe löschen möchtest? Alle zugehörigen Daten werden dauerhaft entfernt und können nicht wiederhergestellt werden.
        </p>

        <x-slot:actions>
            <x-button label="Abbrechen" wire:click="$toggle('confirmingTeamDeletion')" wire:loading.attr="disabled" />
            <x-button label="Arbeitsgruppe löschen" class="btn-error" wire:click="deleteTeam" wire:loading.attr="disabled" icon="o-trash" />
        </x-slot:actions>
    </x-mary-modal>
</x-card>
