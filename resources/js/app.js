import './bootstrap';

const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');
const applyDark = (isDark) => document.documentElement.classList.toggle('dark', isDark);
applyDark(prefersDark.matches);
prefersDark.addEventListener('change', e => applyDark(e.matches));

// Leaflet importieren
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Leaflet global verfügbar machen
window.L = L;

import './chronik';