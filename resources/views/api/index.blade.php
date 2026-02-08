<x-app-layout>
    <x-member-page>
        <x-header title="{{ __('API Tokens') }}" separator />

        <div>
            @livewire('api.api-token-manager')
        </div>
    </x-member-page>
</x-app-layout>
