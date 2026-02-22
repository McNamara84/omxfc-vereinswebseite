<x-app-layout title="Seite nicht gefunden – Offizieller MADDRAX Fanclub e. V." description="Die angeforderte Seite existiert nicht.">
    <div class="max-w-lg mx-auto px-6 py-12">
        <x-card shadow class="text-center">
            <x-header title="404" subtitle="Seite nicht gefunden" class="mb-4" />
            <img src="{{ asset('images/errors/404.png') }}" alt="Seite verschollen" class="mx-auto w-64 h-auto mb-6">
            <p class="text-base-content/70 mb-6">
                {{ __('Wir haben Archivar Zuul losgeschickt um diese Seite zu finden, aber er wurde nicht mal in seiner BagBox fündig. Bitte prüfe die eingegebene URL auf Fehlertaratzen!') }}
            </p>
            <x-button label="{{ __('Zur Startseite') }}" link="/" class="btn-primary" icon="o-home" />
        </x-card>
    </div>
</x-app-layout>