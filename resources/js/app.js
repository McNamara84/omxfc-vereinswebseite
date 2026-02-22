import './bootstrap';

// Alpine.js initialisieren (Logik in alpine-init.js für Testbarkeit extrahiert)
import Alpine from 'alpinejs';
import anchor from '@alpinejs/anchor';
import focus from '@alpinejs/focus';
import { scheduleInitAlpine } from './alpine-init';

scheduleInitAlpine(Alpine, [anchor, focus]);

const DARK_THEME = 'coffee';
const LIGHT_THEME = 'caramellatte';

const prefersDark = window.__omxfcPrefersDark ?? window.matchMedia('(prefers-color-scheme: dark)');
window.__omxfcPrefersDark = prefersDark;

const applyDark = (isDark) => {
    const root = document.documentElement;
    const nextIsDark = Boolean(isDark);

    root.classList.toggle('dark', nextIsDark);
    root.dataset.theme = nextIsDark ? DARK_THEME : LIGHT_THEME;

    return nextIsDark;
};

const getStoredTheme = () => {
    try {
        const raw = window.localStorage.getItem('mary-theme');
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
};

const applyStoredOrSystemTheme = () => {
    const storedTheme = getStoredTheme();

    if (storedTheme === DARK_THEME) {
        return applyDark(true);
    }

    if (storedTheme === LIGHT_THEME) {
        return applyDark(false);
    }

    return applyDark(prefersDark.matches);
};

window.__omxfcApplyStoredTheme = applyStoredOrSystemTheme;

prefersDark.addEventListener('change', (event) => {
    // Nur reagieren wenn kein explizites Theme gespeichert ist
    const storedTheme = getStoredTheme();
    if (!storedTheme || (storedTheme !== DARK_THEME && storedTheme !== LIGHT_THEME)) {
        applyDark(event.matches);
    }
});

window.addEventListener('storage', (event) => {
    if (event.key !== 'mary-theme') {
        return;
    }

    applyStoredOrSystemTheme();
});

// Leaflet importieren
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Leaflet global verfügbar machen
window.L = L;

import './chronik';
import './char-editor';
import './todos';
import './dashboard';
import './mitglieder/accessibility';
import './mitglieder/map-utils';
import './protokolle/accordion';
import './kassenbuch/modals';
import './romantausch-gallery';
import './romantausch-dropzone';

import './polls/charts';

// Wire-navigate-kompatible Module (Dual-Event-Listener)
import './mitglied-werden';
import './home';
import './fotogalerie';
import './changelog';
import './kompendium';
import './mitglieder/karte';
import './romantausch/serien-filter';

// Admin-Charts (inkl. Chart.js) nur laden, wenn das Admin-Dashboard aktiv ist (Code-Splitting)
async function loadAdminChartsIfNeeded() {
    if (document.getElementById('admin-charts-config')) {
        try {
            const { initAdminCharts } = await import('./admin/charts');
            initAdminCharts();
        } catch (error) {
            console.error('[Admin-Charts] Laden/Initialisierung fehlgeschlagen:', error);
        }
    }
}
document.addEventListener('DOMContentLoaded', loadAdminChartsIfNeeded);
document.addEventListener('livewire:navigated', loadAdminChartsIfNeeded);

// 3D-Viewer (inkl. Three.js) nur laden, wenn ein Viewer-Container auf der Seite ist (Code-Splitting)
async function loadThreeDViewerIfNeeded() {
    if (document.querySelector('[data-three-d-viewer]')) {
        try {
            const { initThreeDViewers } = await import('./three-d-viewer');
            initThreeDViewers();
        } catch (error) {
            console.error('[3D-Viewer] Laden/Initialisierung fehlgeschlagen:', error);
        }
    }
}
document.addEventListener('DOMContentLoaded', loadThreeDViewerIfNeeded);
document.addEventListener('livewire:navigated', loadThreeDViewerIfNeeded);
