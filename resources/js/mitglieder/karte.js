/**
 * Mitgliederkarte: Leaflet-Karte mit Mitgliedern, Stammtischen und Legende.
 *
 * Wird über app.js gebundelt geladen. Guard-Pattern: Initialisierung
 * läuft nur, wenn #map[data-member-map] auf der Seite existiert.
 *
 * Benötigt data-Attribute in einem #member-map-config Element:
 * - data-members     → JSON mit Mitglieder-Daten
 * - data-stammtische → JSON mit Stammtisch-Daten
 * - data-center-lat  → Karten-Zentrum Latitude
 * - data-center-lon  → Karten-Zentrum Longitude
 * - data-members-center-lat → Mitglieder-Schwerpunkt Latitude
 * - data-members-center-lon → Mitglieder-Schwerpunkt Longitude
 */

let mapInstance = null;

function initMitgliederKarte() {
    const mapEl = document.querySelector('#map[data-member-map]');
    if (!mapEl) return;

    // Cleanup vorheriger Karten-Instanz (bei SPA-Navigation)
    if (mapInstance) {
        mapInstance.remove();
        mapInstance = null;
    }

    const configEl = document.getElementById('member-map-config');
    if (!configEl) return;

    let memberData, stammtischData;
    try {
        memberData = JSON.parse(configEl.dataset.members || '[]');
        stammtischData = JSON.parse(configEl.dataset.stammtische || '[]');
    } catch (e) {
        console.error('Fehler beim Parsen der Kartendaten:', e);
        return;
    }

    const centerLat = parseFloat(configEl.dataset.centerLat) || 51.0;
    const centerLon = parseFloat(configEl.dataset.centerLon) || 10.0;
    const membersCenterLat = parseFloat(configEl.dataset.membersCenterLat) || centerLat;
    const membersCenterLon = parseFloat(configEl.dataset.membersCenterLon) || centerLon;

    const map = L.map('map').setView([centerLat, centerLon], 6);
    mapInstance = map;

    // OpenStreetMap Layer hinzufügen
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 18
    }).addTo(map);

    // Icon-Styles basierend auf Mitgliedsrollen
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

    const iconByType = {
        vorstand: vorstandIcon,
        ehrenmitglied: ehrenmitgliedIcon,
        mitglied: mitgliedIcon,
    };

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
        const mapUtils = window.omxfcMemberMap ?? {};
        const type = typeof mapUtils.getRoleIconType === 'function'
            ? mapUtils.getRoleIconType(member.role)
            : 'mitglied';
        const icon = iconByType[type] ?? mitgliedIcon;

        const marker = L.marker([member.lat, member.lon], {icon}).addTo(map);

        const popupContent = typeof mapUtils.buildMemberPopup === 'function'
            ? mapUtils.buildMemberPopup(member)
            : `
                <div class="text-center">
                    <strong>${member.name}</strong><br>
                    ${member.city}<br>
                    <em>${member.role}</em><br>
                    <a href="${member.profile_url}" class="text-blue-500 hover:underline mt-2 inline-block">
                        Zum Profil
                    </a>
                </div>
            `;

        marker.bindPopup(popupContent);
    });

    // Regionalstammtische auf Karte platzieren
    stammtischData.forEach(stammtisch => {
        const marker = L.marker([stammtisch.lat, stammtisch.lon], {
            icon: stammtischIcon,
            zIndexOffset: 1000
        }).addTo(map);

        marker.bindPopup(`
            <div class="text-center">
                <strong>${stammtisch.name}</strong><br>
                ${stammtisch.address}<br>
                <em>${stammtisch.info}</em>
            </div>
        `);
    });

    // Mittelpunkt aller Mitglieder markieren
    const centerMarker = L.marker([membersCenterLat, membersCenterLon], {
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
    legend.onAdd = function() {
        const div = L.DomUtil.create('div', 'legend bg-white p-2 rounded shadow');
        div.setAttribute('role', 'complementary');
        div.setAttribute('aria-label', 'Legende der Mitgliederkarte');
        div.setAttribute('tabindex', '0');

        const mapUtils = window.omxfcMemberMap ?? {};
        if (typeof mapUtils.createLegendMarkup === 'function') {
            div.innerHTML = mapUtils.createLegendMarkup();
        } else {
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
        }

        return div;
    };
    legend.addTo(map);
}

document.addEventListener('DOMContentLoaded', initMitgliederKarte);
document.addEventListener('livewire:navigated', initMitgliederKarte);
