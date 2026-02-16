const HIDDEN_CLASS = 'hidden';

if (typeof window !== 'undefined' && typeof window.__omxfcProtokolleAccordionInitialised === 'undefined') {
    window.__omxfcProtokolleAccordionInitialised = false;
}

export function applyAccordionState(button, panel, icon, expand, container) {
    const parentDetails = container ?? button.closest('details');
    const shouldExpand = typeof expand === 'boolean' ? expand : Boolean(parentDetails?.open);

    if (parentDetails && parentDetails.open !== shouldExpand) {
        parentDetails.open = shouldExpand;
    }

    button.setAttribute('aria-expanded', shouldExpand ? 'true' : 'false');
    panel.classList.toggle(HIDDEN_CLASS, !shouldExpand);

    if (shouldExpand) {
        panel.removeAttribute('hidden');
    } else {
        panel.setAttribute('hidden', '');
    }

    panel.setAttribute('aria-hidden', shouldExpand ? 'false' : 'true');

    if (icon) {
        icon.textContent = shouldExpand ? '−' : '+';
    }

    return shouldExpand;
}

export function setupProtokolleAccordion(root = document) {
    const buttons = Array.from(root.querySelectorAll('[data-protokolle-accordion-button]'));

    buttons.forEach((button) => {
        const container = button.closest('details');
        const targetId = button.getAttribute('aria-controls');
        const panel = targetId ? root.getElementById(targetId) : null;

        if (!container || !panel) {
            return;
        }

        const icon = button.querySelector('[data-protokolle-accordion-icon]');
        const isExpanded = Boolean(container.open);
        applyAccordionState(button, panel, icon, isExpanded, container);

        container.addEventListener('toggle', () => {
            applyAccordionState(button, panel, icon, container.open, container);
        });
    });

    if (typeof window !== 'undefined' && buttons.length > 0) {
        window.__omxfcProtokolleAccordionInitialised = true;
    }

    return buttons;
}

if (typeof document !== 'undefined') {
    const bootstrapAccordion = () => {
        setupProtokolleAccordion();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrapAccordion, { once: true });
    } else {
        bootstrapAccordion();
    }

    document.addEventListener('livewire:navigated', () => {
        window.__omxfcProtokolleAccordionInitialised = false;
        bootstrapAccordion();
    });
}

export default setupProtokolleAccordion;
