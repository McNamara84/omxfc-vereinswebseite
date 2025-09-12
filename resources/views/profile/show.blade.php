<x-app-layout>
    <x-member-page>
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
            <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-red-400 mb-6">{{ __('Profil') }}</h1>

            @if (Laravel\Fortify\Features::canUpdateProfileInformation())
                @livewire('profile.update-profile-information-form')
                <x-section-border />
            @endif

            {{-- Serienspezifische Daten ergänzen --}}
            @livewire('profile.update-seriendaten-form')
            <x-section-border />

            @if (Auth::user()->hasRole(\App\Enums\Role::Ehrenmitglied))
                @livewire('profile.update-review-notification-form')
                <x-section-border />
            @endif

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
                <div class="mt-10 sm:mt-0">
                    @livewire('profile.update-password-form')
                </div>
                <x-section-border />
            @endif

            {{-- @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
            <div class="mt-10 sm:mt-0">
                @livewire('profile.two-factor-authentication-form')
            </div>
            <x-section-border />
            @endif --}}

            <div class="mt-10 sm:mt-0">
                @livewire('profile.logout-other-browser-sessions-form')
            </div>

            @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
                <x-section-border />
                <div class="mt-10 sm:mt-0">
                    @livewire('profile.delete-user-form')
                </div>
            @endif
        </div>
    </x-member-page>
</x-app-layout>
