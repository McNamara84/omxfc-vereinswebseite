<x-app-layout>
    <x-member-page>
        <div class="space-y-6">
            <x-ui.page-header
                eyebrow="Mitgliederbereich"
                title="Arbeitsgruppe erstellen"
                description="Lege eine neue Arbeitsgruppe an und ordne sie direkt einer verantwortlichen Person zu."
            />

            <x-ui.panel title="Details der Arbeitsgruppe" description="Name und Startkontext der neuen Arbeitsgruppe.">
                @livewire('teams.create-team-form')
            </x-ui.panel>
        </div>
    </x-member-page>
</x-app-layout>
