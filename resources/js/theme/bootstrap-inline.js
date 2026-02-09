(() => {
    const root = document.documentElement;
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');

    const LIGHT_THEME = 'caramellatte';
    const DARK_THEME  = 'coffee';

    /**
     * Read a value from localStorage.
     * Alpine's $persist wraps values in JSON quotes, so we strip them.
     */
    const getStored = (key) => {
        try {
            const raw = window.localStorage.getItem(key);
            return raw ? raw.replaceAll('"', '') : null;
        } catch (error) {
            return null;
        }
    };

    const applyTheme = (isDark) => {
        const nextIsDark = Boolean(isDark);
        root.classList.toggle('dark', nextIsDark);
        root.dataset.theme = nextIsDark ? DARK_THEME : LIGHT_THEME;
        return root.classList.contains('dark');
    };

    const followsSystemPreference = () => {
        const storedTheme = getStored('mary-theme');
        return !storedTheme;
    };

    const applySystemTheme = (matches = prefersDark.matches, force = false) => {
        if (!force && !followsSystemPreference()) {
            return root.classList.contains('dark');
        }
        return applyTheme(Boolean(matches));
    };

    const applyStoredTheme = () => {
        const storedTheme = getStored('mary-theme');
        if (storedTheme === DARK_THEME) {
            return applyTheme(true);
        }
        if (storedTheme === LIGHT_THEME) {
            return applyTheme(false);
        }
        return applySystemTheme(prefersDark.matches, true);
    };

    window.__omxfcPrefersDark = prefersDark;
    window.__omxfcApplySystemTheme = applySystemTheme;
    window.__omxfcApplyStoredTheme = applyStoredTheme;

    // Initial theme application
    const storedTheme = getStored('mary-theme');
    if (storedTheme === DARK_THEME || storedTheme === LIGHT_THEME) {
        applyTheme(storedTheme === DARK_THEME);
    } else {
        applyTheme(prefersDark.matches);
    }
})();
})();
