<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-xl sm:rounded-lg p-6">
                <h2 class="text-2xl font-semibold text-[#8B0116] mb-6">Mitgliederkarte</h2>
                
                <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                    <p class="text-sm text-yellow-800">
                        <strong>Hinweis:</strong> Aus Datenschutzgründen werden die Standorte der Mitglieder nicht exakt angezeigt.
                    </p>
                </div>
                <!-- Karten-Container -->
                <div id="map" class="w-full h-[600px] rounded-lg border border-gray-300"></div>
            </div>
        </div>
    </div>
    
    <!-- Leaflet JS und CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <script>
        // Daten aus dem Controller
        const memberData = {!! $memberData !!};
        const stammtischData = {!! $stammtischData !!};
        const membersCenter = { lat: {{ $membersCenterLat }}, lon: {{ $membersCenterLon }} };
        
        // Karte initialisieren
        document.addEventListener('DOMContentLoaded', function() {
            const map = L.map('map').setView([{{ $centerLat }}, {{ $centerLon }}], 6);
            
            // OpenStreetMap Layer hinzufügen
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 18
            }).addTo(map);
            
            // Icon-Styles basierend auf Mitgliedsrollen (vereinfacht)
            const vorstandIcon = L.divIcon({
                html: '<div class="marker-icon vorstand"></div>',
                className: 'custom-div-icon',
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            });
            
            const ehrenmitgliedIcon = L.divIcon({
                html: '<div class="marker-icon ehrenmitglied"></div>',
                className: 'custom-div-icon',
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            });
            
            const mitgliedIcon = L.divIcon({
                html: '<div class="marker-icon mitglied"></div>',
                className: 'custom-div-icon',
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            });
            
            // Spezielles Icon für Regionalstammtische
            const stammtischIcon = L.divIcon({
                html: '<div class="marker-icon stammtisch"><i class="fas fa-users"></i></div>',
                className: 'custom-div-icon',
                iconSize: [40, 40],
                iconAnchor: [20, 20]
            });

            // Icon für den Schwerpunkt aller Mitglieder
            const centerIcon = L.divIcon({
                html: '<div class="marker-icon center"><i class="fas fa-star"></i></div>',
                className: 'custom-div-icon',
                iconSize: [40, 40],
                iconAnchor: [20, 20]
            });
            
            // Mitglieder auf Karte platzieren
            memberData.forEach(member => {
                let icon;
                
                // Rollenbasierte Marker-Zuweisung (vereinfacht)
                if (member.role === 'Vorstand' || member.role === 'Kassenwart') {
                    icon = vorstandIcon;
                } else if (member.role === 'Ehrenmitglied') {
                    icon = ehrenmitgliedIcon;
                } else {
                    // Admin und Mitglied bekommen den gleichen Marker
                    icon = mitgliedIcon;
                }
                
                const marker = L.marker([member.lat, member.lon], {icon: icon}).addTo(map);
                
                // Popup mit Infos
                marker.bindPopup(`
                    <div class="text-center">
                        <strong>${member.name}</strong><br>
                        ${member.city}<br>
                        <em>${member.role}</em><br>
                        <a href="${member.profile_url}" class="text-blue-500 hover:underline mt-2 inline-block">
                            Zum Profil
                        </a>
                    </div>
                `);
            });
            
            // Regionalstammtische auf Karte platzieren
            stammtischData.forEach(stammtisch => {
                const marker = L.marker([stammtisch.lat, stammtisch.lon], {
                    icon: stammtischIcon,
                    zIndexOffset: 1000 // Stammtische über anderen Markern anzeigen
                }).addTo(map);
                
                // Popup mit Infos
                marker.bindPopup(`
                    <div class="text-center">
                        <strong>${stammtisch.name}</strong><br>
                        ${stammtisch.address}<br>
                        <em>${stammtisch.info}</em>
                    </div>
                `);
            });

            // Mittelpunkt aller Mitglieder markieren
            const centerMarker = L.marker([membersCenter.lat, membersCenter.lon], {
                icon: centerIcon,
                zIndexOffset: 1000
            }).addTo(map);
            centerMarker.bindPopup(`
                <div class="text-center">
                    <strong>Mittelpunkt</strong>
                </div>
            `);
            
            // Legende hinzufügen
            const legend = L.control({position: 'bottomright'});
            legend.onAdd = function(map) {
                const div = L.DomUtil.create('div', 'legend bg-white p-2 rounded shadow');
                div.innerHTML = `
                    <h4 class="font-semibold mb-2">Legende</h4>
                    <div class="flex items-center mb-1">
                        <div class="marker-icon stammtisch mr-2" style="display:inline-block;"></div>
                        <span>Regionalstammtisch</span>
                    </div>
                    <div class="flex items-center mb-1">
                        <div class="marker-icon center mr-2" style="display:inline-block;"></div>
                        <span>Mittelpunkt</span>
                    </div>
                    <div class="flex items-center mb-1">
                        <div class="marker-icon vorstand mr-2" style="display:inline-block;"></div>
                        <span>Vorstand</span>
                    </div>
                    <div class="flex items-center mb-1">
                        <div class="marker-icon ehrenmitglied mr-2" style="display:inline-block;"></div>
                        <span>Ehrenmitglied</span>
                    </div>
                    <div class="flex items-center">
                        <div class="marker-icon mitglied mr-2" style="display:inline-block;"></div>
                        <span>Mitglied</span>
                    </div>
                `;
                return div;
            };
            legend.addTo(map);
        });
    </script>
    
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
</x-app-layout>