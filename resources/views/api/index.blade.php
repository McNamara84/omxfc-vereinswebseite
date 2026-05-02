<x-app-layout>
    <x-member-page>
        <x-ui.page-header
            title="{{ __('API Tokens') }}"
            description="{{ __('Create and manage personal access tokens for third-party integrations tied to your account.') }}"
        />

        <div>
            @livewire('api.api-token-manager')
        </div>
    </x-member-page>
</x-app-layout>
