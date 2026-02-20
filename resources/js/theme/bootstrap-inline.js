(() => {
    const root = document.documentElement;
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');

    const DARK_THEME = 'coffee';
    const LIGHT_THEME = 'caramellatte';

    const applyTheme = (isDark) => {
        const nextIsDark = Boolean(isDark);
        root.classList.toggle('dark', nextIsDark);
        root.dataset.theme = nextIsDark ? DARK_THEME : LIGHT_THEME;
        return nextIsDark;
    };

    const getStoredTheme = () => {
        try {
            const raw = window.localStorage.getItem('_x_mary-theme');
            return raw ? JSON.parse(raw) : null;
        } catch {
            return null;
        }
    };

    const applyStoredOrSystemTheme = () => {
        const storedTheme = getStoredTheme();

        if (storedTheme === DARK_THEME) {
            return applyTheme(true);
        }

        if (storedTheme === LIGHT_THEME) {
            return applyTheme(false);
        }

        // Kein gespeichertes Theme → Systempräferenz verwenden
        return applyTheme(prefersDark.matches);
    };

    // Einmalige Migration vom alten 'theme'-Key
    try {
        const oldTheme = window.localStorage.getItem('theme');
        if (oldTheme === 'dark' || oldTheme === 'light') {
            const newTheme = oldTheme === 'dark' ? DARK_THEME : LIGHT_THEME;
            const newClass = oldTheme === 'dark' ? 'dark' : '';
            window.localStorage.setItem('_x_mary-theme', JSON.stringify(newTheme));
            window.localStorage.setItem('_x_mary-class', JSON.stringify(newClass));
            window.localStorage.removeItem('theme');
        }
    } catch {}

    window.__omxfcPrefersDark = prefersDark;
    window.__omxfcApplyStoredTheme = applyStoredOrSystemTheme;

    applyStoredOrSystemTheme();
})();
