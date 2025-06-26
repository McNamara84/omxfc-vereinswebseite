import './bootstrap';

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

// WICHTIG: Warte bis Livewire fertig initialisiert ist
if (window.Livewire) {
    // Livewire ist bereits geladen, warte auf Initialisierung
    window.addEventListener('livewire:initialized', () => {
        Alpine.start();
    });
} else {
    // Fallback für ältere Livewire Versionen
    document.addEventListener('livewire:load', () => {
        Alpine.start();
    });
    
    // Zusätzlicher Fallback
    document.addEventListener('DOMContentLoaded', () => {
        // Warte 100ms um sicherzugehen dass Livewire geladen ist
        setTimeout(() => {
            if (window.Livewire && !window.Alpine._isReady) {
                Alpine.start();
            }
        }, 100);
    });
}