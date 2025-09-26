import './bootstrap';

window.omxfc = window.omxfc || {};

const initQueue = Array.isArray(window.omxfc.__initQueue)
    ? window.omxfc.__initQueue
    : [];

const flushInitQueue = () => {
    while (initQueue.length > 0) {
        const callback = initQueue.shift();

        if (typeof callback !== 'function') {
            continue;
        }

        try {
            callback();
        } catch (error) {
            window.console?.error?.('OMXFC init callback failed', error);
        }
    }
};

if (typeof window.omxfc.queueInit !== 'function') {
    window.omxfc.queueInit = (callback) => {
        if (typeof callback !== 'function') {
            return;
        }

        if (window.omxfc.__appReady) {
            callback();
            return;
        }

        initQueue.push(callback);
    };
}

window.omxfc.__initQueue = initQueue;

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
import initMitgliedschaftForm from './mitgliedschaft/form';

window.omxfc.initMitgliedschaftForm = (root = document, options = {}) =>
    initMitgliedschaftForm(root, options);

window.omxfc.__appReady = true;
flushInitQueue();
