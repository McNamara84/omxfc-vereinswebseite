<x-app-layout>
    <x-member-page>
        <x-card shadow>
                <x-header title="Mitgliederkarte" useH1 data-testid="page-title" />

                <div id="member-map-note">
                    <x-alert class="alert-warning mb-4" icon="o-exclamation-triangle" role="note">
                        Aus Datenschutzgründen werden die Standorte der Mitglieder nicht exakt angezeigt.
                    </x-alert>
                </div>
                <!-- Karten-Container -->
                <div
                    id="map"
                    class="w-full h-[600px] rounded-lg border border-base-content/10"
                    data-member-map
                    role="region"
                    aria-label="Mitgliederkarte"
                    aria-describedby="member-map-note"
                    tabindex="0"
                ></div>
        </x-card>

    <!-- Daten für die Karte als data-Attribute -->
    <div id="member-map-config" class="hidden"
        data-members="{{ $memberData }}"
        data-stammtische="{{ $stammtischData }}"
        data-center-lat="{{ $centerLat }}"
        data-center-lon="{{ $centerLon }}"
        data-members-center-lat="{{ $membersCenterLat }}"
        data-members-center-lon="{{ $membersCenterLon }}"
    ></div>

    <!-- Font Awesome für Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    
    <!-- Styles für die Icons -->
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