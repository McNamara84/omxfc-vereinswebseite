import { expect, test as base } from '@playwright/test';

const matchesUrl = (currentUrl, expectedUrl) => {
    if (typeof expectedUrl === 'function') {
        return expectedUrl(new URL(currentUrl));
    }

    if (expectedUrl instanceof RegExp) {
        return expectedUrl.test(currentUrl);
    }

    return currentUrl === String(expectedUrl);
};

const waitForMatchingUrl = async (page, expectedUrl, timeout) => {
    await expect
        .poll(() => matchesUrl(page.url(), expectedUrl), {
            timeout,
            intervals: [100, 250, 500, 1000],
        })
        .toBe(true);
};

export const test = base.extend({
    page: async ({ page }, use) => {
        const withNavigationDefaults = (options = {}) => ({ waitUntil: 'domcontentloaded', ...options });
        const goto = page.goto.bind(page);
        const goBack = page.goBack.bind(page);
        const goForward = page.goForward.bind(page);
        const reload = page.reload.bind(page);
        const waitForNavigation = page.waitForNavigation.bind(page);

        page.goto = (url, options = {}) => goto(url, withNavigationDefaults(options));
        page.goBack = (options = {}) => goBack(withNavigationDefaults(options));
        page.goForward = (options = {}) => goForward(withNavigationDefaults(options));
        page.reload = (options = {}) => reload(withNavigationDefaults(options));
        page.waitForNavigation = (options = {}) => waitForNavigation(withNavigationDefaults(options));
        page.waitForURL = async (url, options = {}) => {
            const navigationOptions = withNavigationDefaults(options);
            const timeout = navigationOptions.timeout ?? 30000;

            await waitForMatchingUrl(page, url, timeout);
        };

        await use(page);
    },
});

export { expect };