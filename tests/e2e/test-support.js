import { expect, test as base } from '@playwright/test';
import { waitForUrl, withNavigationDefaults } from './utils/navigation.js';

const defaultStableActionFallbackTimeout = 1500;
const defaultNavigationRetryAttemptTimeout = 8000;
const parseTimeout = (value, fallback = defaultStableActionFallbackTimeout) => {
    const parsedValue = Number(value);

    return Number.isFinite(parsedValue) && parsedValue > 0 ? parsedValue : fallback;
};
const stableActionFallbackTimeout = parseTimeout(
    process.env.PLAYWRIGHT_STABLE_ACTION_FALLBACK_TIMEOUT
        ?? process.env.PLAYWRIGHT_STABLE_CLICK_FALLBACK_TIMEOUT,
);
const navigationRetryAttemptTimeout = parseTimeout(
    process.env.PLAYWRIGHT_GOTO_RETRY_ATTEMPT_TIMEOUT,
    defaultNavigationRetryAttemptTimeout,
);
const locatorStableActionPatchSymbol = Symbol.for('omxfc.playwright.locatorStableActionFallback');
const retryableNavigationErrors = [
    'net::ERR_EMPTY_RESPONSE',
    'net::ERR_CONNECTION_RESET',
    'net::ERR_CONNECTION_REFUSED',
    'Timeout',
];

const disableMotionForPlaywright = () => {
    const installRafFallback = () => {
        if (window.__playwrightRafFallbackInstalled) {
            return;
        }

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
    };

    installRafFallback();

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
    const attemptOptions = navigationOptions.timeout === undefined
        ? { ...navigationOptions, timeout: navigationRetryAttemptTimeout }
        : navigationOptions;

    for (let attempt = 0; attempt < retryDelays.length; attempt += 1) {
        if (retryDelays[attempt] > 0) {
            await sleep(retryDelays[attempt]);
        }

        try {
            return await goto(url, attemptOptions);
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

const normalizeSelectTargets = (values) => {
    const targets = Array.isArray(values) ? values : [values];

    return targets.map((target) => {
        if (typeof target === 'string') {
            return { value: target };
        }

        if (typeof target === 'number') {
            return { index: target };
        }

        if (target && typeof target === 'object') {
            return {
                value: target.value,
                label: target.label,
                index: target.index,
            };
        }

        return {};
    });
};

const setSelectOptionsViaDom = async (locator, values) => {
    await locator.waitFor({ state: 'visible', timeout: stableActionFallbackTimeout });

    const elementHandle = await locator.elementHandle({ timeout: stableActionFallbackTimeout });

    if (!elementHandle) {
        throw new Error('locator.selectOption fallback failed: select element was not found');
    }

    let result;

    try {
        result = await elementHandle.evaluate((element, targets) => {
            if (!(element instanceof HTMLSelectElement)) {
                return { ok: false, reason: 'target is not a select element' };
            }

            if (element.disabled || element.matches(':disabled') || element.closest('[aria-disabled="true"]')) {
                return { ok: false, reason: 'select element is disabled' };
            }

            if (targets.length > 1 && !element.multiple) {
                return { ok: false, reason: 'multiple values require a multiple select' };
            }

            const options = [...element.options];
            const selectedOptions = [];

            for (const target of targets) {
                const option = options.find((candidate, index) => {
                    if (target.index !== undefined && target.index !== index) {
                        return false;
                    }

                    if (target.value !== undefined && target.value !== candidate.value) {
                        return false;
                    }

                    if (target.label !== undefined && target.label !== candidate.label) {
                        return false;
                    }

                    return target.index !== undefined || target.value !== undefined || target.label !== undefined;
                }) ?? null;

                if (!option) {
                    return { ok: false, reason: 'select option was not found' };
                }

                if (option.disabled || option.closest('optgroup[disabled]')) {
                    return { ok: false, reason: 'select option is disabled' };
                }

                selectedOptions.push(option);
            }

            for (const option of options) {
                option.selected = false;
            }

            for (const option of selectedOptions) {
                option.selected = true;
            }

            const selectedValues = selectedOptions.map((option) => option.value);
            const model = element.getAttribute('x-model') || element.getAttribute('x-model.number');
            const root = element.closest('[x-data]');
            const state = root && window.Alpine?.$data ? window.Alpine.$data(root) : null;

            if (typeof element._x_model?.set === 'function') {
                element._x_model.set(element.multiple ? selectedValues : element.value);
            } else if (model && state) {
                const path = model.split('.').filter(Boolean);
                let target = state;

                for (const segment of path.slice(0, -1)) {
                    target = target?.[segment];
                }

                if (target && path.length > 0) {
                    target[path.at(-1)] = element.multiple ? selectedValues : element.value;
                }
            }

            element.dispatchEvent(new Event('input', { bubbles: true, composed: true }));
            element.dispatchEvent(new Event('change', { bubbles: true }));

            return { ok: true, values: selectedValues };
        }, normalizeSelectTargets(values));
    } finally {
        await elementHandle.dispose();
    }

    if (!result.ok) {
        throw new Error(`locator.selectOption fallback failed: ${result.reason}`);
    }

    return result.values;
};

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

const runSelectOptionWithStableActionFallback = async (locator, action, values, options = {}) => {
    if (options.force) {
        return action(values, options);
    }

    return setSelectOptionsViaDom(locator, values);
};

const patchLocatorStableActionFallback = (locator) => {
    const prototype = Object.getPrototypeOf(locator);

    if (!prototype || prototype[locatorStableActionPatchSymbol]) {
        return;
    }

    const originalClick = prototype.click;
    const originalCheck = prototype.check;
    const originalUncheck = prototype.uncheck;
    const originalSelectOption = prototype.selectOption;

    if (![originalClick, originalCheck, originalUncheck, originalSelectOption].some((method) => typeof method === 'function')) {
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

    if (typeof originalSelectOption === 'function') {
        prototype.selectOption = function selectOption(values, options = {}) {
            return runSelectOptionWithStableActionFallback(
                this,
                (actionValues, actionOptions) => originalSelectOption.call(this, actionValues, actionOptions),
                values,
                options,
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