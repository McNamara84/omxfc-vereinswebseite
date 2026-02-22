<x-app-layout title="Zugriff verweigert – Offizieller MADDRAX Fanclub e. V." description="Du besitzt keine Berechtigung für diesen Bereich.">
    <div class="max-w-lg mx-auto px-6 py-12">
        <x-card shadow class="text-center">
            <x-header title="403" subtitle="Zugriff verweigert" class="mb-4" />
            <img src="{{ asset('images/errors/403.png') }}" alt="Verbotene Zone" class="mx-auto w-64 h-auto mb-6">
            <p class="text-base-content/70 mb-6">
                {{ __('Der Zugriff auf diese Seite ist dir nicht gestattet. Solltest du versuchen, trotzdem an diese Inhalte zu kommen, wird dich ein Rudel Taratze holen und Orguudoo bringen!') }}
            </p>
            <x-button label="{{ __('Zur Startseite') }}" link="/" class="btn-primary" icon="o-home" />
        </x-card>
    </div>
</x-app-layout>