import {
    TOUR_DESKTOP_BREAKPOINT,
    TOUR_PUBLIC_DESKTOP_BREAKPOINT,
    detectTourDevice,
    filterReachableSteps,
    isElementVisible,
    revealSelectorsForStep,
    resolveCurrentStepIndex,
    selectorForStep,
    visibleElementForSelector,
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

    it('erkennt die unterschiedlichen Breakpoints von Member-Sidebar und Public-Navbar', () => {
        expect(TOUR_DESKTOP_BREAKPOINT).toBe(1024);
        expect(TOUR_PUBLIC_DESKTOP_BREAKPOINT).toBe(1280);
        expect(detectTourDevice(TOUR_PUBLIC_DESKTOP_BREAKPOINT)).toBe('desktop');
        expect(detectTourDevice(TOUR_PUBLIC_DESKTOP_BREAKPOINT - 1)).toBe('mobile');

        document.body.innerHTML = '<nav data-testid="member-sidebar-navigation"></nav>';

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

    it('behandelt Elemente in geschlossenen details als unsichtbar, ausser summary selbst', () => {
        document.body.innerHTML = `
            <details>
                <summary id="tour-summary">Community</summary>
                <a id="tour-hidden-link" href="/mitglieder">Mitgliederliste</a>
            </details>
        `;

        expect(isElementVisible(document.getElementById('tour-summary'))).toBe(true);
        expect(isElementVisible(document.getElementById('tour-hidden-link'))).toBe(false);
    });

    it('findet bei identischen Tour-Keys den sichtbaren Treffer statt eines versteckten Vorgängers', () => {
        document.body.innerHTML = `
            <nav style="display: none;">
                <a id="desktop-target" data-tour-key="dashboard">Desktop</a>
            </nav>
            <nav>
                <a id="mobile-target" data-tour-key="dashboard">Mobil</a>
            </nav>
        `;

        expect(isElementVisible(document.getElementById('desktop-target'))).toBe(false);
        expect(visibleElementForSelector('[data-tour-key="dashboard"]')?.id).toBe('mobile-target');
    });
});
