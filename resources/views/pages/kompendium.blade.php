<x-app-layout title="Kompendium – Offizieller MADDRAX Fanclub e. V." description="Volltextsuche durch Maddrax-Romane für Mitglieder.">
    <x-member-page class="max-w-4xl">
        <x-header title="Maddrax-Kompendium" />

        <x-card shadow>
            {{-- Info-Card --------------------------------------------------- --}}
            <div class="mb-6 p-4 border-l-4 border-primary bg-base-200 rounded">
                @if($indexierteRomaneSummary->isEmpty())
                    <p class="text-base-content">
                        Aktuell sind keine Romane für die Suche indexiert.
                    </p>
                @else
                    <p class="mb-2">Aktuell sind die folgenden Romane für die Suche indexiert:</p>
                    <ul class="list-disc ml-6">
                        @foreach($indexierteRomaneSummary as $gruppe)
                            <li>
                                <strong>{{ $gruppe['serie_name'] }}</strong>
                                ({{ $gruppe['beschreibung'] }})
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if($istAdmin ?? false)
                    <x-button label="Kompendium verwalten" link="{{ route('kompendium.admin') }}" icon="o-cog-6-tooth" class="btn-ghost btn-sm text-primary mt-4" />
                @endif
            </div>

            @if($showSearch)
                @livewire('kompendium-suche')
            @else
                @if($kompendiumReward && $kompendiumReward->is_active)
                    @livewire('kompendium-kauf-overlay', [
                        'rewardId' => $kompendiumReward->id,
                    ])
                @else
                    <x-alert icon="o-lock-closed" class="alert-warning mb-4">
                        Das Kompendium ist derzeit nicht verfügbar.
                    </x-alert>
                @endif
            @endif
        </x-card>
    </x-member-page>
</x-app-layout>
