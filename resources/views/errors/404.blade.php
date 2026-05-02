<x-app-layout title="Seite nicht gefunden – Offizieller MADDRAX Fanclub e. V." description="Die angeforderte Seite existiert nicht.">
    <div class="max-w-lg mx-auto px-6 py-12">
        <x-ui.panel class="text-center">
            <x-slot:header>
                <div class="w-full text-center space-y-2">
                    <p class="text-[0.7rem] font-semibold uppercase tracking-[0.28em] text-base-content/45">Fehlerseite</p>
                    <h1 class="font-display text-5xl font-semibold tracking-tight text-base-content">404</h1>
                    <p class="text-sm text-base-content/72">Seite nicht gefunden</p>
                </div>
            </x-slot:header>
            <img src="{{ asset('images/errors/404.png') }}" alt="Seite verschollen" class="mx-auto w-64 h-auto mb-6">
            <p class="text-base-content/70 mb-6">
                {{ __('Wir haben Archivar Zuul losgeschickt um diese Seite zu finden, aber er wurde nicht mal in seiner BagBox fündig. Bitte prüfe die eingegebene URL auf Fehlertaratzen!') }}
            </p>
            <x-button label="{{ __('Zur Startseite') }}" link="/" class="btn-primary" icon="o-home" />
        </x-ui.panel>
    </div>
</x-app-layout>