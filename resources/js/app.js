import './bootstrap';

// Alpine.js initialisieren
// Livewire 4 bündelt Alpine und setzt window.Alpine synchron bevor Module-Scripts laufen.
// Auf Livewire-Seiten dürfen wir Alpine NICHT erneut starten — nur Plugins registrieren.
// Auf Nicht-Livewire-Seiten (z.B. Kassenbuch mit reinem Blade + Alpine)
// müssen wir Alpine selbst laden und starten.
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';

if (window.Alpine) {
    // Livewire hat Alpine bereits geladen — nur Plugins registrieren, nicht überschreiben.
    window.Alpine.plugin(focus);
} else {
    // Kein Livewire auf dieser Seite — Alpine selbst initialisieren.
    window.Alpine = Alpine;
    Alpine.plugin(focus);
    Alpine.start();
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
