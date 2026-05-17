<x-app-layout>
    <x-member-page>
        <div class="space-y-6">
            <x-ui.page-header
                eyebrow="Mitgliederbereich"
                title="Profil & Einstellungen"
                description="Verwalte persönliche Daten, Serienpräferenzen, Sicherheitsoptionen und deine aktiven Sitzungen an einem zentralen Ort."
                data-tour-profile-key="profile-header"
            >
                <x-slot:actions>
                    <x-button
                        label="Öffentliches Profil ansehen"
                        icon="o-eye"
                        link="{{ route('profile.view.self') }}"
                        wire:navigate
                        class="btn-outline"
                        data-tour-profile-key="profile-public-view"
                    />
                </x-slot:actions>
            </x-ui.page-header>

            @if (($tourOverview ?? collect())->isNotEmpty())
                <x-ui.panel title="Touren & Hilfestart" description="Starte verfügbare Einführungen erneut, wenn du Menüs oder Vereinsbereiche noch einmal geführt erkunden möchtest." data-tour-profile-key="profile-tour-overview">
                    <div class="grid gap-4 lg:grid-cols-2">
                        @foreach ($tourOverview as $tour)
                            @php
                                $assignment = $tour['assignment'];
                                $statusValue = $tour['status']?->value;
                                $statusLabel = match ($statusValue) {
                                    'completed' => 'Abgeschlossen',
                                    'in_progress' => 'In Bearbeitung',
                                    'pending' => 'Offen',
                                    default => 'Noch nicht gestartet',
                                };
                                $statusClass = match ($statusValue) {
                                    'completed' => 'badge-success',
                                    'in_progress' => 'badge-warning',
                                    'pending' => 'badge-primary',
                                    default => 'badge-outline',
                                };
                            @endphp

                            <article class="rounded-3xl border border-base-300 bg-base-100/70 p-5 shadow-sm">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="space-y-2">
                                        <h3 class="text-lg font-semibold text-base-content">{{ $tour['definition']->title }}</h3>
                                        <p class="text-sm leading-relaxed text-base-content/70">{{ $tour['definition']->description }}</p>
                                    </div>

                                    <x-badge :value="$statusLabel" class="{{ $statusClass }}" />
                                </div>

                                <dl class="mt-4 grid gap-3 text-sm text-base-content/70 sm:grid-cols-2">
                                    <div>
                                        <dt class="font-semibold text-base-content">Schritte</dt>
                                        <dd>{{ count($tour['definition']->steps) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-base-content">Zuletzt zugewiesen</dt>
                                        <dd>{{ $assignment?->assigned_at?->locale('de')->isoFormat('D. MMM YYYY, HH:mm') ?? 'Noch nicht zugewiesen' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-base-content">Fortschritt</dt>
                                        <dd>{{ $assignment?->current_step_key ?: 'Startet am Anfang' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-base-content">Version</dt>
                                        <dd>{{ $tour['definition']->version }}</dd>
                                    </div>
                                </dl>

                                <div class="mt-5 flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('touren.restart', $tour['definition']->key) }}">
                                        @csrf
                                        <x-button
                                            :label="$tour['is_open'] ? 'Tour von vorn starten' : 'Tour erneut starten'"
                                            icon="o-play"
                                            class="btn-primary btn-sm"
                                        />
                                    </form>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </x-ui.panel>
            @endif

            @if (Laravel\Fortify\Features::canUpdateProfileInformation())
                <x-ui.panel title="Persönliche Daten" description="Halte Name, Foto, Adresse, Beitrag und Kontaktmöglichkeiten aktuell, damit der Verein dich zuverlässig erreicht." data-tour-profile-key="profile-personal-data">
                    @livewire('profile.update-profile-information-form')
                </x-ui.panel>
            @endif

            {{-- Serienspezifische Daten ergänzen --}}
            <x-ui.panel title="Serienspezifische Daten" description="Pflege deine Lieblingsdetails zur Serie Maddrax. Diese Angaben können andere Mitglieder in deinem Profil sehen." data-tour-profile-key="profile-series-data">
                @livewire('profile.update-seriendaten-form')
            </x-ui.panel>

            @if (Auth::user()->hasRole(\App\Enums\Role::Ehrenmitglied))
                <x-ui.panel title="Benachrichtigungen" description="Steuere, ob du zu neuen Rezensionen deiner Romane per E-Mail informiert werden möchtest.">
                    @livewire('profile.update-review-notification-form')
                </x-ui.panel>
            @endif

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
                <x-ui.panel title="Passwort ändern" description="Nutze ein starkes Passwort und aktualisiere es regelmäßig für einen sicheren Mitgliederzugang." data-tour-profile-key="profile-password">
                    @livewire('profile.update-password-form')
                </x-ui.panel>
            @endif

            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                <x-ui.panel title="Zwei-Faktor-Authentisierung" description="Erhöhe die Sicherheit deines Kontos mit einem zusätzlichen Anmeldeschritt per Authenticator-App." data-tour-profile-key="profile-two-factor">
                    @livewire('profile.two-factor-authentication-form')
                </x-ui.panel>
            @endif

            <x-ui.panel title="Browser-Sitzungen" description="Behalte den Überblick über aktive Geräte und beende bei Bedarf andere Sitzungen zentral." data-tour-profile-key="profile-browser-sessions">
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
