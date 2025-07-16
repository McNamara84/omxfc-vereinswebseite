import './bootstrap';

// Leaflet importieren
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
window.L = L;

// Alpine Focus Plugin über Livewire laden
document.addEventListener('livewire:init', () => {
    // Alpine ist jetzt verfügbar über Livewire
    Alpine.plugin(AlpineFloatingUI)
    
    // Focus Plugin
    import('@alpinejs/focus').then(module => {
        Alpine.plugin(module.default)
    })
})