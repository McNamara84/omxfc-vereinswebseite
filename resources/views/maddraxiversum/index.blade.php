<x-app-layout>
    <div class="py-6 md:py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-4 md:p-6 lg:p-8 bg-white dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent border-b border-gray-200 dark:border-gray-700">
                    @if ($showMap)
                        <p class="mb-4 text-gray-600 dark:text-gray-400 text-sm md:text-base"> {{-- Ggf. Textgröße anpassen --}}
                            Erkunde das Maddraxiversum. Du hast aktuell {{ $userPoints }} Punkte gesammelt.
                        </p>
                        {{-- Leaflet CSS --}}
                        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
                              integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
                              crossorigin=""/>
                        {{-- Leaflet JavaScript --}}
                        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                                integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
                                crossorigin=""></script>
                        <div id="map" class="w-full h-96 md:h-[70vh] mb-4 border dark:border-gray-600 rounded">
                        </div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                const map = L.map('map', {
                                    minZoom: 0,
                                });
                                const tileUrl = '{{ $tileUrl }}';
                                L.tileLayer(tileUrl, {
                                    attribution: '&copy; Maddraxikon | OMXFC e. V.',
                                    minZoom: 0,
                                    maxZoom: 5,
                                    noWrap: false,
                                }).addTo(map);
                                map.setView([60, -25], 3);
                                function updateMapSize() {
                                     map.invalidateSize();
                                }
                                let resizeTimeout;
                                window.addEventListener('resize', () => {
                                     clearTimeout(resizeTimeout);
                                     resizeTimeout = setTimeout(updateMapSize, 150);
                                });
                                setTimeout(updateMapSize, 100);

                            });
                        </script>

                    @else
                        {{-- Nachricht, wenn der Benutzer nicht genug Punkte hat --}}
                        <div class="bg-yellow-100 dark:bg-yellow-800 border-l-4 border-yellow-500 dark:border-yellow-300 text-yellow-700 dark:text-yellow-200 p-4" role="alert">
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