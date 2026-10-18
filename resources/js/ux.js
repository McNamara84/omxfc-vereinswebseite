const submittedForms = new WeakSet();
let initialized = false;

const uxForms = root => root.matches?.('form[data-ux-form]') ? [root] : Array.from(root.querySelectorAll('form[data-ux-form]'));

export function revealHashTarget(hash = window.location.hash) {
    let target;
    try { target = document.getElementById(decodeURIComponent(hash.replace(/^#/, ''))); } catch { return false; }
    if (!target) return false;
    for (let parent = target; parent; parent = parent.parentElement) {
        if (parent.tagName === 'DETAILS') parent.open = true;
    }
    target.scrollIntoView?.({ block: 'start' });
    if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
    target.focus({ preventScroll: true });
    return true;
}

export function focusFormErrors(root = document) {
    uxForms(root).forEach(form => {
        if (!submittedForms.has(form)) return;
        submittedForms.delete(form);
        form.querySelector('[data-ux-error-summary]')?.focus();
    });
}

export function formSnapshot(form) {
    return JSON.stringify(Array.from(form.querySelectorAll('input:not([type=hidden]):not([type=submit]), textarea, select'))
        .map(field => [field.name || field.id, field.type === 'checkbox' || field.type === 'radio' ? field.checked : field.value]));
}

export function registerUxLifecycle() {
    if (initialized) return;
    initialized = true;
    const baselines = new WeakMap();
    const savingForms = new WeakSet();
    let hooksRegistered = false;
    const prepare = () => {
        document.querySelectorAll('form[data-guard-dirty]').forEach(form => {
            if (!baselines.has(form)) baselines.set(form, formSnapshot(form));
        });
        if (window.location.hash) requestAnimationFrame(() => revealHashTarget());
    };
    const dirty = () => Array.from(document.querySelectorAll('form[data-guard-dirty]'))
        .some(form => !savingForms.has(form) && baselines.has(form) && baselines.get(form) !== formSnapshot(form));
    document.addEventListener('submit', event => {
        if (event.target.matches('form[data-ux-form]')) submittedForms.add(event.target);
        if (event.target.matches('form[data-guard-dirty]')) savingForms.add(event.target);
    }, true);
    document.addEventListener('livewire:navigate', event => {
        if (dirty() && !window.confirm('Du hast ungespeicherte Änderungen. Möchtest du die Seite wirklich verlassen?')) event.preventDefault();
    });
    window.addEventListener('beforeunload', event => {
        if (dirty()) { event.preventDefault(); event.returnValue = ''; }
    });
    const hooks = () => {
        if (hooksRegistered || !window.Livewire) return;
        hooksRegistered = true;
        window.Livewire?.hook('commit', ({ component, commit, succeed }) => {
            const submissions = uxForms(component.el).filter(form => {
                const method = form.getAttribute('wire:submit')?.split('(')[0].trim();
                return submittedForms.has(form) && commit.calls?.some(call => call.method === method);
            });
            // Field updates can finish before the submission's own response.
            succeed(() => submissions.forEach(form => focusFormErrors(form)));
        });
        window.Livewire?.hook('morphed', ({ el }) => {
            if (el.matches?.('form[data-guard-dirty]')) savingForms.delete(el);
            el.querySelectorAll?.('form[data-guard-dirty]').forEach(form => savingForms.delete(form));
        });
        window.Livewire?.hook('request', ({ fail }) => fail(() => {
            document.querySelectorAll('form[data-guard-dirty]').forEach(form => savingForms.delete(form));
        }));
    };
    document.addEventListener('livewire:init', hooks, { once: true });
    if (window.Livewire) hooks();
    document.addEventListener('DOMContentLoaded', prepare);
    document.addEventListener('livewire:navigated', prepare);
    window.addEventListener('hashchange', () => revealHashTarget());
    if (document.readyState !== 'loading') prepare();
}

registerUxLifecycle();
