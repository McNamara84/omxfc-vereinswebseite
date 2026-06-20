import { expect, test as base } from '@playwright/test';
import { waitForUrl, withNavigationDefaults } from './utils/navigation.js';

const defaultStableActionFallbackTimeout = 1500;
const parseTimeout = (value, fallback = defaultStableActionFallbackTimeout) => {
    const parsedValue = Number(value);

    return Number.isFinite(parsedValue) ? parsedValue : fallback;
};
const stableActionFallbackTimeout = parseTimeout(
    process.env.PLAYWRIGHT_STABLE_ACTION_FALLBACK_TIMEOUT
        ?? process.env.PLAYWRIGHT_STABLE_CLICK_FALLBACK_TIMEOUT,
);
const locatorStableActionPatchSymbol = Symbol.for('omxfc.playwright.locatorStableActionFallback');
const retryableNavigationErrors = [
    'net::ERR_EMPTY_RESPONSE',
    'net::ERR_CONNECTION_RESET',
    'net::ERR_CONNECTION_REFUSED',
];

const disableMotionForPlaywright = () => {
    if (!window.__playwrightRafFallbackInstalled) {
        let frameId = 0;
        const frameTimers = new Map();

        // Docker Chromium can stop producing animation frames on Windows hosts;
        // Alpine x-show and Playwright's stability checks both rely on rAF.
        Object.defineProperty(window, '__playwrightRafFallbackInstalled', { value: true });
        window.requestAnimationFrame = (callback) => {
            frameId += 1;
            const currentFrameId = frameId;
            const timer = window.setTimeout(() => {
                frameTimers.delete(currentFrameId);
                callback(window.performance.now());
            }, 16);

            frameTimers.set(currentFrameId, timer);
            return currentFrameId;
        };
        window.cancelAnimationFrame = (currentFrameId) => {
            const timer = frameTimers.get(currentFrameId);

            if (timer === undefined) {
                return;
            }

            window.clearTimeout(timer);
            frameTimers.delete(currentFrameId);
        };
    }

    const styleId = 'playwright-disable-motion';
    const content = `
        *, *::before, *::after {
            animation-delay: 0s !important;
            animation-duration: 0.001ms !important;
            animation-iteration-count: 1 !important;
            scroll-behavior: auto !important;
            transition-delay: 0s !important;
            transition-duration: 0.001ms !important;
        }

        ::view-transition-old(root),
        ::view-transition-new(root) {
            animation: none !important;
        }
    `;

    const install = () => {
        if (document.getElementById(styleId)) {
            return;
        }

        const style = document.createElement('style');
        style.id = styleId;
        style.textContent = content;
        document.head.append(style);
    };

    if (document.head) {
        install();
        return;
    }

    document.addEventListener('DOMContentLoaded', install, { once: true });
};

const sleep = (duration) => new Promise((resolve) => setTimeout(resolve, duration));

const shouldRetryNavigationError = (error) => retryableNavigationErrors
    .some((message) => String(error?.message ?? '').includes(message));

const gotoWithRetry = async (goto, url, options = {}) => {
    const navigationOptions = withNavigationDefaults(options);
    const retryDelays = [0, 250, 750];

    for (let attempt = 0; attempt < retryDelays.length; attempt += 1) {
        if (retryDelays[attempt] > 0) {
            await sleep(retryDelays[attempt]);
        }

        try {
            return await goto(url, navigationOptions);
        } catch (error) {
            const isLastAttempt = attempt === retryDelays.length - 1;

            if (isLastAttempt || !shouldRetryNavigationError(error)) {
                throw error;
            }
        }
    }

    return null;
};

const withStableActionFallbackTimeout = (options = {}) => {
    const timeout = Number.isFinite(options.timeout)
        ? Math.min(options.timeout, stableActionFallbackTimeout)
        : stableActionFallbackTimeout;

    return { ...options, timeout };
};

const scrollIntoView = async (locator) => locator.evaluate((element) => {
    element.scrollIntoView({ block: 'center', inline: 'center' });
});

const receivesPointerAtCenter = async (locator) => locator.evaluate((element) => {
    const rect = element.getBoundingClientRect();

    if (rect.width <= 0 || rect.height <= 0) {
        return false;
    }

    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height / 2;
    const topElement = document.elementFromPoint(centerX, centerY);

    return topElement === element || element.contains(topElement);
});

const runWithStableActionFallback = async (locator, action, options = {}, { requirePointer = true } = {}) => {
    if (options.force || options.trial) {
        return action(options);
    }

    try {
        await action({ ...withStableActionFallbackTimeout(options), trial: true });
        return action({ ...options, force: true });
    } catch {
        const isVisible = await locator.waitFor({ state: 'visible', timeout: stableActionFallbackTimeout })
            .then(() => true)
            .catch(() => false);
        const isEnabled = await locator.isEnabled({ timeout: stableActionFallbackTimeout }).catch(() => false);
        await scrollIntoView(locator).catch(() => {});
        const receivesPointer = requirePointer
            ? await receivesPointerAtCenter(locator).catch(() => false)
            : true;

        if (isVisible && isEnabled && receivesPointer) {
            return action({ ...options, force: true });
        }

        return action(options);
    }
};

const patchLocatorStableActionFallback = (locator) => {
    const prototype = Object.getPrototypeOf(locator);

    if (!prototype || prototype[locatorStableActionPatchSymbol]) {
        return;
    }

    const originalClick = prototype.click;
    const originalCheck = prototype.check;
    const originalUncheck = prototype.uncheck;

    if (![originalClick, originalCheck, originalUncheck].some((method) => typeof method === 'function')) {
        return;
    }

    Object.defineProperty(prototype, locatorStableActionPatchSymbol, { value: true });

    // Chromium in the Docker harness can stop satisfying Playwright's frame-based
    // stability check even for static controls. Keep the fallback narrow.
    if (typeof originalClick === 'function') {
        prototype.click = function click(options = {}) {
            return runWithStableActionFallback(
                this,
                (actionOptions) => originalClick.call(this, actionOptions),
                options,
                { requirePointer: true },
            );
        };
    }

    if (typeof originalCheck === 'function') {
        prototype.check = function check(options = {}) {
            return runWithStableActionFallback(
                this,
                (actionOptions) => originalCheck.call(this, actionOptions),
                options,
                { requirePointer: false },
            );
        };
    }

    if (typeof originalUncheck === 'function') {
        prototype.uncheck = function uncheck(options = {}) {
            return runWithStableActionFallback(
                this,
                (actionOptions) => originalUncheck.call(this, actionOptions),
                options,
                { requirePointer: false },
            );
        };
    }
};

export const test = base.extend({
    page: async ({ page }, use) => {
        const goto = page.goto.bind(page);
        const goBack = page.goBack.bind(page);
        const goForward = page.goForward.bind(page);
        const reload = page.reload.bind(page);
        const waitForNavigation = page.waitForNavigation.bind(page);

        page.goto = (url, options = {}) => gotoWithRetry(goto, url, options);
        page.goBack = (options = {}) => goBack(withNavigationDefaults(options));
        page.goForward = (options = {}) => goForward(withNavigationDefaults(options));
        page.reload = (options = {}) => reload(withNavigationDefaults(options));
        page.waitForNavigation = (options = {}) => waitForNavigation(withNavigationDefaults(options));
        page.waitForURL = (url, options = {}) => waitForUrl(page, url, options);

        await page.addInitScript(disableMotionForPlaywright);
        patchLocatorStableActionFallback(page.locator('body'));

        await use(page);
    },
});

export { expect };