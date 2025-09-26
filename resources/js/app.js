import './bootstrap';

window.omxfc = window.omxfc || {};

const ensureMobileToggleRef = (root = document) => {
    if (!root || typeof root.querySelector !== 'function') {
        return null;
    }

    const toggle = root.querySelector('button[aria-controls="mobile-navigation"]');

    if (!toggle) {
        return null;
    }

    const applyRef = () => {
        if (toggle.getAttribute('x-ref') !== 'mobileToggle') {
            toggle.setAttribute('x-ref', 'mobileToggle');
        }

        const expanded = toggle.getAttribute('aria-expanded');
        if (expanded !== 'true' && expanded !== 'false') {
            toggle.setAttribute('aria-expanded', 'false');
        }

        const label = toggle.getAttribute('aria-label');
        if (!label || label.trim().length === 0) {
            toggle.setAttribute('aria-label', 'Menü öffnen');
        }

        const srOnlyElements = toggle.querySelectorAll('.sr-only');

        if (srOnlyElements.length > 0) {
            srOnlyElements.forEach((element) => {
                if (element && typeof element.remove === 'function') {
                    element.remove();
                }
            });

            if (toggle.textContent.trim().length === 0) {
                toggle.textContent = 'Menü öffnen';
            }
        }
    };

    applyRef();

    if (typeof requestAnimationFrame === 'function') {
        requestAnimationFrame(applyRef);
    } else {
        setTimeout(applyRef, 0);
    }

    if (typeof MutationObserver !== 'function') {
        return applyRef;
    }

    const watchedAttributes = new Set(['x-ref', 'aria-expanded', 'aria-label']);
    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            if (mutation.type === 'attributes' && watchedAttributes.has(mutation.attributeName)) {
                applyRef();
                break;
            }
        }
    });

    observer.observe(toggle, {
        attributes: true,
        attributeFilter: Array.from(watchedAttributes),
    });

    return () => observer.disconnect();
};

const scheduleEnsureMobileToggleRef = () => {
    const start = () => {
        try {
            const disconnect = ensureMobileToggleRef();

            if (typeof disconnect === 'function') {
                window.omxfc.__disconnectMobileToggleRefObserver = disconnect;
            }
        } catch (error) {
            window.console?.error?.('Failed to ensure mobile toggle ref', error);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
        return;
    }

    if (typeof queueMicrotask === 'function') {
        queueMicrotask(start);
        return;
    }

    start();
};

scheduleEnsureMobileToggleRef();

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

const handlePrefersDarkChange = (event) => {
    const matches =
        typeof event === 'boolean'
            ? event
            : event && typeof event.matches === 'boolean'
                ? event.matches
                : getSystemPrefersDark();

    applySystemPreference(matches);
};

if (typeof prefersDark.addEventListener === 'function') {
    prefersDark.addEventListener('change', handlePrefersDarkChange);
} else if (typeof prefersDark.addListener === 'function') {
    prefersDark.addListener(handlePrefersDarkChange);
}

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
window.omxfc.__appReady = true;
flushInitQueue();
