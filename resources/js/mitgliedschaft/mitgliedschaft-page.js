import initMitgliedschaftForm from './form';

export function ensureGlobalInitializer() {
    window.omxfc = window.omxfc || {};

    if (typeof window.omxfc.initMitgliedschaftForm !== 'function') {
        window.omxfc.initMitgliedschaftForm = (root = document, options = {}) =>
            initMitgliedschaftForm(root, options);
    }
}

export function startEnhancement() {
    ensureGlobalInitializer();

    if (typeof window.omxfc.queueInit === 'function') {
        window.omxfc.queueInit(() => window.omxfc.initMitgliedschaftForm());
        return;
    }

    window.omxfc.initMitgliedschaftForm();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startEnhancement, { once: true });
} else if (typeof queueMicrotask === 'function') {
    queueMicrotask(startEnhancement);
} else {
    startEnhancement();
}
