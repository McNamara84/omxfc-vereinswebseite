<x-member-layout>
    <div class="py-4 flex flex-col h-[calc(100vh-4rem)]">
        <div class="flex-grow mx-auto w-full px-4 lg:px-6">
            <div class="bg-base-100 overflow-hidden shadow-xl rounded-lg h-full flex flex-col">
                <div class="p-4 flex-grow flex flex-col">
                    @if ($showMap)


                        <div id="map" data-maddraxiversum-map data-tile-url="{{ $tileUrl }}" class="w-full flex-grow border border-base-content/10 rounded"></div>

                    @else
                        <div class="bg-warning/10 border-l-4 border-warning text-warning-content p-4">
                            <p class="font-bold">Zugriff eingeschränkt</p>
                            <p>Du musst diese Belohnung zuerst im Bereich <a href="/belohnungen" class="underline font-semibold">Belohnungen einlösen</a> freischalten.</p>
                            <p>Du hast aktuell {{ $userPoints }} Baxx gesammelt.</p>

                            @if(($rewardConfiguration['effective_rule']['is_active'] ?? false) && ($rewardConfiguration['effective_rule']['points'] ?? 0) > 0)
                                <p class="mt-3 text-sm">
                                    Aktuelle Vergaberegel für Missionen: <span class="font-semibold">{{ $rewardConfiguration['effective_rule']['rule_label'] }}</span>
                                </p>
                            @endif

                            @if($rewardConfiguration['prominent_special_offer'] ?? null)
                                <p class="mt-2 text-sm font-semibold">{{ $rewardConfiguration['prominent_special_offer']['banner_text'] }}</p>
                                @if($rewardConfiguration['prominent_special_offer']['banner_end_text'])
                                    <p class="text-sm">{{ $rewardConfiguration['prominent_special_offer']['banner_end_text'] }}</p>
                                @endif
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Mission Modal --}}
    <x-modal id="mission-modal" without-trap-focus aria-labelledby="mission-title">
        <h3 id="mission-title" class="text-xl font-bold mb-4"></h3>
        <div id="mission-duration" class="mb-2 text-base-content"></div>
        <div id="mission-description" class="mb-6 text-base-content"></div>
        <x-slot:actions>
            <button type="button" id="start-mission" class="btn btn-primary w-full">Starte Mission</button>
        </x-slot:actions>
    </x-modal>
</x-member-layout>
