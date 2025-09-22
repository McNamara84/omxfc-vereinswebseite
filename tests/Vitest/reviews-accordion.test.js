import { beforeEach, describe, expect, it } from 'vitest';
import { applyReviewsAccordionState, setupReviewsAccordion } from '../../resources/js/reviews/accordion.js';

describe('Reviews accordion', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <div data-reviews-accordion>
                <button data-reviews-accordion-button aria-controls="panel-1" aria-expanded="false">
                    <span>Erster Zyklus</span>
                    <span data-reviews-accordion-icon>+</span>
                </button>
                <div id="panel-1" data-reviews-accordion-panel class="hidden" hidden aria-hidden="true"></div>
            </div>
        `;
    });

    it('updates aria attributes and icon when applying accordion state', () => {
        const button = document.querySelector('[data-reviews-accordion-button]');
        const panel = document.querySelector('[data-reviews-accordion-panel]');
        const icon = document.querySelector('[data-reviews-accordion-icon]');

        applyReviewsAccordionState(button, panel, icon, true);

        expect(button.getAttribute('aria-expanded')).toBe('true');
        expect(panel.classList.contains('hidden')).toBe(false);
        expect(panel.hasAttribute('hidden')).toBe(false);
        expect(panel.getAttribute('aria-hidden')).toBe('false');
        expect(icon.textContent).toBe('−');

        applyReviewsAccordionState(button, panel, icon, false);

        expect(button.getAttribute('aria-expanded')).toBe('false');
        expect(panel.classList.contains('hidden')).toBe(true);
        expect(panel.getAttribute('aria-hidden')).toBe('true');
        expect(panel.hasAttribute('hidden')).toBe(true);
        expect(icon.textContent).toBe('+');
    });

    it('registers click handlers that toggle the accordion', () => {
        setupReviewsAccordion();

        const button = document.querySelector('[data-reviews-accordion-button]');
        const panel = document.querySelector('[data-reviews-accordion-panel]');

        expect(button?.getAttribute('aria-expanded')).toBe('false');
        expect(panel?.classList.contains('hidden')).toBe(true);

        button?.click();

        expect(button?.getAttribute('aria-expanded')).toBe('true');
        expect(panel?.classList.contains('hidden')).toBe(false);

        button?.click();

        expect(button?.getAttribute('aria-expanded')).toBe('false');
        expect(panel?.classList.contains('hidden')).toBe(true);
        expect(
            document.querySelector('[data-reviews-accordion]')?.dataset.reviewsAccordionReady
        ).toBe('true');
    });

    it('allows setup to run multiple times without duplicating handlers', () => {
        setupReviewsAccordion();

        const button = document.querySelector('[data-reviews-accordion-button]');

        button?.click();
        expect(button?.getAttribute('aria-expanded')).toBe('true');

        setupReviewsAccordion();

        button?.click();
        expect(button?.getAttribute('aria-expanded')).toBe('false');

        button?.click();
        expect(button?.getAttribute('aria-expanded')).toBe('true');
    });
});
