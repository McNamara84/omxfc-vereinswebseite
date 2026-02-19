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

            {{-- Suchschlitz (ab 100 Baxx) -------------------------------- --}}
            @if($showSearch)
                <div id="kompendium-config" data-search-url="{{ route('kompendium.search') }}" data-serien-url="{{ route('kompendium.serien') }}" class="hidden"></div>
                <div class="mb-4">
                    <x-input id="search" placeholder="Suchbegriff eingeben … (Enter)" icon="o-magnifying-glass" data-testid="kompendium-search" />
                </div>

                {{-- Serien-Filter (wird per JS befüllt) ----------------------- --}}
                <div id="serien-filter" class="mb-4 hidden">
                    <fieldset role="group" aria-labelledby="serien-filter-legend">
                        <legend id="serien-filter-legend" class="text-sm font-medium text-base-content mb-2">
                            Serien filtern:
                        </legend>
                        <div id="serien-checkboxes" class="flex flex-wrap gap-x-4 gap-y-2" role="group">
                            {{-- Wird per JavaScript dynamisch befüllt --}}
                        </div>
                    </fieldset>
                </div>
            @else
                <x-alert icon="o-lock-closed" class="alert-warning mb-4">
                    Die Suche wird ab <strong>{{ $required }}</strong> Baxx freigeschaltet.
                    Dein aktueller Stand: <strong>{{ $userPoints }}</strong>.
                </x-alert>
            @endif

            {{-- Trefferliste ---------------------------------------------- --}}
            <div id="results" class="space-y-6"></div>

            {{-- Loader ----------------------------------------------------- --}}
            <div id="loading" class="hidden text-center py-4">
                <x-loading class="loading-spinner loading-md" />
            </div>
        </x-card>
    </x-member-page>
</x-app-layout>
