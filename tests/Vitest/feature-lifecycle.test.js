import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createFeatureLifecycle } from '../../resources/js/features/lifecycle';
import { mountAlpineFeature } from '../../resources/js/features/alpine';

const flush = async () => { for (let i = 0; i < 12; i++) await Promise.resolve(); };
const deferred = () => { let resolve, reject; const promise = new Promise((yes, no) => { resolve = yes; reject = no; }); return { promise, resolve, reject }; };
let lifecycle;
beforeEach(() => { document.body.innerHTML = ''; });
afterEach(() => { lifecycle?.destroy(); delete window.Alpine; vi.restoreAllMocks(); });

describe('feature lifecycle', () => {
    const setup = (load = vi.fn().mockResolvedValue({}), mount = vi.fn(() => vi.fn())) => {
        const onError = vi.fn();
        lifecycle = createFeatureLifecycle([{ selector: '[data-test-feature]', load, mount }], { onError });
        lifecycle.scan();
        return { load, mount, onError };
    };

    it('does not import absent features and shares one import across independent roots', async () => {
        const { load, mount } = setup();
        await flush();
        expect(load).not.toHaveBeenCalled();
        document.body.innerHTML = '<div data-test-feature></div><div data-test-feature></div>';
        await flush();
        expect(load).toHaveBeenCalledTimes(1);
        expect(mount).toHaveBeenCalledTimes(2);
        lifecycle.scan();
        document.dispatchEvent(new Event('livewire:navigated'));
        await flush();
        expect(mount).toHaveBeenCalledTimes(2);
        const cleanup = mount.mock.results[0].value;
        document.body.firstElementChild.remove();
        await flush();
        expect(cleanup).toHaveBeenCalledTimes(1);
    });

    it('cancels pending imports on navigation and mounts only the new connected root', async () => {
        const pending = deferred();
        document.body.innerHTML = '<div data-test-feature></div>';
        const { load, mount } = setup(vi.fn(() => pending.promise));
        document.dispatchEvent(new Event('livewire:navigating'));
        document.body.innerHTML = '<section data-test-feature></section>';
        pending.resolve({});
        await flush();
        expect(mount).not.toHaveBeenCalled();
        document.dispatchEvent(new Event('livewire:navigated'));
        await flush();
        expect(load).toHaveBeenCalledTimes(1);
        expect(mount).toHaveBeenCalledTimes(1);
        expect(mount.mock.calls[0][1].tagName).toBe('SECTION');
        document.dispatchEvent(new Event('livewire:navigating'));
        expect(mount.mock.calls[0][2].signal.aborted).toBe(true);
        expect(mount.mock.results[0].value).toHaveBeenCalledTimes(1);
    });

    it('cleans up a mount that finishes after navigation', async () => {
        const pending = deferred();
        const cleanup = vi.fn();
        document.body.innerHTML = '<div data-test-feature></div>';
        setup(undefined, vi.fn(() => pending.promise));
        await flush();
        document.dispatchEvent(new Event('livewire:navigating'));
        pending.resolve(cleanup);
        await flush();
        expect(cleanup).toHaveBeenCalledTimes(1);
    });

    it('reports import errors without retry loops and retries on explicit user action', async () => {
        document.body.innerHTML = '<div data-test-feature><p data-feature-status></p><button data-feature-retry hidden>Retry</button></div>';
        const load = vi.fn().mockRejectedValueOnce(new Error('offline')).mockResolvedValue({});
        const { mount, onError } = setup(load);
        await flush();
        expect(onError).toHaveBeenCalledTimes(1);
        expect(document.querySelector('[data-feature-retry]').hidden).toBe(false);
        lifecycle.scan();
        await flush();
        expect(load).toHaveBeenCalledTimes(1);
        document.querySelector('button').click();
        await flush();
        expect(load).toHaveBeenCalledTimes(2);
        expect(mount).toHaveBeenCalledTimes(1);
    });

    it('retries mount failures using the successful cached import', async () => {
        document.body.innerHTML = '<div data-test-feature></div>';
        const mount = vi.fn().mockImplementationOnce(() => { throw new Error('not ready'); }).mockReturnValue(undefined);
        const { load } = setup(undefined, mount);
        await flush();
        document.dispatchEvent(new Event('features:retry'));
        await flush();
        expect(load).toHaveBeenCalledTimes(1);
        expect(mount).toHaveBeenCalledTimes(2);
        expect(document.body.firstElementChild.dataset.featureState).toBe('ready');
    });

    it('ignores rejected imports and detached roots after cancellation', async () => {
        const pending = deferred();
        document.body.innerHTML = '<div data-test-feature></div>';
        const { mount, onError } = setup(() => pending.promise);
        document.body.innerHTML = '';
        await flush();
        pending.reject(new Error('late failure'));
        await flush();
        expect(onError).not.toHaveBeenCalled();
        expect(mount).not.toHaveBeenCalled();
    });

    it('unregisters navigation, mutation, and retry listeners on destruction', async () => {
        const { load } = setup();
        lifecycle.destroy();
        document.body.innerHTML = '<div data-test-feature></div>';
        for (const name of ['DOMContentLoaded', 'livewire:navigated', 'features:retry']) document.dispatchEvent(new Event(name));
        await flush();
        expect(load).not.toHaveBeenCalled();
    });
});

it('guards real Alpine expressions until registration and preserves input during scans', async () => {
    const { default: Alpine } = await import('alpinejs');
    const errors = vi.fn();
    Alpine.setErrorHandler(errors);
    window.Alpine = Alpine;
    document.body.innerHTML = '<div data-test-feature x-data x-ignore><p data-feature-status>Loading</p><button data-feature-retry hidden></button><div data-feature-content hidden inert><form x-data="lazyTest"><input x-model="name"><span x-text="name"></span></form></div></div>';
    const root = document.body.firstElementChild;
    Alpine.initTree(root);
    expect(errors).not.toHaveBeenCalled();
    const destroy = vi.fn();
    const register = () => Alpine.data('lazyTest', () => ({ name: 'Start', destroy }));
    const mount = vi.fn((module, element) => mountAlpineFeature(register, element));
    lifecycle = createFeatureLifecycle([{ selector: '[data-test-feature]', load: async () => ({}), mount }]);
    lifecycle.scan();
    await flush();
    const input = root.querySelector('input');
    expect(input.value).toBe('Start');
    input.value = 'Ungespeichert';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    lifecycle.scan();
    await flush();
    expect(input.value).toBe('Ungespeichert');
    expect(root.querySelector('span').textContent).toBe('Ungespeichert');
    expect(mount).toHaveBeenCalledTimes(1);
    lifecycle.destroy();
    expect(destroy).toHaveBeenCalledTimes(1);
    expect(root.hasAttribute('x-ignore')).toBe(true);
    expect(errors).not.toHaveBeenCalled();
});

it('can retry an Alpine feature when the runtime is not available yet', () => {
    expect(() => mountAlpineFeature(vi.fn(), document.body)).toThrow('Alpine');
});
