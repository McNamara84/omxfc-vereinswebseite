<x-app-layout title="Registrieren – Offizieller MADDRAX Fanclub e. V." description="Erstelle ein Konto beim Offiziellen MADDRAX Fanclub.">
    <div class="max-w-md mx-auto px-6 py-12">
        <x-card shadow data-testid="register-card">
            <x-header title="Registrieren" class="mb-4" />

            <x-validation-errors class="mb-4" />

            <form method="POST" action="{{ route('register') }}">
                @csrf

                <x-input
                    label="Name"
                    id="name"
                    name="name"
                    type="text"
                    :value="old('name')"
                    required
                    autofocus
                    autocomplete="name"
                    data-testid="register-name"
                />

                <x-input
                    label="E-Mail"
                    id="email"
                    name="email"
                    type="email"
                    :value="old('email')"
                    required
                    autocomplete="username"
                    class="mt-4"
                    data-testid="register-email"
                />

                <x-input
                    label="Passwort"
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="mt-4"
                    data-testid="register-password"
                />

                <x-input
                    label="Passwort bestätigen"
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="mt-4"
                    data-testid="register-password-confirm"
                />

                @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                    <div class="mt-4">
                        <div class="flex items-center">
                            <x-checkbox name="terms" id="terms" required data-testid="register-terms" />
                            <div class="ms-2 text-sm">
                                {!! __('Ich stimme den :terms_of_service und der :privacy_policy zu.', [
                                        'terms_of_service' => '<a target="_blank" href="'.route('terms.show').'" class="underline text-sm text-base-content/70 hover:text-base-content">'.__('Nutzungsbedingungen').'</a>',
                                        'privacy_policy' => '<a target="_blank" href="'.route('policy.show').'" class="underline text-sm text-base-content/70 hover:text-base-content">'.__('Datenschutzerklärung').'</a>',
                                ]) !!}
                            </div>
                        </div>
                    </div>
                @endif

                <div class="flex items-center justify-between mt-6">
                    <x-button label="Bereits registriert?" :link="route('login')" class="btn-ghost btn-sm" data-testid="register-login-link" />
                    <x-button label="Registrieren" type="submit" class="btn-primary" data-testid="register-submit" />
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
