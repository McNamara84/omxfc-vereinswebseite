import './bootstrap';

// Alpine.js initialisieren
// Livewire 4 bündelt Alpine, aber injiziert es nur auf Seiten mit Livewire-Komponenten.
// Für Seiten ohne Livewire-Komponenten (z.B. Kassenbuch mit reinem Blade + Alpine)
// müssen wir Alpine selbst laden und starten.
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';

// Hilfsfunktion zur sicheren Alpine-Initialisierung
const initAlpine = () => {
    // Nur initialisieren, wenn Alpine noch nicht läuft
    if (!window.Alpine?._x_dataStack) {
        window.Alpine = Alpine;
        if (typeof Alpine.plugin === 'function') {
            Alpine.plugin(focus);
        }
        if (typeof Alpine.start === 'function') {
            Alpine.start();
        }
    }
};

// Warte auf DOMContentLoaded, um zu prüfen ob Livewire Alpine bereits geladen hat
document.addEventListener('DOMContentLoaded', initAlpine);

// Falls DOM bereits geladen ist und Alpine noch nicht läuft
if (document.readyState !== 'loading') {
    initAlpine();
}

const prefersDark = window.__omxfcPrefersDark ?? window.matchMedia('(prefers-color-scheme: dark)');
const getSystemPrefersDark = () => prefersDark.matches;
window.__omxfcPrefersDark = prefersDark;

const applyDark = (isDark) => {
    const root = document.documentElement;
    const nextIsDark = Boolean(isDark);

    root.classList.toggle('dark', nextIsDark);
    root.dataset.theme = nextIsDark ? 'dark' : 'light';

    return root.classList.contains('dark');
};

const getStoredTheme = () => {
    try {
        return window.localStorage.getItem('theme');
    } catch (error) {
        return null;
    }
};

const followsSystemPreference = (storedTheme = getStoredTheme()) => {
    return storedTheme !== 'dark' && storedTheme !== 'light';
};

const fallbackApplySystemPreference = (matches = getSystemPrefersDark(), force = false) => {
    const storedTheme = getStoredTheme();

    if (!force && !followsSystemPreference(storedTheme)) {
        return document.documentElement.classList.contains('dark');
    }

    return applyDark(Boolean(matches));
};

const fallbackApplyStoredTheme = (theme = getStoredTheme()) => {
    if (theme === 'dark') {
        return applyDark(true);
    }

    if (theme === 'light') {
        return applyDark(false);
    }

    return fallbackApplySystemPreference(undefined, true);
};

const applySystemPreference =
    typeof window.__omxfcApplySystemTheme === 'function'
        ? window.__omxfcApplySystemTheme
        : fallbackApplySystemPreference;

const applyStoredTheme =
    typeof window.__omxfcApplyStoredTheme === 'function'
        ? window.__omxfcApplyStoredTheme
        : fallbackApplyStoredTheme;

window.__omxfcApplySystemTheme = applySystemPreference;
window.__omxfcApplyStoredTheme = applyStoredTheme;

prefersDark.addEventListener('change', (event) => {
    applySystemPreference(event.matches);
});

window.addEventListener('storage', (event) => {
    if (event.key !== 'theme') {
        return;
    }

    applyStoredTheme(event.newValue ?? undefined);
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
