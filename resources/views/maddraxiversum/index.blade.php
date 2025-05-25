<x-app-layout>
    <div class="py-4 flex flex-col h-[calc(100vh-4rem)]">
        <div class="flex-grow mx-auto w-full px-4 lg:px-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg h-full flex flex-col">
                <div class="p-4 flex-grow flex flex-col">
                    @if ($showMap)

                        <div id="map" class="w-full flex-grow border dark:border-gray-600 rounded"></div>

                        <script>
                            const csrfToken = '{{ csrf_token() }}';
                            const tileUrl = '{{ $tileUrl }}';
                        </script>
                        @vite(['resources/js/maddraxiversum.js'])
                    @else
                        <div class="bg-yellow-100 dark:bg-yellow-800 border-l-4 border-yellow-500 dark:border-yellow-300 text-yellow-700 dark:text-yellow-200 p-4">
                            <p class="font-bold">Zugriff eingeschränkt</p>
                            <p>Du benötigst mindestens {{ $requiredPoints }} Punkte, um die Maddraxiversum-Karte anzuzeigen.</p>
                            <p>Du hast aktuell {{ $userPoints }} Punkte in deinem Team gesammelt.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
<div id="mission-modal" class="fixed inset-0 bg-black bg-opacity-50 z-[2000] flex items-center justify-center hidden">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-start mb-4">
            <h3 id="mission-title" class="text-xl font-bold"></h3>
            <button id="close-mission-modal" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                ✕
            </button>
        </div>
        <div id="mission-duration" class="mb-2 text-gray-600 dark:text-gray-400"></div>
        <div id="mission-description" class="mb-6 text-gray-700 dark:text-gray-300"></div>
        <button id="start-mission" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded w-full">
            Starte Mission
        </button>
    </div>
</div>

