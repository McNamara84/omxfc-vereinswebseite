import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createFeatureLifecycle } from '../../resources/js/features/lifecycle';
import { mountAlpineFeature } from '../../resources/js/features/alpine';

// Cross a task boundary so pending observer callbacks finish before negative assertions.
const settleMutations = () => new Promise(resolve => setTimeout(resolve, 0));
const deferred = () => { let resolve, reject; const promise = new Promise((yes, no) => { resolve = yes; reject = no; }); return { promise, resolve, reject }; };
let lifecycle;
beforeEach(() => { lifecycle = undefined; document.body.innerHTML = ''; });
afterEach(() => { lifecycle?.destroy(); vi.unstubAllGlobals(); vi.restoreAllMocks(); });

describe('feature lifecycle', () => {
    const setup = (load = vi.fn().mockResolvedValue({}), mount = vi.fn(() => vi.fn())) => {
        const onError = vi.fn();
        lifecycle = createFeatureLifecycle([{ selector: '[data-test-feature]', load, mount }], { onError });
        lifecycle.scan();
        return { load, mount, onError };
    };

    it('does not import absent features and shares one import across independent roots', async () => {
        const { load, mount } = setup();
        await settleMutations();
        expect(load).not.toHaveBeenCalled();
        document.body.innerHTML = '<div data-test-feature></div><div data-test-feature></div>';
        await vi.waitFor(() => expect(mount).toHaveBeenCalledTimes(2), {
            message: 'Both connected feature roots finish mounting after their shared import',
        });
        expect(load).toHaveBeenCalledTimes(1);
        expect(mount).toHaveBeenCalledTimes(2);
        lifecycle.scan();
        document.dispatchEvent(new Event('livewire:navigated'));
        await settleMutations();
        expect(mount).toHaveBeenCalledTimes(2);
        const cleanup = mount.mock.results[0].value;
        document.body.firstElementChild.remove();
        await vi.waitFor(() => expect(cleanup).toHaveBeenCalledTimes(1), {
            message: 'Removing a mounted root releases its resources',
        });
    });

    it('cancels pending imports on navigation and mounts only the new connected root', async () => {
        const pending = deferred();
        document.body.innerHTML = '<div data-test-feature></div>';
        const { load, mount } = setup(vi.fn(() => pending.promise));
        document.dispatchEvent(new Event('livewire:navigating'));
        document.body.innerHTML = '<section data-test-feature></section>';
        pending.resolve({});
        await pending.promise;
        await settleMutations();
        expect(mount).not.toHaveBeenCalled();
        document.dispatchEvent(new Event('livewire:navigated'));
        await vi.waitFor(() => expect(mount).toHaveBeenCalledTimes(1), {
            message: 'Navigation mounts only the new connected root',
        });
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
        const { mount } = setup(undefined, vi.fn(() => pending.promise));
        await vi.waitFor(() => expect(mount).toHaveBeenCalledOnce(), {
            message: 'The asynchronous mount starts before navigation cancels it',
        });
        document.dispatchEvent(new Event('livewire:navigating'));
        pending.resolve(cleanup);
        await vi.waitFor(() => expect(cleanup).toHaveBeenCalledTimes(1), {
            message: 'A mount finishing after navigation immediately releases its resources',
        });
    });

    it('reports import errors without retry loops and retries on explicit user action', async () => {
        document.body.innerHTML = '<div data-test-feature><p data-feature-status></p><button data-feature-retry hidden>Retry</button></div>';
        const load = vi.fn().mockRejectedValueOnce(new Error('offline')).mockResolvedValue({});
        const { mount, onError } = setup(load);
        await vi.waitFor(() => expect(onError).toHaveBeenCalledTimes(1), {
            message: 'The failed import exposes an explicit retry without mounting',
        });
        expect(document.querySelector('[data-feature-retry]').hidden).toBe(false);
        lifecycle.scan();
        await settleMutations();
        expect(load).toHaveBeenCalledTimes(1);
        document.querySelector('button').click();
        await vi.waitFor(() => expect(document.body.firstElementChild.dataset.featureState).toBe('ready'), {
            message: 'An explicit retry recovers the failed feature',
        });
        expect(load).toHaveBeenCalledTimes(2);
        expect(mount).toHaveBeenCalledTimes(1);
    });

    it('retries mount failures using the successful cached import', async () => {
        document.body.innerHTML = '<div data-test-feature></div>';
        const mount = vi.fn().mockImplementationOnce(() => { throw new Error('not ready'); }).mockReturnValue(undefined);
        const { load } = setup(undefined, mount);
        await vi.waitFor(() => expect(document.body.firstElementChild.dataset.featureState).toBe('error'), {
            message: 'The first mount reports its failure before retrying',
        });
        document.dispatchEvent(new Event('features:retry'));
        await vi.waitFor(() => expect(document.body.firstElementChild.dataset.featureState).toBe('ready'), {
            message: 'Retrying the mount reuses its successful import',
        });
        expect(load).toHaveBeenCalledTimes(1);
        expect(mount).toHaveBeenCalledTimes(2);
        expect(document.body.firstElementChild.dataset.featureState).toBe('ready');
    });

    it('ignores rejected imports and detached roots after cancellation', async () => {
        const pending = deferred();
        document.body.innerHTML = '<div data-test-feature></div>';
        const { mount, onError } = setup(() => pending.promise);
        document.body.innerHTML = '';
        await settleMutations();
        pending.reject(new Error('late failure'));
        await pending.promise.catch(() => {});
        await settleMutations();
        expect(onError).not.toHaveBeenCalled();
        expect(mount).not.toHaveBeenCalled();
    });

    it('unregisters navigation, mutation, and retry listeners on destruction', async () => {
        const { load } = setup();
        lifecycle.destroy();
        document.body.innerHTML = '<div data-test-feature></div>';
        for (const name of ['DOMContentLoaded', 'livewire:navigated', 'features:retry']) document.dispatchEvent(new Event(name));
        await settleMutations();
        expect(load).not.toHaveBeenCalled();
    });

    it('releases a mounted root when it no longer matches the feature selector', async () => {
        document.body.innerHTML = '<div data-test-feature></div>';
        const { mount } = setup();
        const root = document.body.firstElementChild;
        await vi.waitFor(() => expect(root.dataset.featureState).toBe('ready'), {
            message: 'The root is mounted before its feature marker is removed',
        });
        const signal = mount.mock.calls[0][2].signal;
        const cleanup = mount.mock.results[0].value;

        root.removeAttribute('data-test-feature');
        lifecycle.scan();

        expect(signal.aborted).toBe(true);
        expect(cleanup).toHaveBeenCalledOnce();
        lifecycle.scan();
        expect(cleanup).toHaveBeenCalledOnce();
        expect(mount).toHaveBeenCalledOnce();
    });
});

it('guards real Alpine expressions until registration and preserves input during scans', async () => {
    const { default: Alpine } = await import('alpinejs');
    const errors = vi.fn();
    Alpine.setErrorHandler(errors);
    vi.stubGlobal('Alpine', Alpine);
    document.body.innerHTML = '<div data-test-feature x-data x-ignore><p data-feature-status>Loading</p><button data-feature-retry hidden></button><div data-feature-content hidden inert><form x-data="lazyTest"><input x-model="name"><span x-text="name"></span></form></div></div>';
    const root = document.body.firstElementChild;
    Alpine.initTree(root);
    expect(errors).not.toHaveBeenCalled();
    const destroy = vi.fn();
    const register = () => Alpine.data('lazyTest', () => ({ name: 'Start', destroy }));
    const mount = vi.fn((module, element) => mountAlpineFeature(register, element));
    lifecycle = createFeatureLifecycle([{ selector: '[data-test-feature]', load: async () => ({}), mount }]);
    lifecycle.scan();
    await vi.waitFor(() => expect(root.dataset.featureState).toBe('ready'), {
        message: 'The registered Alpine feature initializes its input',
    });
    const input = root.querySelector('input');
    expect(input.value).toBe('Start');
    input.value = 'Ungespeichert';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    lifecycle.scan();
    await vi.waitFor(() => expect(root.querySelector('span').textContent).toBe('Ungespeichert'), {
        message: 'Alpine reflects the unsaved input while scans preserve the mounted root',
    });
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
