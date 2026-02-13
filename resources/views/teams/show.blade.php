<x-app-layout>
    <x-member-page>
        <x-header title="Arbeitsgruppe verwalten" separator />
            @livewire('teams.update-team-name-form', ['team' => $team])

            @livewire('teams.team-member-manager', ['team' => $team])

            @if (Gate::check('delete', $team) && ! $team->personal_team)
                <x-hr class="my-8" />

                @livewire('teams.delete-team-form', ['team' => $team])
            @endif
    </x-member-page>
</x-app-layout>
