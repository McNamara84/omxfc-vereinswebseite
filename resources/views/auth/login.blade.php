<x-app-layout title="Login – Offizieller MADDRAX Fanclub e. V." description="Melde dich mit deinem Konto beim Offiziellen MADDRAX Fanclub an.">
    <div class="max-w-md mx-auto px-6 py-12">
        <x-ui.page-header title="Login" description="Melde dich mit deinem Vereinskonto an, um auf Dashboard, Community-Funktionen und interne Bereiche zuzugreifen." class="mb-6" />

        <x-ui.panel data-testid="login-card">

            @if ($errors->any())
                <x-alert icon="o-exclamation-triangle" class="alert-error mb-4">
                    <div class="font-medium">{{ __('Es gibt ein Problem bei der Anmeldung.') }}</div>
                    <ul class="mt-2 list-disc list-inside text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            @endif

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

                <div class="mt-4">
                    <x-password
                        label="Passwort"
                        id="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        data-testid="login-password"
                    />
                </div>

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
        </x-ui.panel>
    </div>
</x-app-layout>