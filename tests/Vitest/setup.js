const jsdomWindow = globalThis.window?.jsdom?.window ?? globalThis.jsdom?.window ?? globalThis.window;

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

        Object.defineProperty(jsdomWindow, name, {
            configurable: true,
            value: storage,
            writable: false,
        });
    });
}
