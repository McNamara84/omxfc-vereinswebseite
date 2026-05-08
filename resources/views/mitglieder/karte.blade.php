@php($pageDescription = $isUnlocked
    ? 'Die Vereinskarte zeigt Wohnorte bewusst nur angenähert und bündelt Mitglieder sowie Regionalstammtische in einer gemeinsamen Kartenansicht.'
    : 'Schalte die Mitgliederkarte mit Baxx frei, um die geschützte Kartenansicht des Vereins vollständig zu nutzen.')

<x-app-layout>
    <x-member-page>
        <x-ui.page-header
            eyebrow="Mitgliederbereich"
            title="Mitgliederkarte"
            :description="$pageDescription"
            data-testid="page-title"
        >
            <x-slot:actions>
                <x-button
                    label="Zur Mitgliederliste"
                    icon="o-users"
                    link="{{ route('mitglieder.index') }}"
                    wire:navigate
                    class="btn-outline"
                />
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.panel>
                @if (session('success'))
                    <x-alert icon="o-check-circle" class="alert-success mb-4" dismissible>
                        {{ session('success') }}
                    </x-alert>
                @endif

                @if ($errors->has('reward'))
                    <x-alert icon="o-exclamation-circle" class="alert-error mb-4" dismissible>
                        {{ $errors->first('reward') }}
                    </x-alert>
                @endif

                <div id="member-map-note">
                    <x-alert class="alert-warning mb-4" icon="o-exclamation-triangle" role="note">
                        Aus Datenschutzgründen werden die Wohnorte der Mitglieder nicht exakt angezeigt.
                    </x-alert>
                </div>
                <div class="relative">
                    <div class="{{ $isUnlocked ? '' : 'pointer-events-none select-none blur-[10px] saturate-0' }}">
                        <div
                            id="map"
                            class="w-full h-[600px] rounded-lg border border-base-content/10"
                            style="min-height: 600px;"
                            data-member-map
                            role="region"
                            aria-label="Mitgliederkarte"
                            aria-describedby="member-map-note"
                            tabindex="{{ $isUnlocked ? '0' : '-1' }}"
                            @if (! $isUnlocked) aria-hidden="true" @endif
                        ></div>
                    </div>

                    @unless($isUnlocked)
                        <div class="absolute inset-0 flex items-center justify-center rounded-lg bg-base-100/70 p-6 backdrop-blur-sm">
                            <div class="max-w-lg rounded-3xl border border-base-content/10 bg-base-100/90 p-6 text-center shadow-xl">
                                <h2 class="text-2xl font-semibold text-primary">Mitgliederkarte freischalten</h2>
                                <p class="mt-3 text-sm text-base-content/80">
                                    Die vollständige Vereinskarte ist geschützt und kann mit Baxx freigeschaltet werden.
                                </p>

                                <div class="mt-4 flex flex-wrap justify-center gap-2">
                                    <x-badge :value="$reward->cost_baxx . ' Baxx Preis'" class="badge-primary" icon="o-currency-dollar" />
                                    @if (is_int($availableBaxx))
                                        <x-badge :value="$availableBaxx . ' Baxx verfügbar'" class="badge-success" icon="o-banknotes" />
                                    @else
                                        <x-badge value="Guthaben wird geprüft" class="badge-warning" icon="o-exclamation-triangle" />
                                    @endif
                                </div>

                                @if (! $reward->is_active)
                                    <p class="mt-4 text-sm text-base-content/75">
                                        Die Freischaltung ist aktuell im Reward-Adminbereich deaktiviert.
                                    </p>
                                @elseif ($walletWarning)
                                    <p class="mt-4 text-sm text-base-content/75">
                                        {{ $walletWarning }}
                                    </p>
                                @elseif ($canPurchase)
                                    <form method="POST" action="{{ route('mitglieder.karte.purchase') }}" class="mt-5">
                                        @csrf
                                        <x-button type="submit" label="Mitgliederkarte jetzt freischalten" icon="o-lock-open" class="btn-primary" />
                                    </form>
                                @else
                                    <p class="mt-4 text-sm text-base-content/75">
                                        Dir fehlen aktuell {{ $missingBaxx }} Baxx für die Freischaltung.
                                    </p>
                                    <div class="mt-5 flex flex-wrap justify-center gap-3">
                                        <x-button label="Zu Baxx verdienen" link="{{ route('todos.index') }}" wire:navigate icon="o-clipboard-document-list" class="btn-primary btn-sm" />
                                        <x-button label="Im Belohnungsbereich ansehen" link="{{ route('rewards.index') }}" wire:navigate icon="o-gift" class="btn-outline btn-sm" />
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endunless
                </div>
                </x-ui.panel>

    <!-- Daten für die Karte als data-Attribute -->
    <div id="member-map-config" class="hidden"
        data-members="{{ $memberData }}"
        data-stammtische="{{ $stammtischData }}"
        data-center-lat="{{ $centerLat }}"
        data-center-lon="{{ $centerLon }}"
        data-members-center-lat="{{ $membersCenterLat }}"
        data-members-center-lon="{{ $membersCenterLon }}"
    ></div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

    <style>
        .marker-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 0 5px rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
        }
        
        .marker-icon.vorstand {
            background-color: #0056b3; 
        }
        
        .marker-icon.ehrenmitglied {
            background-color: #ffc107;
        }
        
        .marker-icon.mitglied {
            background-color: #6c757d;
        }
        
        .marker-icon.stammtisch {
            background-color: #e63946;
            width: 30px;
            height: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 14px;
        }

        .marker-icon.center {
            background-color: #28a745;
            width: 30px;
            height: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 14px;
        }
        
        .custom-div-icon {
            background: none;
            border: none;
        }
        
        .legend {
            line-height: 1.5;
            font-size: 0.875rem;
        }
        
        .legend .marker-icon {
            width: 16px;
            height: 16px;
            margin-right: 5px;
        }
        
        .legend .marker-icon.stammtisch {
            width: 16px;
            height: 16px;
            font-size: 8px;
        }

        .legend .marker-icon.center {
            width: 16px;
            height: 16px;
            font-size: 8px;
        }
    </style>
    </x-member-page>
</x-app-layout>