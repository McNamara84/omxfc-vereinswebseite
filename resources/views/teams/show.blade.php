<x-app-layout>
    <x-member-page>
        <div class="space-y-6">
            <x-ui.page-header
                eyebrow="Mitgliederbereich"
                title="Arbeitsgruppe verwalten"
                description="Verwalte Name, Mitglieder, Rollen und Einladungen deiner Arbeitsgruppe in einer konsistenten Verwaltungsansicht."
            />

            <x-ui.panel title="Stammdaten" description="Name und verantwortliche Leitung der Arbeitsgruppe.">
                @livewire('teams.update-team-name-form', ['team' => $team])
            </x-ui.panel>

            @livewire('teams.team-member-manager', ['team' => $team])

            @if (Gate::check('delete', $team) && ! $team->personal_team)
                <x-ui.panel title="Arbeitsgruppe löschen" description="Entferne die Arbeitsgruppe dauerhaft, wenn sie nicht mehr benötigt wird.">
                    @livewire('teams.delete-team-form', ['team' => $team])
                </x-ui.panel>
            @endif
        </div>
    </x-member-page>
</x-app-layout>
