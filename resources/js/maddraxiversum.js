import L from 'leaflet';
import 'leaflet.markercluster';
import axios from 'axios';

// Definierte Koordinaten
const coordinates = {
    Waashton: [38.9072, -77.0369],
    Salem: [42.5195, -70.8967]
};

const map = L.map('map', { minZoom: 0 }).setView([20, 0], 2);

L.tileLayer(tileUrl, {
    attribution: '&copy; Maddraxikon | OMXFC e. V.',
    minZoom: 0,
    maxZoom: 5,
    noWrap: false,
}).addTo(map);

const cityIcon = L.icon({
    iconUrl: '/images/mapdrax/StadtMarker.png',
    iconSize: [24, 31],
    iconAnchor: [12, 31],
    popupAnchor: [0, -31]
});

const cityMarkers = L.markerClusterGroup({
    maxClusterRadius: 50,
    disableClusteringAtZoom: 4
});

// Definiere Missionen lokal (später ggf. dynamisch vom Backend holen)
const missions = {
    "Waashton": [
        {
            name: "Hexenjäger von Salem",
            description: "Im Jahr 2554 ist Waashton von den Nosfera befreit und du bist die neue Eingreiftruppe des Weltrates...",
            travel_duration: 51,
            mission_duration: 12,
            destination: "Salem",
            reward: 5,
        }
    ]
};

// Lade Städte und erstelle Marker mit Popup und Missionslink
axios.get('/maddraxikon-staedte').then(response => {
    const results = response.data.query.results;
    for (const cityName in results) {
        const city = results[cityName];
        const coordinates = city.printouts.Koordinaten;
        if (coordinates && coordinates.length > 0) {
            const lat = coordinates[0].lat;
            const lon = coordinates[0].lon;

            const marker = L.marker([lat, lon], {
                icon: cityIcon,
                title: cityName
            });

            // Popup-Inhalt erstellen
            let popupContent = `
                <div class="text-center">
                    <h3 class="font-bold">${cityName}</h3>
                    <a href="${city.fullurl}" target="_blank" class="text-blue-500 hover:underline">
                        im Maddraxikon
                    </a>
                </div>
            `;

            // Missionen anzeigen, falls vorhanden
            if (missions[cityName]) {
                popupContent += '<div class="mt-2 font-semibold">Missionen:</div><ul>';
                missions[cityName].forEach((mission, index) => {
                    popupContent += `
                        <li>
                            <button class="mission-link text-blue-600 hover:underline" data-city="${cityName}" data-index="${index}">
                                ${mission.name}
                            </button>
                        </li>`;
                });
                popupContent += '</ul>';
            }

            marker.bindPopup(popupContent);
            cityMarkers.addLayer(marker);
        }
    }
    map.addLayer(cityMarkers);
}).catch(console.error);

// Popup-Event für Missionsbutton
map.on('popupopen', function (e) {
    document.querySelectorAll('.mission-link').forEach(link => {
        link.addEventListener('click', function () {
            const city = this.dataset.city;
            const index = parseInt(this.dataset.index);
            openMissionModal(missions[city][index]);
        });
    });
});

const modal = document.getElementById('mission-modal');
const missionTitle = document.getElementById('mission-title');
const missionDescription = document.getElementById('mission-description');
const missionDuration = document.getElementById('mission-duration');
const startMissionButton = document.getElementById('start-mission');
const closeModalButton = document.getElementById('close-mission-modal');

export function openMissionModal(mission) {
    missionTitle.textContent = mission.name;
    missionDescription.textContent = mission.description;
    missionDuration.textContent = `Dauer: ${mission.mission_duration} min`;
    startMissionButton.dataset.mission = JSON.stringify(mission);
    modal.classList.replace('hidden', 'flex');
}

closeModalButton.addEventListener('click', () => {
    modal.classList.replace('flex', 'hidden');
});

export function calculateBearing(from, to) {
    const startLat = from[0] * Math.PI / 180;
    const startLng = from[1] * Math.PI / 180;
    const destLat = to[0] * Math.PI / 180;
    const destLng = to[1] * Math.PI / 180;

    const y = Math.sin(destLng - startLng) * Math.cos(destLat);
    const x = Math.cos(startLat) * Math.sin(destLat) -
              Math.sin(startLat) * Math.cos(destLat) * Math.cos(destLng - startLng);
    
    let bearing = Math.atan2(y, x) * 180 / Math.PI;
    bearing = (bearing + 360) % 360; // Normalisiere auf 0-360 Grad
    
    return bearing;
}

export function animateGlider(from, to, durationSeconds) {
    return new Promise((resolve) => {
        // Berechne den initialen Kurs
        const bearing = calculateBearing(from, to);
        console.log('Berechneter Kurs:', bearing);

        // Erstelle das Icon
        const gliderIcon = L.divIcon({
            className: 'glider-icon',
            html: `<img src="/images/mapdrax/GleiterPlayer.png" alt="Gleiter von oben" style="width: 32px; height: 32px; transform: rotate(${bearing}deg);">`,
            iconSize: [32, 32],
            iconAnchor: [16, 16]
        });

        const marker = L.marker(from, {
            icon: gliderIcon,
            zIndexOffset: 1000
        }).addTo(map);

        // Füge CSS für den Marker hinzu
        const style = document.createElement('style');
        style.textContent = `
            .glider-icon {
                display: block;
            }
            .glider-icon img {
                width: 32px;
                height: 32px;
                transition: transform 0.1s linear;
            }
        `;
        document.head.appendChild(style);

        // Animation
        const fps = 30;
        const steps = durationSeconds * fps;
        let step = 0;

        const latStep = (to[0] - from[0]) / steps;
        const lngStep = (to[1] - from[1]) / steps;

        // Erstelle eine LatLngBounds für beide Punkte
        const bounds = L.latLngBounds([from, to]);
        map.fitBounds(bounds, { padding: [50, 50] });

        const interval = setInterval(() => {
            step++;
            
            if (step > steps) {
                clearInterval(interval);
                map.removeLayer(marker);
                resolve();
                return;
            }

            const newLat = from[0] + latStep * step;
            const newLng = from[1] + lngStep * step;

            // Aktualisiere nur die Position
            marker.setLatLng([newLat, newLng]);

            // Zentriere die Karte sanft
            map.panTo([newLat, newLng], {
                animate: true,
                duration: 1/fps
            });
        }, 1000/fps);
    });
}

// Button "Mission starten" Event
startMissionButton.addEventListener('click', async () => {
    const mission = JSON.parse(startMissionButton.dataset.mission);
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    try {
        // Starte die Mission auf dem Backend
        const response = await axios.post('/mission/starten', {
            name: mission.name,
            origin: "Waashton",
            destination: mission.destination,
            travel_duration: mission.travel_duration,
            mission_duration: mission.mission_duration,
        }, {
            headers: { 'X-CSRF-TOKEN': csrfToken }
        });
        console.log('Mission gestartet:', response.data);
        modal.classList.replace('flex', 'hidden');

        // 1. Flug zur Missionsdestination
        await animateGlider(coordinates.Waashton, coordinates[mission.destination], mission.travel_duration);

        // 2. Verweilen am Ziel
        await new Promise(resolve => setTimeout(resolve, mission.mission_duration * 1000));

        // 3. Rückflug zur Basis (Waashton)
        await animateGlider(coordinates[mission.destination], coordinates.Waashton, mission.travel_duration);

        // 4. Mission als abgeschlossen markieren
        console.log('Sende Status-Check-Request...');
        const statusResponse = await axios.post('/mission/status-pruefen', {}, {
            headers: { 'X-CSRF-TOKEN': csrfToken }
        });
        console.log('Status-Check-Response:', statusResponse.data);

        if (statusResponse.data.status === 'completed') {
            alert("Mission erfolgreich abgeschlossen und Gleiter zurück an der Basis.");
        } else {
            console.error('Unerwarteter Status:', statusResponse.data);
            alert("Mission konnte nicht als abgeschlossen markiert werden. Bitte versuche es später erneut.");
        }
    } catch (error) {
        console.error('Fehler bei der Mission:', error);
        if (error.response && error.response.data && error.response.data.error) {
            alert(error.response.data.error);
        } else {
            alert("Ein Fehler ist aufgetreten. Bitte versuche es später erneut.");
        }
    }
});

// Funktion zum Laden des Mission-Status
async function loadMissionStatus() {
    try {
        const response = await axios.get('/mission/status');
        console.log('Mission-Status geladen:', response.data);

        if (response.data.status !== 'none') {
            const mission = response.data.mission;
            const currentLocation = response.data.current_location;
            const currentPosition = response.data.status;

            // Berechne die verbleibende Zeit
            const now = new Date();
            const startedAt = new Date(mission.started_at);
            const elapsedSeconds = Math.floor((now - startedAt) / 1000);
            const totalDuration = mission.travel_duration + mission.mission_duration + mission.travel_duration;
            const remainingSeconds = totalDuration - elapsedSeconds;

            if (remainingSeconds > 0) {
                // Positioniere den Gleiter
                if (currentPosition === 'traveling') {
                    if (elapsedSeconds < mission.travel_duration) {
                        // Auf dem Hinflug
                        await animateGlider(
                            coordinates[mission.origin],
                            coordinates[mission.destination],
                            remainingSeconds
                        );
                    } else {
                        // Auf dem Rückflug
                        await animateGlider(
                            coordinates[mission.destination],
                            coordinates[mission.origin],
                            remainingSeconds
                        );
                    }
                } else if (currentPosition === 'in_mission') {
                    // Am Zielort
                    const gliderIcon = L.icon({
                        iconUrl: '/images/mapdrax/GleiterPlayer.png',
                        iconSize: [32, 32],
                        iconAnchor: [16, 16],
                    });

                    const marker = L.marker(coordinates[mission.destination], { icon: gliderIcon }).addTo(map);
                    
                    // Warte die verbleibende Zeit
                    await new Promise(resolve => setTimeout(resolve, remainingSeconds * 1000));
                    
                    // Rückflug
                    await animateGlider(
                        coordinates[mission.destination],
                        coordinates[mission.origin],
                        mission.travel_duration
                    );
                }

                // Mission als abgeschlossen markieren
                const statusResponse = await axios.post('/mission/status-pruefen', {}, {
                    headers: { 'X-CSRF-TOKEN': csrfToken }
                });

                if (statusResponse.data.status === 'completed') {
                    alert("Mission erfolgreich abgeschlossen und Gleiter zurück an der Basis.");
                }
            }
        }
    } catch (error) {
        console.error('Fehler beim Laden des Mission-Status:', error);
    }
}

// Lade den Mission-Status beim Start
document.addEventListener('DOMContentLoaded', loadMissionStatus);

