<x-app-layout title="Serverfehler – Offizieller MADDRAX Fanclub e. V." description="Es ist ein unerwarteter Fehler aufgetreten.">
    <div class="max-w-lg mx-auto px-6 py-12">
        <x-card shadow class="text-center">
            <x-header title="500" subtitle="Serverfehler" class="mb-4" />
            <img src="{{ asset('images/errors/500.png') }}" alt="Server zerstört" class="mx-auto w-64 h-auto mb-6">
            <p class="text-base-content/70 mb-6">
                {{ __('Entweder ist ein Komet eingeschlagen oder der Server ist überlastet. Bitte versuche es später aus deinem Bunker erneut.') }}
            </p>
            <x-button label="{{ __('Zur Startseite') }}" link="/" class="btn-primary" icon="o-home" />
        </x-card>
    </div>
</x-app-layout>