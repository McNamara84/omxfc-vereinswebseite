<x-app-layout>
    <x-member-page>
        <x-card class="shadow-xl" title="{{ __('Profil') }}">
            @if (Laravel\Fortify\Features::canUpdateProfileInformation())
                @livewire('profile.update-profile-information-form')
                <x-hr class="my-8" />
            @endif

            {{-- Serienspezifische Daten ergänzen --}}
            @livewire('profile.update-seriendaten-form')
            <x-hr class="my-8" />

            @if (Auth::user()->hasRole(\App\Enums\Role::Ehrenmitglied))
                @livewire('profile.update-review-notification-form')
                <x-hr class="my-8" />
            @endif

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
                <div class="mt-10 sm:mt-0">
                    @livewire('profile.update-password-form')
                </div>
                <x-hr class="my-8" />
            @endif

            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
            <div class="mt-10 sm:mt-0">
                @livewire('profile.two-factor-authentication-form')
            </div>
            <x-hr class="my-8" />
            @endif

            <div class="mt-10 sm:mt-0">
                @livewire('profile.logout-other-browser-sessions-form')
            </div>

            @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
                <x-hr class="my-8" />
                <div class="mt-10 sm:mt-0">
                    @livewire('profile.delete-user-form')
                </div>
            @endif
        </x-card>
    </x-member-page>
</x-app-layout>
