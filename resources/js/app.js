import './bootstrap';
import omxfcLogoUrl from '../images/omxfc-logo.png';

// Blade-only Logo fuer Vite::asset im Manifest halten.
void omxfcLogoUrl;

// Alpine.js initialisieren (Logik in alpine-init.js für Testbarkeit extrahiert)
import Alpine from 'alpinejs';
import anchor from '@alpinejs/anchor';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';
import persist from '@alpinejs/persist';
import './alpine/char-editor';
import { scheduleInitAlpine } from './alpine-init';

scheduleInitAlpine(Alpine, [anchor, focus, persist, collapse]);

const DARK_THEME = 'coffee';
const LIGHT_THEME = 'caramellatte';

const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');
window.__omxfcPrefersDark = prefersDark;

const getSystemPrefersDark = () => window.matchMedia('(prefers-color-scheme: dark)').matches;

const resolveSystemPreference = (event) =>
    typeof event?.matches === 'boolean' ? event.matches : getSystemPrefersDark();

const currentThemeIsDark = () => {
    const root = document.documentElement;

    return root.dataset.theme === DARK_THEME || root.classList.contains('dark');
};

const syncThemeToggleState = () => {
    const pressed = currentThemeIsDark() ? 'true' : 'false';

    document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
        toggle.setAttribute('aria-pressed', pressed);
    });
};

const applyDark = (isDark) => {
    const root = document.documentElement;
    const nextIsDark = Boolean(isDark);

    root.classList.toggle('dark', nextIsDark);
    root.dataset.theme = nextIsDark ? DARK_THEME : LIGHT_THEME;
    syncThemeToggleState();

    return nextIsDark;
};

const applyAndStoreTheme = (isDark) => {
    const nextIsDark = applyDark(isDark);
    const theme = nextIsDark ? DARK_THEME : LIGHT_THEME;
    const themeClass = nextIsDark ? 'dark' : '';

    try {
        window.localStorage.setItem('mary-theme', JSON.stringify(theme));
        window.localStorage.setItem('mary-class', JSON.stringify(themeClass));
    } catch {}

    window.dispatchEvent(new CustomEvent('theme-changed', { detail: theme }));
    window.dispatchEvent(new CustomEvent('theme-changed-class', { detail: themeClass }));

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

    return applyDark(getSystemPrefersDark());
};

window.__omxfcApplyStoredTheme = applyStoredOrSystemTheme;

const applySystemPreferenceChange = (event) => {
    // Nur reagieren wenn kein explizites Theme gespeichert ist
    const storedTheme = getStoredTheme();
    if (!storedTheme || (storedTheme !== DARK_THEME && storedTheme !== LIGHT_THEME)) {
        applyDark(resolveSystemPreference(event));
    }
};

if (typeof prefersDark.addEventListener === 'function') {
    prefersDark.addEventListener('change', applySystemPreferenceChange);
} else if (typeof prefersDark.addListener === 'function') {
    prefersDark.addListener(applySystemPreferenceChange);
}

window.addEventListener('storage', (event) => {
    if (! ['mary-theme', 'mary-class', null].includes(event.key)) {
        return;
    }

    applyStoredOrSystemTheme();
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', syncThemeToggleState, { once: true });
} else {
    syncThemeToggleState();
}

document.addEventListener('livewire:navigated', syncThemeToggleState);

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    if (! event.target.closest('[data-theme-toggle]')) {
        return;
    }

    event.preventDefault();
    applyAndStoreTheme(document.documentElement.dataset.theme !== DARK_THEME);
});

// Leaflet importieren
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Leaflet global verfügbar machen
window.L = L;

import './alpine/hoerbuch-role-repeater';
import { registerMarkdownEditorLifecycle } from './reviews/markdown-editor';
import './todos';
import './dashboard';
import './mitglieder/accessibility';
import './mitglieder/map-utils';
import './romantausch-dropzone';

import './polls/charts';
import './tours/runner';

// Wire-navigate-kompatible Module (Dual-Event-Listener)
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

registerMarkdownEditorLifecycle();

// Toast-Bridge: Livewire dispatch('toast') → maryUI window.toast()
// Livewire-Komponenten nutzen $this->dispatch('toast', type: '...', title: '...'),
// maryUI <x-toast /> hört aber auf 'mary-toast' Window-Events via window.toast().
document.addEventListener('livewire:init', () => {
    const cssMap = { success: 'alert-success', error: 'alert-error', warning: 'alert-warning', info: 'alert-info' };

    Livewire.on('toast', (params) => {
        const data = Array.isArray(params) ? params[0] : params;
        const type = data.type || 'info';

        if (typeof window.toast === 'function') {
            window.toast({
                toast: {
                    title: data.title || '',
                    description: data.description || '',
                    css: cssMap[type] || 'alert-info',
                    timeout: data.timeout || 3000,
                    position: data.position || 'toast-top toast-end',
                    noProgress: false,
                },
            });
        }
    });
});
