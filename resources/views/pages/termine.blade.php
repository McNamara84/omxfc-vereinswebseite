<x-app-layout>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 bg-gray-100 dark:bg-gray-800 rounded-lg shadow-sm">
        <h1 class="text-2xl sm:text-3xl font-bold text-[#8B0116] dark:text-[#ff4b63] mb-4 sm:mb-8">Termine</h1>

        <div class="aspect-video rounded-lg overflow-hidden shadow-md border border-gray-200 dark:border-gray-600">
            <iframe src="{{ $calendarUrl }}"
                    style="border-width:0"
                    width="100%"
                    height="600"
                    frameborder="0"
                    scrolling="no">
            </iframe>
        </div>

        <p class="mt-4 text-sm sm:text-base text-gray-700 dark:text-gray-300">
            Hier findest du alle aktuellen Termine des Vereins. Den Kalender kannst du auch direkt bei
            <a href="{{ $calendarLink }}" target="_blank" class="text-[#8B0116] dark:text-[#ff4b63] underline hover:text-[#6a0110] dark:hover:text-[#d63c4e]">
                Google Kalender
            </a>
            öffnen.
        </p>
    </div>
</x-app-layout>
