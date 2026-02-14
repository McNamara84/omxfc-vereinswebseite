<x-app-layout title="E-Mail bestätigen – Offizieller MADDRAX Fanclub e. V." description="Bestätige deine E-Mail-Adresse, um fortzufahren.">
    <div class="max-w-md mx-auto px-6 py-12">
        <x-card shadow data-testid="verify-email-card">
            <x-header title="E-Mail bestätigen" class="mb-4" />

            <p class="mb-4 text-sm text-base-content/70">
                Bevor du fortfährst, bestätige bitte deine E-Mail-Adresse, indem du auf den Link klickst, den wir dir gerade per E-Mail geschickt haben. Falls du die E-Mail nicht erhalten hast, senden wir dir gerne eine neue.
            </p>

            @if (session('status') == 'verification-link-sent')
                <x-alert icon="o-check-circle" class="alert-success mb-4">
                    Ein neuer Bestätigungslink wurde an die E-Mail-Adresse gesendet, die du in deinen Profileinstellungen angegeben hast.
                </x-alert>
            @endif

            <div class="mt-4 flex items-center justify-between">
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <x-button label="Bestätigungs-E-Mail erneut senden" type="submit" class="btn-primary" data-testid="verify-email-resend" />
                </form>

                <div class="flex items-center gap-2">
                    <x-button label="Profil bearbeiten" :link="route('profile.show')" class="btn-ghost btn-sm" data-testid="verify-email-profile" />

                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <x-button label="Abmelden" type="submit" class="btn-ghost btn-sm" data-testid="verify-email-logout" />
                    </form>
                </div>
            </div>
        </x-card>
    </div>
</x-app-layout>
