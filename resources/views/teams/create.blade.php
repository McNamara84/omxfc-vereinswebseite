<x-app-layout>
    <x-member-page>
        <x-header title="{{ __('Create Team') }}" separator />

        <div>
            @livewire('teams.create-team-form')
        </div>
    </x-member-page>
</x-app-layout>
