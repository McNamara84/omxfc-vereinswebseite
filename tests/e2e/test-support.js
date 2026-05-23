import { expect, test as base } from '@playwright/test';
import { waitForUrl, withNavigationDefaults } from './utils/navigation.js';

export const test = base.extend({
    page: async ({ page }, use) => {
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
        page.waitForURL = (url, options = {}) => waitForUrl(page, url, options);

        await use(page);
    },
});

export { expect };