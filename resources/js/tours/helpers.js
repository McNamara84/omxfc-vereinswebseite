// Keep these aligned with the member sidebar (`lg`) and public navbar (`xl`).
export const TOUR_DESKTOP_BREAKPOINT = 1024;
export const TOUR_PUBLIC_DESKTOP_BREAKPOINT = 1280;

export function detectTourDevice(width = window.innerWidth, root = document) {
    const hasMemberSidebar = root?.querySelector?.('[data-testid="member-sidebar-navigation"]');
    const breakpoint = hasMemberSidebar ? TOUR_DESKTOP_BREAKPOINT : TOUR_PUBLIC_DESKTOP_BREAKPOINT;

    return width >= breakpoint ? 'desktop' : 'mobile';
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

    for (let current = element; current; current = current.parentElement) {
        if (current instanceof HTMLDetailsElement && !current.open) {
            const summary = current.querySelector('summary');

            if (!(summary instanceof HTMLElement) || !summary.contains(element)) {
                return false;
            }
        }

        const style = window.getComputedStyle(current);

        if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
            return false;
        }
    }

    return true;
}

export function visibleElementForSelector(selector, root = document) {
    if (typeof selector !== 'string' || selector === '' || typeof root?.querySelectorAll !== 'function') {
        return null;
    }

    return Array.from(root.querySelectorAll(selector)).find((element) => isElementVisible(element)) ?? null;
}
