<x-app-layout>
    <x-member-page>
        <x-ui.page-header
            eyebrow="Mitgliederbereich"
            title="Mitgliederkarte"
            description="Die Kartenansicht wird freigeschaltet, sobald du erste Baxx gesammelt und mindestens eine Challenge abgeschlossen hast."
            data-testid="page-title"
        />

        <x-ui.panel>
            <x-alert title="Karte noch nicht verfügbar" icon="o-lock-closed" class="alert-warning">
                Die Mitgliederkarte wird freigeschaltet, sobald du mindestens eine Challenge erfolgreich abgeschlossen hast.
                <x-slot:actions>
                    <x-button label="Zu den verfügbaren Challenges" link="{{ route('todos.index') }}" wire:navigate icon="o-clipboard-document-list" class="btn-primary btn-sm" />
                </x-slot:actions>
            </x-alert>
        </x-ui.panel>
    </x-member-page>
</x-app-layout>