<x-app-layout title="Zugriff verweigert – Offizieller MADDRAX Fanclub e. V." description="Du besitzt keine Berechtigung für diesen Bereich.">
    <div class="max-w-lg mx-auto px-6 py-12">
        <x-ui.panel class="text-center">
            <x-slot:header>
                <div class="w-full text-center space-y-2">
                    <p class="text-[0.7rem] font-semibold uppercase tracking-[0.28em] text-base-content/45">Fehlerseite</p>
                    <h1 class="font-display text-5xl font-semibold tracking-tight text-base-content">403</h1>
                    <p class="text-sm text-base-content/72">Zugriff verweigert</p>
                </div>
            </x-slot:header>
            <img src="{{ asset('images/errors/403.png') }}" alt="Verbotene Zone" class="mx-auto w-64 h-auto mb-6">
            <p class="text-base-content/70 mb-6">
                {{ __('Der Zugriff auf diese Seite ist dir nicht gestattet. Solltest du versuchen, trotzdem an diese Inhalte zu kommen, wird dich ein Rudel Taratze holen und Orguudoo bringen!') }}
            </p>
            <x-button label="{{ __('Zur Startseite') }}" link="/" class="btn-primary" icon="o-home" />
        </x-ui.panel>
    </div>
</x-app-layout>