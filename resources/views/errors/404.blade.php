<x-app-layout>
    <div class="container mx-auto py-12 text-center">
        <h1 class="text-5xl font-bold text-apocalypse-accent mb-6">404</h1>
        <img src="{{ asset('images/errors/404.png') }}" alt="Seite verschollen" class="mx-auto w-64 h-auto mb-6">
        <p class="text-xl text-gray-700 dark:text-gray-300 mb-8">
            {{ __('Wir haben Archivar Zuul losgeschickt um diese Seite zu finden, aber er wurde nicht mal in seiner BagBox fündig. Bitte prüfe die eingegebene URL auf Fehlertaratzen!') }}
        </p>
        <a href="{{ url('/') }}" class="text-apocalypse-accent underline">
            {{ __('Zur Startseite') }}
        </a>
    </div>
</x-app-layout>