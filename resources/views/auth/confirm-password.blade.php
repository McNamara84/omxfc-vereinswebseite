<x-app-layout title="Passwort bestätigen – Offizieller MADDRAX Fanclub e. V." description="Bestätige dein Passwort, um fortzufahren.">
    <div class="max-w-md mx-auto px-6 py-12">
        <x-ui.page-header title="Passwort bestätigen" description="Bestätige dein aktuelles Passwort, bevor du einen geschützten Bereich der Anwendung öffnest." class="mb-6" />

        <x-ui.panel data-testid="confirm-password-card">

            <x-alert icon="o-information-circle" class="alert-info mb-4">
                Dies ist ein geschützter Bereich der Anwendung. Bitte bestätige dein Passwort, bevor du fortfährst.
            </x-alert>

            @if ($errors->any())
                <x-alert icon="o-exclamation-triangle" class="alert-error mb-4">
                    <div class="font-medium">{{ __('Es gibt ein Problem.') }}</div>
                    <ul class="mt-2 list-disc list-inside text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            @endif

            <form method="POST" action="{{ route('password.confirm') }}">
                @csrf

                <x-input
                    label="Passwort"
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    autofocus
                    data-testid="confirm-password-input"
                />

                <div class="flex justify-end mt-6">
                    <x-button label="Bestätigen" type="submit" class="btn-primary" data-testid="confirm-password-submit" />
                </div>
            </form>
        </x-ui.panel>
    </div>
</x-app-layout>
