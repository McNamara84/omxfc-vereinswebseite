import { beforeEach, describe, expect, it } from 'vitest';
import { applyAccordionState, setupProtokolleAccordion } from '../../resources/js/protokolle/accordion.js';

describe('Protokolle accordion', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <details data-protokolle-accordion-item>
                <summary data-protokolle-accordion-button aria-controls="panel-2024" aria-expanded="false">
                    <span data-protokolle-accordion-icon>+</span>
                </summary>
                <div id="panel-2024" data-protokolle-accordion-panel class="hidden" hidden aria-hidden="true"></div>
            </details>
        `;
    });

    it('updates aria attributes when applying accordion state', () => {
        const button = document.querySelector('[data-protokolle-accordion-button]');
        const panel = document.getElementById('panel-2024');
        const container = button.closest('details');
        const icon = button.querySelector('[data-protokolle-accordion-icon]');

        applyAccordionState(button, panel, icon, true, container);

        expect(button.getAttribute('aria-expanded')).toBe('true');
        expect(panel.classList.contains('hidden')).toBe(false);
        expect(panel.hasAttribute('hidden')).toBe(false);
        expect(panel.getAttribute('aria-hidden')).toBe('false');
        expect(icon.textContent).toBe('−');

        applyAccordionState(button, panel, icon, false, container);

        expect(button.getAttribute('aria-expanded')).toBe('false');
        expect(panel.classList.contains('hidden')).toBe(true);
        expect(panel.hasAttribute('hidden')).toBe(true);
        expect(panel.getAttribute('aria-hidden')).toBe('true');
        expect(icon.textContent).toBe('+');
    });

    it('registers toggle handlers that sync the panel state', () => {
        const button = document.querySelector('[data-protokolle-accordion-button]');
        const panel = document.getElementById('panel-2024');
        const container = button.closest('details');

        setupProtokolleAccordion();

        expect(button.getAttribute('aria-expanded')).toBe('false');
        expect(panel.classList.contains('hidden')).toBe(true);
        expect(container?.open).toBe(false);

        container?.setAttribute('open', '');
        container?.dispatchEvent(new Event('toggle'));

        expect(button.getAttribute('aria-expanded')).toBe('true');
        expect(panel.classList.contains('hidden')).toBe(false);
        expect(container?.open).toBe(true);

        container?.removeAttribute('open');
        container?.dispatchEvent(new Event('toggle'));

        expect(button.getAttribute('aria-expanded')).toBe('false');
        expect(panel.classList.contains('hidden')).toBe(true);
        expect(window.__omxfcProtokolleAccordionInitialised).toBe(true);
    });
});
