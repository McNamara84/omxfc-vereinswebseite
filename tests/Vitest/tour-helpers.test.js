import {
    TOUR_DESKTOP_BREAKPOINT,
    detectTourDevice,
    filterReachableSteps,
    revealSelectorsForStep,
    resolveCurrentStepIndex,
    selectorForStep,
} from '@/tours/helpers';

describe('tour helpers', () => {
    const steps = [
        {
            key: 'dashboard',
            selectors: {
                desktop: '[data-tour-key="dashboard"]',
                mobile: '[data-tour-key="dashboard-mobile"]',
            },
            reveal: {
                mobile: ['[data-tour-key="mobile-menu-toggle"]'],
            },
        },
        {
            key: 'profile-settings',
            selectors: {
                desktop: '[data-tour-key="profile-settings"]',
            },
        },
    ];

    beforeEach(() => {
        document.body.innerHTML = '';
    });

    it('erkennt den Desktop-Breakpoint wie die Navigation', () => {
        expect(detectTourDevice(TOUR_DESKTOP_BREAKPOINT)).toBe('desktop');
        expect(detectTourDevice(TOUR_DESKTOP_BREAKPOINT - 1)).toBe('mobile');
    });

    it('löst Geräte-spezifische Selektoren auf', () => {
        expect(selectorForStep(steps[0], 'desktop')).toBe('[data-tour-key="dashboard"]');
        expect(selectorForStep(steps[0], 'mobile')).toBe('[data-tour-key="dashboard-mobile"]');
        expect(selectorForStep(steps[1], 'mobile')).toBeNull();
    });

    it('liefert Reveal-Selektoren nur für das passende Gerät', () => {
        expect(revealSelectorsForStep(steps[0], 'mobile')).toEqual(['[data-tour-key="mobile-menu-toggle"]']);
        expect(revealSelectorsForStep(steps[0], 'desktop')).toEqual([]);
    });

    it('bestimmt den aktuellen Schrittindex aus dem step key', () => {
        expect(resolveCurrentStepIndex(steps, 'profile-settings')).toBe(1);
        expect(resolveCurrentStepIndex(steps, 'unbekannt')).toBe(0);
    });

    it('filtert nur Schritte, deren Ziel im DOM vorhanden ist', () => {
        document.body.innerHTML = `
            <button data-tour-key="dashboard"></button>
            <button data-tour-key="profile-settings"></button>
        `;

        expect(filterReachableSteps(steps, 'desktop')).toHaveLength(2);
        expect(filterReachableSteps(steps, 'mobile')).toHaveLength(0);
    });
});