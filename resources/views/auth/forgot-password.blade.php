<x-app-layout title="Passwort vergessen – Offizieller MADDRAX Fanclub e. V." description="Fordere einen Link zum Zurücksetzen deines Passworts an.">
    <div class="max-w-md mx-auto px-6 py-12">
        <x-card shadow data-testid="forgot-password-card">
            <x-header title="Passwort vergessen" class="mb-4" />

            <p class="mb-4 text-sm text-base-content/70">
                Du hast dein Passwort vergessen? Das ist kein Problem. Teile uns einfach deine E-Mail-Adresse mit und wir senden dir einen Link zum Zurücksetzen des Passworts zu, mit dem du ein neues wählen kannst.
            </p>

            @session('status')
                <x-alert icon="o-check-circle" class="alert-success mb-4">
                    {{ $value }}
                </x-alert>
            @endsession

            <x-validation-errors class="mb-4" />

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <x-input
                    label="E-Mail"
                    id="email"
                    name="email"
                    type="email"
                    :value="old('email')"
                    required
                    autofocus
                    autocomplete="username"
                    data-testid="forgot-password-email"
                />

                <div class="flex items-center justify-end mt-6">
                    <x-button label="Link anfordern" type="submit" class="btn-primary" data-testid="forgot-password-submit" />
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>