<x-app-layout title="Login – Offizieller MADDRAX Fanclub e. V." description="Melde dich mit deinem Konto beim Offiziellen MADDRAX Fanclub an.">
    <div class="max-w-md mx-auto px-6 py-12">
        <x-card shadow data-testid="login-card">
            <x-header title="Login" class="mb-4" useH1 />

            <x-validation-errors class="mb-4" />

            @session('status')
                <x-alert icon="o-check-circle" class="alert-success mb-4">
                    {{ $value }}
                </x-alert>
            @endsession

            <form method="POST" action="{{ route('login') }}">
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
                    data-testid="login-email"
                />

                <x-input
                    label="Passwort"
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="mt-4"
                    data-testid="login-password"
                />

                <div class="mt-4">
                    <x-checkbox label="Merken" id="remember_me" name="remember" data-testid="login-remember" />
                </div>

                <div class="flex items-center justify-between mt-6">
                    @if (Route::has('password.request'))
                        <x-button label="Passwort vergessen?" :link="route('password.request')" class="btn-ghost btn-sm" data-testid="login-forgot-password" />
                    @endif
                    <x-button label="Login" type="submit" class="btn-primary" data-testid="login-submit" />
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>