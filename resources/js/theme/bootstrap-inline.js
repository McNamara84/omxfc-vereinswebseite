(() => {
    const root = document.documentElement;
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');

    const DARK_THEME = 'coffee';
    const LIGHT_THEME = 'caramellatte';
    const EXPLICIT_THEME_KEY = 'omxfc-theme-explicit';

    const getSystemPrefersDark = () => window.matchMedia('(prefers-color-scheme: dark)').matches;

    const applyTheme = (isDark) => {
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

    const hasExplicitTheme = () => {
        try {
            return window.localStorage.getItem(EXPLICIT_THEME_KEY) === '1';
        } catch {
            return false;
        }
    };

    const applyStoredOrSystemTheme = () => {
        const storedTheme = getStoredTheme();

        if (hasExplicitTheme() && storedTheme === DARK_THEME) {
            return applyTheme(true);
        }

        if (hasExplicitTheme() && storedTheme === LIGHT_THEME) {
            return applyTheme(false);
        }

        // Kein gespeichertes Theme → Systempräferenz verwenden
        return applyTheme(getSystemPrefersDark());
    };

    // Einmalige Migration vom alten 'theme'-Key
    try {
        const oldTheme = window.localStorage.getItem('theme');
        if (oldTheme === 'dark' || oldTheme === 'light') {
            const newTheme = oldTheme === 'dark' ? DARK_THEME : LIGHT_THEME;
            const newClass = oldTheme === 'dark' ? 'dark' : '';
            window.localStorage.setItem('mary-theme', JSON.stringify(newTheme));
            window.localStorage.setItem('mary-class', JSON.stringify(newClass));
            window.localStorage.setItem(EXPLICIT_THEME_KEY, '1');
            window.localStorage.removeItem('theme');
        }
    } catch {}

    // Existing maryUI values predate the provenance marker and came from a
    // deliberate choice. System defaults retain marker 0 so media-query
    // changes continue to be respected.
    try {
        const storedTheme = getStoredTheme();
        const marker = window.localStorage.getItem(EXPLICIT_THEME_KEY);

        if (marker === null && (storedTheme === DARK_THEME || storedTheme === LIGHT_THEME)) {
            window.localStorage.setItem(EXPLICIT_THEME_KEY, '1');
        }

        // maryUI's inline controller reads both values directly. Keep
        // automatically managed values aligned with the current system theme
        // on every page load so its persisted state cannot restore a stale
        // preference after the bootstrap has run.
        if (marker === '0' || (storedTheme !== DARK_THEME && storedTheme !== LIGHT_THEME)) {
            const systemIsDark = getSystemPrefersDark();
            window.localStorage.setItem('mary-theme', JSON.stringify(systemIsDark ? DARK_THEME : LIGHT_THEME));
            window.localStorage.setItem('mary-class', JSON.stringify(systemIsDark ? 'dark' : ''));
            window.localStorage.setItem(EXPLICIT_THEME_KEY, '0');
        }
    } catch {}

    window.__omxfcPrefersDark = prefersDark;
    window.__omxfcApplyStoredTheme = applyStoredOrSystemTheme;

    applyStoredOrSystemTheme();
})();
