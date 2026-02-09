<x-app-layout title="Termine – Offizieller MADDRAX Fanclub e. V." description="Aktuelle Vereinsveranstaltungen und Treffen im praktischen Kalender.">
    <x-public-page>
        <h1 class="text-2xl sm:text-3xl font-bold text-primary mb-4 sm:mb-8">Termine</h1>
        <!-- Desktop: Monatsansicht -->
        <div
            class="hidden md:block aspect-video rounded-lg overflow-hidden shadow-md border border-base-content/20">
            <iframe src="{{ $calendarUrl }}" class="w-full h-full border-0" frameborder="0" scrolling="no">
            </iframe>
        </div>
        <!-- Mobil: Terminliste (Agenda) -->
        <div
            class="md:hidden h-[600px] rounded-lg overflow-hidden shadow-md border border-base-content/20">
            <iframe src="{{ $calendarUrlAgenda }}" class="w-full h-full border-0" frameborder="0" scrolling="no">
            </iframe>
        </div>
        <p class="mt-4 text-sm sm:text-base text-base-content">
            Hier findest du alle aktuellen Termine des Vereins. Den Kalender kannst du auch direkt bei
            <a href="{{ $calendarLink }}" target="_blank"
                class="link link-primary">
                Google Kalender
            </a>
            öffnen.
        </p>
    </x-public-page>
</x-app-layout>
