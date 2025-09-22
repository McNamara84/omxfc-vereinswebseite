const updateHiddenState = (panel, expanded) => {
    panel.classList.toggle('hidden', !expanded);

    if (!expanded) {
        panel.setAttribute('hidden', '');
        panel.setAttribute('aria-hidden', 'true');
    } else {
        panel.removeAttribute('hidden');
        panel.setAttribute('aria-hidden', 'false');
    }
};

export const applyReviewsAccordionState = (button, panel, icon, expanded) => {
    const nextState = Boolean(expanded);

    button.setAttribute('aria-expanded', nextState ? 'true' : 'false');
    updateHiddenState(panel, nextState);

    if (icon) {
        icon.textContent = nextState ? '−' : '+';
    }

    return nextState;
};

export const setupReviewsAccordion = () => {
    if (window.__omxfcReviewsAccordionInitialised) {
        return;
    }

    const accordions = document.querySelectorAll('[data-reviews-accordion]');

    accordions.forEach((accordion) => {
        const button = accordion.querySelector('[data-reviews-accordion-button]');
        const panel = accordion.querySelector('[data-reviews-accordion-panel]');
        const icon = accordion.querySelector('[data-reviews-accordion-icon]');

        if (!button || !panel) {
            return;
        }

        const initiallyExpanded = button.getAttribute('aria-expanded') === 'true' || !panel.classList.contains('hidden');
        applyReviewsAccordionState(button, panel, icon, initiallyExpanded);

        button.addEventListener('click', () => {
            const isExpanded = button.getAttribute('aria-expanded') === 'true';
            applyReviewsAccordionState(button, panel, icon, !isExpanded);
        });
    });

    window.__omxfcReviewsAccordionInitialised = true;
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        setupReviewsAccordion();
    });
} else {
    setupReviewsAccordion();
}

window.__omxfcSetupReviewsAccordion = setupReviewsAccordion;
