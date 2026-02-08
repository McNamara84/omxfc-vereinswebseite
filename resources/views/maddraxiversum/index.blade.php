<x-app-layout>
    <div class="py-4 flex flex-col h-[calc(100vh-4rem)]">
        <div class="flex-grow mx-auto w-full px-4 lg:px-6">
            <div class="bg-base-100 overflow-hidden shadow-xl rounded-lg h-full flex flex-col">
                <div class="p-4 flex-grow flex flex-col">
                    @if ($showMap)

                        <div id="map" class="w-full flex-grow border border-base-content/10 rounded"></div>

                        <script>
                            const csrfToken = '{{ csrf_token() }}';
                            const tileUrl = '{{ $tileUrl }}';
                        </script>
                        @vite(['resources/js/maddraxiversum.js'])
                    @else
                        <div class="bg-warning/10 border-l-4 border-warning text-warning-content p-4">
                            <p class="font-bold">Zugriff eingeschränkt</p>
                            <p>Du benötigst mindestens {{ $requiredPoints }} Baxx, um die Maddraxiversum-Karte anzuzeigen.</p>
                            <p>Du hast aktuell {{ $userPoints }} Baxx in deinem Team gesammelt.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
<div id="mission-modal" class="fixed inset-0 bg-neutral/50 z-[2000] flex items-center justify-center hidden">
    <div class="bg-base-100 p-6 rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-start mb-4">
            <h3 id="mission-title" class="text-xl font-bold"></h3>
            <button id="close-mission-modal" class="text-base-content/50 hover:text-base-content">
                ✕
            </button>
        </div>
        <div id="mission-duration" class="mb-2 text-base-content/60"></div>
        <div id="mission-description" class="mb-6 text-base-content/70"></div>
        <button id="start-mission" class="btn btn-primary w-full">
            Starte Mission
        </button>
    </div>
</div>

