<x-app-layout>
    <div class="py-2 md:py-4 flex flex-col h-[calc(100vh-4rem)]">
        <div class="flex-grow mx-auto w-full px-2 sm:px-4 lg:px-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg h-full flex flex-col">
                <div class="p-2 md:p-4 bg-white dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent border-b border-gray-200 dark:border-gray-700 flex-grow flex flex-col">
                    @if ($showMap)
                        <p class="mb-2 text-gray-600 dark:text-gray-400 text-sm">
                            Herzlich willkommen im Maddraxiversum! Reise zum Weltrat nach Waashton um zu prüfen, ob der Weltrat neue Aufträge für dich hat!
                        </p>
                        {{-- Leaflet CSS --}}
                        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
                              integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
                              crossorigin=""/>
                        {{-- Leaflet JavaScript --}}
                        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                                integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
                                crossorigin=""></script>
                        {{-- Leaflet Marker Cluster --}}
                        <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
                        <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
                        <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
                        
                        <div class="flex flex-col h-full relative">
                            {{-- Karte --}}
                            <div id="map" class="w-full flex-grow border dark:border-gray-600 rounded">
                            </div>
                            
                            {{-- Legende (außerhalb der Karte positioniert, aber über ihr) --}}
                            <div id="map-legend" class="absolute top-3 right-3 bg-white dark:bg-gray-700 p-2 rounded shadow-md z-[1000] text-sm opacity-90 hover:opacity-100 transition-opacity">
                                <div class="font-bold mb-1">Legende</div>
                                <div class="flex items-center mb-1">
                                    <input type="checkbox" id="toggle-cities" checked class="mr-2">
                                    <label for="toggle-cities" class="cursor-pointer">Städte anzeigen</label>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Missionsmodal --}}
                        <div id="mission-modal" class="fixed inset-0 bg-black bg-opacity-50 z-[2000] flex items-center justify-center hidden">
                            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
                                <div class="flex justify-between items-start mb-4">
                                    <h3 id="mission-title" class="text-xl font-bold"></h3>
                                    <button id="close-mission-modal" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <div id="mission-duration" class="mb-2 text-gray-600 dark:text-gray-400"></div>
                                <div id="mission-description" class="mb-6 text-gray-700 dark:text-gray-300"></div>
                                <button id="start-mission" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded w-full">
                                    Starte Mission
                                </button>
                            </div>
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
                                map.setView([20, 0], 2); // Weltansicht als Startpunkt
                                
                                // Eigener Stadt-Marker
                                const cityIcon = L.icon({
                                    iconUrl: '/images/mapdrax/StadtMarker.png',
                                    iconSize: [24, 31],
                                    iconAnchor: [12, 31],
                                    popupAnchor: [0, -31]
                                });
                                
                                // Marker-Gruppen erstellen
                                const cityMarkers = L.markerClusterGroup({
                                    maxClusterRadius: 50,
                                    disableClusteringAtZoom: 4
                                });
                                
                                // Missionen aus dem Controller
                                const missions = {
                                    "Waashton": [
                                        {
                                            name: "Hexenjäger von Salem",
                                            description: "Im Jahr 2554 ist Waashton von den Nosfera befreit und du bist die neue Eingreiftruppe des Weltrates -- aber leider noch ohne konkreten Auftrag. Du schlenderst auf der Straße vor dem Pentagon herum, gehst in deine Stammkneipe und beginnst bereits dich zu langweilen. Da trifft es sich gut, dass zufällig ein rot lackiertes Trike an dir vorbeiknattert und auf das Pentagon zufährt. Du witterst sogleich einen Auftrag, als der Trike-Fahrer Jami Tenner aus Nuu'ork mit den Wachen am Eingangstor diskutiert, dass er mit seiner Begleitung, einer ängstlichen jungen Frau, unbedingt beim Weltrat vorgelassen werden muss. Diese Frau heißt Carry und kommt aus der Ostküstenstadt Salem, in der ein selbstberufener Hexenjäger Frauen als Hexen umbringen lässt. General Aran Kormak, Captain Warren Yates und der Präsident Mr. Black nehmen sich auch gleich Zeit für das Anliegen der beiden Besucher, sind zwar zunächst skeptisch, aber erteilen dir dann doch den Auftrag, vor Ort nach dem Rechten zu sehen.",
                                            duration: 510,      // Dauer der Mission in Minuten
                                            level: 10,          // Benötigte Punkte um diese Mission zu sehen
                                            probSuccess: 100,   // Wahrscheinlichkeit in % für Erfolg de Mission
                                            reward: 5,          // Belohnung in Punkten bei erfolgreichem Abschluss dieser Mission
                                            msgFail: "Die Mission ist gescheitert. Du hast keine Punkte erhalten.",
                                            destination: "Salem",
                                            duration: 120, // Dauer der Mission in Minuten
                                        }
                                    ]
                                };
                                
                                // Modal-Funktionen
                                const modal = document.getElementById('mission-modal');
                                const closeModal = document.getElementById('close-mission-modal');
                                const missionTitle = document.getElementById('mission-title');
                                const missionDuration = document.getElementById('mission-duration');
                                const missionDescription = document.getElementById('mission-description');
                                const startMissionButton = document.getElementById('start-mission');
                                
                                function openMissionModal(mission) {
                                    missionTitle.textContent = mission.name;
                                    missionDuration.textContent = `Dauer: ${mission.duration}`;
                                    missionDescription.textContent = mission.description;
                                    
                                    // Daten für den "Starte Mission"-Button speichern
                                    startMissionButton.dataset.missionName = mission.name;
                                    
                                    modal.classList.remove('hidden');
                                }
                                
                                closeModal.addEventListener('click', function() {
                                    modal.classList.add('hidden');
                                });
                                
                                // Schließen bei Klick außerhalb des Modals
                                modal.addEventListener('click', function(event) {
                                    if (event.target === modal) {
                                        modal.classList.add('hidden');
                                    }
                                });
                                
                                // Starte Mission Button (Placeholder für zukünftige Funktionalität)
                                startMissionButton.addEventListener('click', function() {
                                    // Hier kommt später der Code zum Starten der Mission
                                    console.log(`Mission "${this.dataset.missionName}" gestartet`);
                                    modal.classList.add('hidden');
                                });
                                
                                // Städte vom Backend-Proxy laden
                                fetch('/maddraxikon-cities')
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data && data.query && data.query.results) {
                                            const results = data.query.results;
                                            
                                            // Durch die Ergebnisse iterieren und Marker hinzufügen
                                            for (const cityName in results) {
                                                const city = results[cityName];
                                                const coordinates = city.printouts.Koordinaten;
                                                
                                                // Nur Städte mit Koordinaten anzeigen
                                                if (coordinates && coordinates.length > 0) {
                                                    const lat = coordinates[0].lat;
                                                    const lon = coordinates[0].lon;
                                                    
                                                    const marker = L.marker([lat, lon], {
                                                        icon: cityIcon,
                                                        title: cityName
                                                    });
                                                    
                                                    // Prüfen, ob die Stadt Missionen hat
                                                    const hasMissions = missions[cityName] && missions[cityName].length > 0;
                                                    
                                                    let popupContent = `
                                                        <div class="text-center">
                                                            <h3 class="font-bold">${cityName}</h3>
                                                            <a href="${city.fullurl}" target="_blank" class="text-blue-500 hover:underline">
                                                                im Maddraxikon
                                                            </a>
                                                        </div>
                                                    `;
                                                    
                                                    // Missionsliste hinzufügen, falls vorhanden
                                                    if (hasMissions) {
                                                        popupContent += `<div class="mt-3"><h4 class="font-semibold">Verfügbare Missionen:</h4><ul class="list-disc pl-5">`;
                                                        
                                                        missions[cityName].forEach((mission, index) => {
                                                            popupContent += `<li><a href="#" class="mission-link text-blue-500 hover:underline" data-city="${cityName}" data-index="${index}">${mission.name}</a></li>`;
                                                        });
                                                        
                                                        popupContent += `</ul></div>`;
                                                    }
                                                    
                                                    const popup = L.popup().setContent(popupContent);
                                                    marker.bindPopup(popup);
                                                    
                                                    // Event-Listener für Missionslinks hinzufügen
                                                    marker.on('popupopen', function() {
                                                        document.querySelectorAll('.mission-link').forEach(link => {
                                                            link.addEventListener('click', function(e) {
                                                                e.preventDefault();
                                                                const city = this.getAttribute('data-city');
                                                                const index = parseInt(this.getAttribute('data-index'));
                                                                openMissionModal(missions[city][index]);
                                                            });
                                                        });
                                                    });
                                                    
                                                    cityMarkers.addLayer(marker);
                                                }
                                            }
                                            
                                            // Marker-Gruppe zur Karte hinzufügen
                                            map.addLayer(cityMarkers);
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Fehler beim Laden der Städte:', error);
                                    });
                                
                                // Event-Listener für die Legende
                                document.getElementById('toggle-cities').addEventListener('change', function() {
                                    if (this.checked) {
                                        map.addLayer(cityMarkers);
                                    } else {
                                        map.removeLayer(cityMarkers);
                                    }
                                });
                                
                                function updateMapSize() {
                                    map.invalidateSize();
                                }
                                
                                // Beim Start aktualisieren
                                setTimeout(updateMapSize, 100);
                                
                                // Bei Größenänderung aktualisieren
                                let resizeTimeout;
                                window.addEventListener('resize', () => {
                                    clearTimeout(resizeTimeout);
                                    resizeTimeout = setTimeout(updateMapSize, 150);
                                });
                                
                                // Bei Tab/Panelwechsel oder anderen DOM-Änderungen aktualisieren
                                const observer = new MutationObserver(updateMapSize);
                                observer.observe(document.body, { 
                                    attributes: true, 
                                    childList: true, 
                                    subtree: true 
                                });
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