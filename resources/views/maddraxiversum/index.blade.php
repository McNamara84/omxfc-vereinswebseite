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
                            <p>Du musst diese Belohnung zuerst unter <a href="/belohnungen" class="underline font-semibold">Belohnungen</a> freischalten.</p>
                            <p>Du hast aktuell {{ $userPoints }} Baxx gesammelt.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Mission Modal --}}
    <x-modal id="mission-modal">
        <h3 id="mission-title" class="text-xl font-bold mb-4"></h3>
        <div id="mission-duration" class="mb-2 text-base-content"></div>
        <div id="mission-description" class="mb-6 text-base-content"></div>
        <x-slot:actions>
            <x-button id="start-mission" label="Starte Mission" class="btn-primary w-full" />
        </x-slot:actions>
    </x-modal>
</x-app-layout>

