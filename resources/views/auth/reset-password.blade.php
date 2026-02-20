<x-app-layout title="Passwort zurücksetzen – Offizieller MADDRAX Fanclub e. V." description="Setze dein Passwort zurück.">
    <div class="max-w-md mx-auto px-6 py-12">
        <x-card shadow data-testid="reset-password-card">
            <x-header title="Passwort zurücksetzen" class="mb-4" useH1 />

            <x-validation-errors class="mb-4" />

            <form method="POST" action="{{ route('password.update') }}">
                @csrf

                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <x-input
                    label="E-Mail"
                    id="email"
                    name="email"
                    type="email"
                    :value="old('email', $request->email)"
                    required
                    autofocus
                    autocomplete="username"
                    data-testid="reset-password-email"
                />

                <x-password
                    label="Passwort"
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    class="mt-4"
                    data-testid="reset-password-password"
                />

                <x-password
                    label="Passwort bestätigen"
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    class="mt-4"
                    data-testid="reset-password-confirm"
                />

                <div class="flex items-center justify-end mt-6">
                    <x-button label="Passwort zurücksetzen" type="submit" class="btn-primary" data-testid="reset-password-submit" />
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
