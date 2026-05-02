<x-app-layout>
    <x-member-page>
        <div class="space-y-6">
            <x-ui.page-header
                eyebrow="Mitgliederbereich"
                title="Profil & Einstellungen"
                description="Verwalte persönliche Daten, Serienpräferenzen, Sicherheitsoptionen und deine aktiven Sitzungen an einem zentralen Ort."
            >
                <x-slot:actions>
                    <x-button
                        label="Öffentliches Profil ansehen"
                        icon="o-eye"
                        link="{{ route('profile.view.self') }}"
                        wire:navigate
                        class="btn-outline"
                    />
                </x-slot:actions>
            </x-ui.page-header>

            @if (Laravel\Fortify\Features::canUpdateProfileInformation())
                <x-ui.panel title="Persönliche Daten" description="Halte Name, Foto, Adresse, Beitrag und Kontaktmöglichkeiten aktuell, damit der Verein dich zuverlässig erreicht.">
                    @livewire('profile.update-profile-information-form')
                </x-ui.panel>
            @endif

            {{-- Serienspezifische Daten ergänzen --}}
            <x-ui.panel title="Serienspezifische Daten" description="Pflege deine Lieblingsdetails zur Serie Maddrax. Diese Angaben können andere Mitglieder in deinem Profil sehen.">
                @livewire('profile.update-seriendaten-form')
            </x-ui.panel>

            @if (Auth::user()->hasRole(\App\Enums\Role::Ehrenmitglied))
                <x-ui.panel title="Benachrichtigungen" description="Steuere, ob du zu neuen Rezensionen deiner Romane per E-Mail informiert werden möchtest.">
                    @livewire('profile.update-review-notification-form')
                </x-ui.panel>
            @endif

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
                <x-ui.panel title="Passwort ändern" description="Nutze ein starkes Passwort und aktualisiere es regelmäßig für einen sicheren Mitgliederzugang.">
                    @livewire('profile.update-password-form')
                </x-ui.panel>
            @endif

            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                <x-ui.panel title="Zwei-Faktor-Authentisierung" description="Erhöhe die Sicherheit deines Kontos mit einem zusätzlichen Anmeldeschritt per Authenticator-App.">
                    @livewire('profile.two-factor-authentication-form')
                </x-ui.panel>
            @endif

            <x-ui.panel title="Browser-Sitzungen" description="Behalte den Überblick über aktive Geräte und beende bei Bedarf andere Sitzungen zentral.">
                @livewire('profile.logout-other-browser-sessions-form')
            </x-ui.panel>

            @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
                <x-ui.panel title="Mitgliedschaft kündigen" description="Beende deine Mitgliedschaft und lösche den internen Account dauerhaft, wenn du den Vereinsbereich nicht mehr nutzen möchtest.">
                    @livewire('profile.delete-user-form')
                </x-ui.panel>
            @endif
        </div>
    </x-member-page>
</x-app-layout>
