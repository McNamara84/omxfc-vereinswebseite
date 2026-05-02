<x-app-layout title="Serverfehler – Offizieller MADDRAX Fanclub e. V." description="Es ist ein unerwarteter Fehler aufgetreten.">
    <div class="max-w-lg mx-auto px-6 py-12">
        <x-ui.panel class="text-center">
            <x-slot:header>
                <div class="w-full text-center space-y-2">
                    <p class="text-[0.7rem] font-semibold uppercase tracking-[0.28em] text-base-content/45">Fehlerseite</p>
                    <h1 class="font-display text-5xl font-semibold tracking-tight text-base-content">500</h1>
                    <p class="text-sm text-base-content/72">Serverfehler</p>
                </div>
            </x-slot:header>
            <img src="{{ asset('images/errors/500.png') }}" alt="Server zerstört" class="mx-auto w-64 h-auto mb-6">
            <p class="text-base-content/70 mb-6">
                {{ __('Entweder ist ein Komet eingeschlagen oder der Server ist überlastet. Bitte versuche es später aus deinem Bunker erneut.') }}
            </p>
            <x-button label="{{ __('Zur Startseite') }}" link="/" class="btn-primary" icon="o-home" />
        </x-ui.panel>
    </div>
</x-app-layout>