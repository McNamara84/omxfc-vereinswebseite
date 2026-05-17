export const TOUR_DESKTOP_BREAKPOINT = 1280;

export function detectTourDevice(width = window.innerWidth) {
    return width >= TOUR_DESKTOP_BREAKPOINT ? 'desktop' : 'mobile';
}

export function selectorForStep(step, device) {
    return step?.selectors?.[device] ?? null;
}

export function revealSelectorsForStep(step, device) {
    const selectors = step?.reveal?.[device] ?? [];

    return Array.isArray(selectors)
        ? selectors.filter((selector) => typeof selector === 'string' && selector !== '')
        : [];
}

export function resolveCurrentStepIndex(steps, currentStepKey) {
    if (!Array.isArray(steps) || steps.length === 0) {
        return 0;
    }

    const index = steps.findIndex((step) => step?.key === currentStepKey);

    return index === -1 ? 0 : index;
}

export function filterReachableSteps(steps, device, root = document) {
    if (!Array.isArray(steps)) {
        return [];
    }

    return steps.filter((step) => {
        const selector = selectorForStep(step, device);

        return typeof selector === 'string' && selector !== '' && root.querySelector(selector);
    });
}

export function isElementVisible(element) {
    if (!(element instanceof Element)) {
        return false;
    }

    const style = window.getComputedStyle(element);

    return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0';
}