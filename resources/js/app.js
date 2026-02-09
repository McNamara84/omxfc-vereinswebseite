import './bootstrap';

// Alpine.js initialisieren (Logik in alpine-init.js für Testbarkeit extrahiert)
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import persist from '@alpinejs/persist';
import { scheduleInitAlpine } from './alpine-init';

scheduleInitAlpine(Alpine, [focus, persist]);

// ── Theme: System-Preference- und Cross-Tab-Listener ──────────────────
// Die initiale Theme-Anwendung erfolgt über bootstrap-inline.js (im <head>).
// maryUI's <x-theme-toggle> steuert den Toggle via Alpine $persist.
// Hier registrieren wir nur Listener für dynamische Änderungen.

const LIGHT_THEME = 'caramellatte';
const DARK_THEME  = 'coffee';

const prefersDark = window.__omxfcPrefersDark ?? window.matchMedia('(prefers-color-scheme: dark)');
window.__omxfcPrefersDark = prefersDark;

const applyTheme = (isDark) => {
    const root = document.documentElement;
    root.classList.toggle('dark', Boolean(isDark));
    root.dataset.theme = isDark ? DARK_THEME : LIGHT_THEME;
    return root.classList.contains('dark');
};

const getStored = (key) => {
    try {
        const raw = window.localStorage.getItem(key);
        return raw ? raw.replaceAll('"', '') : null;
    } catch { return null; }
};

const applySystemPreference = (matches = prefersDark.matches, force = false) => {
    if (!force && getStored('mary-theme')) {
        return document.documentElement.classList.contains('dark');
    }
    return applyTheme(Boolean(matches));
};

const applyStoredTheme = () => {
    const stored = getStored('mary-theme');
    if (stored === DARK_THEME) return applyTheme(true);
    if (stored === LIGHT_THEME) return applyTheme(false);
    return applySystemPreference(undefined, true);
};

window.__omxfcApplySystemTheme = applySystemPreference;
window.__omxfcApplyStoredTheme = applyStoredTheme;

prefersDark.addEventListener('change', (event) => {
    applySystemPreference(event.matches);
});

window.addEventListener('storage', (event) => {
    if (event.key !== 'mary-theme') return;
    applyStoredTheme();
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
