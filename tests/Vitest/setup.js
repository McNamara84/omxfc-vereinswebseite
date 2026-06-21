const jsdomWindow = globalThis.window?.jsdom?.window ?? globalThis.jsdom?.window ?? globalThis.window;

const defineStorageAlias = (target, name, storage) => {
    if (!target) {
        return;
    }

    const storageAliasIsMissing = !(name in target);

    if (!storageAliasIsMissing) {
        const descriptor = Object.getOwnPropertyDescriptor(target, name);
        // Vitest may expose Node 26 storage accessors on globalThis; replace only that configurable alias.
        const canReplaceNodeStorageAccessor = target === globalThis
            && target.window !== jsdomWindow
            && descriptor?.configurable === true
            && typeof descriptor.get === 'function';

        if (!canReplaceNodeStorageAccessor) {
            return;
        }
    }

    Object.defineProperty(target, name, {
        configurable: true,
        value: storage,
        writable: false,
    });
};

if (jsdomWindow) {
    ['localStorage', 'sessionStorage'].forEach((name) => {
        if (!(name in jsdomWindow)) {
            return;
        }

        const storage = jsdomWindow[name];
        defineStorageAlias(jsdomWindow, name, storage);

        if (globalThis !== jsdomWindow) {
            defineStorageAlias(globalThis, name, storage);
        }
    });
}
