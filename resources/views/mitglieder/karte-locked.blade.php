<x-app-layout>
    <x-member-page>
        <x-header title="Mitgliederkarte" />

        <x-alert title="Karte noch nicht verfügbar" icon="o-lock-closed" class="alert-warning">
            Die Mitgliederkarte wird freigeschaltet, sobald du mindestens eine Challenge erfolgreich abgeschlossen hast.
            <x-slot:actions>
                <x-button label="Zu den verfügbaren Challenges" link="{{ route('todos.index') }}" icon="o-clipboard-document-list" class="btn-primary btn-sm" />
            </x-slot:actions>
        </x-alert>
    </x-member-page>
</x-app-layout>