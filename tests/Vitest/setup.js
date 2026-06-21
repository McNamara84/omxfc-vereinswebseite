const jsdomWindow = globalThis.jsdom?.window;

if (jsdomWindow) {
    const browserStorage = {
        localStorage: jsdomWindow.localStorage,
        sessionStorage: jsdomWindow.sessionStorage,
    };

    Object.entries(browserStorage).forEach(([name, storage]) => {
        Object.defineProperty(globalThis, name, {
            configurable: true,
            value: storage,
            writable: false,
        });

        Object.defineProperty(globalThis.window, name, {
            configurable: true,
            value: storage,
            writable: false,
        });
    });
}
