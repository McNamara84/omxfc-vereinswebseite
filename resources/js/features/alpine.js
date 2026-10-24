/** Release a Blade x-ignore boundary only after its factories are registered. */
export function mountAlpineFeature(register, root) {
    const Alpine = window.Alpine;
    if (!Alpine) throw new Error('Alpine ist noch nicht verfügbar.');
    register();
    const content = root.querySelector('[data-feature-content]');
    const status = root.querySelector('[data-feature-status]');
    const retry = root.querySelector('[data-feature-retry]');
    Alpine.mutateDom(() => {
        Alpine.destroyTree(root);
        root.removeAttribute('x-ignore');
        content.hidden = false;
        content.removeAttribute('inert');
        status.hidden = true;
        retry.hidden = true;
        Alpine.initTree(root);
    });
    return () => Alpine.mutateDom(() => {
        Alpine.destroyTree(root);
        root.setAttribute('x-ignore', '');
        content.hidden = true;
        content.setAttribute('inert', '');
        status.hidden = false;
    });
}
