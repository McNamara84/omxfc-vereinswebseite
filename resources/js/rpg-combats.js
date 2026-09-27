import { createCheckPoller } from './rpg-check-polling';

export function mountCombat(root) {
    const content = root.querySelector('[data-combat-content]');
    const notice = root.querySelector('[data-combat-notice]');
    let revision = Number(root.dataset.revision);
    let dirty = false;
    let submitting = false;
    const markDirty = () => { dirty = true; };
    const poller = createCheckPoller({
        url: root.dataset.url,
        onData(data) {
            if (submitting || Number(data.revision) <= revision) return;
            if (dirty || content.contains(document.activeElement)) {
                notice.textContent = 'Der Kampf wurde fortgesetzt. Bitte vor dem Absenden aktualisieren; deine Eingabe wurde nicht überschrieben.';
                return;
            }
            // Use the active (possibly Livewire-provided) Alpine instance. Suspend
            // its observer so each replaced subtree has one explicit lifecycle.
            const alpine = window.Alpine;
            alpine.mutateDom(() => {
                alpine.destroyTree(content);
                // Blade escapes all character names, descriptions and log data.
                content.innerHTML = data.html;
                alpine.initTree(content);
            });
            revision = Number(data.revision);
            notice.textContent = 'Kampfstand aktualisiert.';
        },
        onError(message, fatal) {
            notice.textContent = message;
            if (fatal) {
                content.querySelectorAll('button, input, select, textarea').forEach(el => { el.disabled = true; });
            }
        },
    });
    const submit = event => {
        if (submitting) { event.preventDefault(); return; }
        submitting = true;
        poller.pause();
        // Do not disable the submitter: its name/value carries the chosen command.
        root.setAttribute('aria-busy', 'true');
    };
    root.addEventListener('input', markDirty);
    root.addEventListener('change', markDirty);
    root.addEventListener('submit', submit);
    return () => {
        poller.destroy();
        root.removeEventListener('input', markDirty);
        root.removeEventListener('change', markDirty);
        root.removeEventListener('submit', submit);
    };
}

let cleanups = [];
export function initCombats() {
    cleanups.forEach(cleanup => cleanup());
    cleanups = [...document.querySelectorAll('[data-rpg-combat]')].map(mountCombat);
}
export function destroyCombats() {
    cleanups.forEach(cleanup => cleanup());
    cleanups = [];
}
