import { beforeEach, describe, expect, it, vi } from 'vitest';

const modulePath = '../../../resources/js/mitgliedschaft/mitgliedschaft-page';
const formModulePath = '../../../resources/js/mitgliedschaft/form';

describe('mitgliedschaft page entry', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.resetModules();
        vi.clearAllMocks();
        delete window.omxfc;
        Object.defineProperty(document, 'readyState', {
            configurable: true,
            get: () => 'loading',
        });
        vi.spyOn(document, 'addEventListener').mockImplementation(() => {});
    });

    it('exposes the global initializer through ensureGlobalInitializer', async () => {
        const initSpy = vi.fn();
        vi.doMock(formModulePath, () => ({ default: initSpy }));
        const { ensureGlobalInitializer } = await import(modulePath);

        ensureGlobalInitializer();

        expect(typeof window.omxfc.initMitgliedschaftForm).toBe('function');

        const root = { id: 'root' };
        const options = { hydrate: true };
        window.omxfc.initMitgliedschaftForm(root, options);

        expect(initSpy).toHaveBeenCalledWith(root, options);
    });

    it('uses queueInit when available during startEnhancement', async () => {
        const initSpy = vi.fn();
        vi.doMock(formModulePath, () => ({ default: initSpy }));
        const { startEnhancement } = await import(modulePath);
        const queueInit = vi.fn((callback) => callback());

        window.omxfc = { queueInit };

        startEnhancement();

        expect(queueInit).toHaveBeenCalledTimes(1);
        expect(initSpy).toHaveBeenCalledTimes(1);
    });

    it('initializes immediately when queueInit is absent', async () => {
        const initSpy = vi.fn();
        vi.doMock(formModulePath, () => ({ default: initSpy }));
        const { startEnhancement } = await import(modulePath);

        window.omxfc = {};

        startEnhancement();

        expect(initSpy).toHaveBeenCalledTimes(1);
    });
});
