<x-app-layout title="Registrieren – Offizieller MADDRAX Fanclub e. V." description="Erstelle ein Konto beim Offiziellen MADDRAX Fanclub.">
    <div class="max-w-md mx-auto px-6 py-12">
        <x-card shadow data-testid="register-card">
            <x-header title="Registrieren" class="mb-4" useH1 />

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

                <div class="mt-4">
                    <x-input
                        label="E-Mail"
                        id="email"
                        name="email"
                        type="email"
                        :value="old('email')"
                        required
                        autocomplete="username"
                        data-testid="register-email"
                    />
                </div>

                <div class="mt-4">
                    <x-password
                        label="Passwort"
                        id="password"
                        name="password"
                        required
                        autocomplete="new-password"
                        data-testid="register-password"
                    />
                </div>

                <div class="mt-4">
                    <x-password
                        label="Passwort bestätigen"
                        id="password_confirmation"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        data-testid="register-password-confirm"
                    />
                </div>

                @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                    <div class="mt-4">
                        <x-checkbox name="terms" id="terms" required data-testid="register-terms">
                            <x-slot:label>
                                {!! __('Ich stimme den :terms_of_service und der :privacy_policy zu.', [
                                        'terms_of_service' => '<a target="_blank" rel="noopener noreferrer" href="'.route('terms.show').'" class="underline text-base-content/70 hover:text-base-content" onclick="event.stopPropagation()">'.__('Nutzungsbedingungen').'</a>',
                                        'privacy_policy' => '<a target="_blank" rel="noopener noreferrer" href="'.route('policy.show').'" class="underline text-base-content/70 hover:text-base-content" onclick="event.stopPropagation()">'.__('Datenschutzerklärung').'</a>',
                                ]) !!}
                            </x-slot:label>
                        </x-checkbox>
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
