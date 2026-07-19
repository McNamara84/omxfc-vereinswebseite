const supportedLoadStates = new Set(['domcontentloaded', 'load', 'networkidle']);

export const matchesUrl = (currentUrl, expectedUrl) => {
    if (typeof expectedUrl === 'function') {
        return expectedUrl(new URL(currentUrl));
    }

    if (expectedUrl instanceof RegExp) {
        return expectedUrl.test(currentUrl);
    }

    return currentUrl === String(expectedUrl);
};

export const withNavigationDefaults = (options = {}) => ({ waitUntil: 'domcontentloaded', ...options });

export const waitForMatchingUrl = async (page, expectedUrl, timeout) => {
    // Keep the pure URL helpers lightweight for unit tests and non-Playwright consumers.
    const { expect } = await import('@playwright/test');
    await expect
        .poll(() => matchesUrl(page.url(), expectedUrl), {
            timeout,
            intervals: [100, 250, 500, 1000],
        })
        .toBe(true);
};

export const waitForUrl = async (page, expectedUrl, options = {}, dependencies = {}) => {
    const navigationOptions = withNavigationDefaults(options);
    const timeout = navigationOptions.timeout ?? 30000;
    const waitForMatch = dependencies.waitForMatch ?? waitForMatchingUrl;
    const now = dependencies.now ?? Date.now;
    const startedAt = now();

    await waitForMatch(page, expectedUrl, timeout);

    if (! supportedLoadStates.has(navigationOptions.waitUntil)) {
        return;
    }

    const elapsed = Math.max(0, now() - startedAt);
    const remainingTimeout = Math.max(timeout - elapsed, 1);

    await page.waitForLoadState(navigationOptions.waitUntil, { timeout: remainingTimeout });
};