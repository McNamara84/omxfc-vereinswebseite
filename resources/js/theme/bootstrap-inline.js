(() => {
    const root = document.documentElement;
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');

    const applyTheme = (isDark) => {
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

    const applySystemTheme = (matches = prefersDark.matches, force = false) => {
        const storedTheme = getStoredTheme();

        if (!force && !followsSystemPreference(storedTheme)) {
            return root.classList.contains('dark');
        }

        return applyTheme(Boolean(matches));
    };

    const applyStoredTheme = (theme = getStoredTheme()) => {
        if (theme === 'dark') {
            return applyTheme(true);
        }

        if (theme === 'light') {
            return applyTheme(false);
        }

        return applySystemTheme(prefersDark.matches, true);
    };

    window.__omxfcPrefersDark = prefersDark;
    window.__omxfcApplySystemTheme = applySystemTheme;
    window.__omxfcApplyStoredTheme = applyStoredTheme;

    const storedTheme = getStoredTheme();

    if (storedTheme === 'dark' || storedTheme === 'light') {
        applyTheme(storedTheme === 'dark');
        return;
    }

    applyTheme(prefersDark.matches);
})();
