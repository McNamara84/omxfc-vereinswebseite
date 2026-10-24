/** One import per feature, one mount per DOM root, and cancellation before navigation. */
export function createFeatureLifecycle(features, { document: doc = document, onError = console.error } = {}) {
    const imports = new Map();
    const instances = new Map();
    let paused = false;
    let scheduled = false;

    const load = feature => {
        if (!imports.has(feature)) {
            imports.set(feature, Promise.resolve().then(feature.load).catch(error => {
                imports.delete(feature); // A failed request must be retryable.
                throw error;
            }));
        }
        return imports.get(feature);
    };

    const dispose = (root, instance) => {
        instances.delete(root);
        instance.controller.abort();
        instance.cleanup?.();
        instance.errorElement?.remove();
    };

    const scan = () => {
        if (paused) return;
        for (const [root, instance] of instances) {
            if (!root.isConnected || !root.matches(instance.feature.selector)) dispose(root, instance);
        }
        for (const feature of features) {
            for (const root of doc.querySelectorAll(feature.selector)) {
                if (instances.has(root)) continue;
                const instance = { feature, controller: new AbortController(), cleanup: null };
                instances.set(root, instance);
                root.dataset.featureState = 'loading';
                load(feature).then(async module => {
                    const { signal } = instance.controller;
                    if (signal.aborted || !root.isConnected) return;
                    const cleanup = await feature.mount(module, root, { signal });
                    if (signal.aborted || !root.isConnected) {
                        cleanup?.();
                        return;
                    }
                    instance.cleanup = cleanup;
                    root.dataset.featureState = 'ready';
                }).catch(error => {
                    if (instance.controller.signal.aborted) return;
                    // Keep the failed instance until an explicit retry; DOM updates
                    // must not turn a network error into an endless request loop.
                    instance.failed = true;
                    root.dataset.featureState = 'error';
                    const status = root.querySelector('[data-feature-status]');
                    if (status) {
                        status.hidden = false;
                        status.textContent = 'Laden fehlgeschlagen. Bitte erneut versuchen.';
                    }
                    const retry = root.querySelector('[data-feature-retry]');
                    if (retry) retry.hidden = false;
                    if (!status) {
                        const notice = doc.createElement('div');
                        notice.setAttribute('role', 'status');
                        notice.className = 'alert alert-warning';
                        notice.textContent = 'Dieser Bereich konnte nicht geladen werden. ';
                        const button = doc.createElement('button');
                        button.type = 'button';
                        button.dataset.featureRetry = '';
                        button.className = 'btn btn-outline';
                        button.textContent = 'Erneut versuchen';
                        notice.append(button);
                        root.after(notice);
                        instance.errorElement = notice;
                    }
                    onError(error);
                });
            }
        }
    };

    const schedule = () => {
        if (scheduled || paused) return;
        scheduled = true;
        queueMicrotask(() => { scheduled = false; scan(); });
    };
    const clear = () => {
        paused = true;
        for (const [root, instance] of instances) dispose(root, instance);
    };
    const resume = () => { paused = false; schedule(); };
    const retry = () => {
        for (const [root, instance] of instances) {
            if (instance.failed) dispose(root, instance);
        }
        schedule();
    };
    const click = event => {
        if (event.target.closest('[data-feature-retry]')) retry();
    };
    const observer = new MutationObserver(schedule);
    observer.observe(doc.documentElement, { childList: true, subtree: true });
    doc.addEventListener('DOMContentLoaded', resume);
    doc.addEventListener('livewire:navigated', resume);
    doc.addEventListener('livewire:navigating', clear);
    doc.addEventListener('features:retry', retry);
    doc.addEventListener('click', click);
    if (doc.readyState !== 'loading') resume();

    return {
        scan, retry,
        destroy() {
            clear();
            observer.disconnect();
            doc.removeEventListener('DOMContentLoaded', resume);
            doc.removeEventListener('livewire:navigated', resume);
            doc.removeEventListener('livewire:navigating', clear);
            doc.removeEventListener('features:retry', retry);
            doc.removeEventListener('click', click);
        },
    };
}
