import './bootstrap';
import './mitglied_werden';

// Leaflet importieren
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Leaflet global verfügbar machen
window.L = L;

// Alpine.js
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';

// Alpine Plugins registrieren
Alpine.plugin(focus);

// Alpine global verfügbar machen
window.Alpine = Alpine;

// Warte auf Livewire bevor Alpine startet
document.addEventListener('DOMContentLoaded', () => {
    // Stelle sicher dass Livewire geladen ist
    if (window.Livewire) {
        Alpine.start();
    } else {
        // Fallback: Warte auf Livewire
        document.addEventListener('livewire:load', () => {
            Alpine.start();
        });
    }
});