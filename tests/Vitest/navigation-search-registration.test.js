import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const importSearch = () => import('../../resources/js/navigation-search.js');
const alpineMock = () => ({ data: vi.fn(), initTree: vi.fn(), destroyTree: vi.fn(), $data: vi.fn(() => ({})) });

beforeEach(() => {
    vi.resetModules();
    delete window.Alpine;
    document.body.innerHTML = '';
});

afterEach(() => {
    delete window.Alpine;
    document.body.innerHTML = '';
    vi.useRealTimers();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('navigation search provider registration', () => {
    it('registers once before Alpine startup and lets Alpine initialize the roots', async () => {
        document.body.innerHTML = '<div x-data="navigationSpotlight({})"></div>';
        const module = await importSearch();
        window.Alpine = alpineMock();

        document.dispatchEvent(new Event('alpine:init'));
        document.dispatchEvent(new Event('alpine:init'));

        expect(window.Alpine.data.mock.calls).toEqual([['navigationSpotlight', module.navigationSpotlight]]);
        expect(window.Alpine.initTree).not.toHaveBeenCalled();
        expect(window.Alpine.destroyTree).not.toHaveBeenCalled();
    });

    it('initializes existing spotlight roots after registering and leaves unrelated roots alone', async () => {
        document.body.innerHTML = '<div x-data="navigationSpotlight({})"></div><div x-data="navigationSpotlight"></div><div x-data="unrelated"></div>';
        window.Alpine = alpineMock();

        const module = await importSearch();

        expect(window.Alpine.data).toHaveBeenCalledWith('navigationSpotlight', module.navigationSpotlight);
        expect(window.Alpine.initTree.mock.calls).toEqual([...document.body.children].slice(0, 2).map(root => [root]));
        expect(window.Alpine.destroyTree).not.toHaveBeenCalled();
        expect(window.Alpine.data.mock.invocationCallOrder[0]).toBeLessThan(window.Alpine.initTree.mock.invocationCallOrder[0]);
    });

    it.each([{}, { show() {} }, { show() {}, search() {}, results: null }])('repairs a failed or incomplete spotlight root: %j', async (scope) => {
        document.body.innerHTML = '<div x-data="navigationSpotlight({})"></div>';
        const root = document.body.firstElementChild;
        root._x_dataStack = [scope];
        window.Alpine = alpineMock();
        window.Alpine.$data.mockReturnValue(scope);

        await importSearch();

        expect(window.Alpine.destroyTree).toHaveBeenCalledWith(root);
        expect(window.Alpine.initTree).toHaveBeenCalledWith(root);
        expect(window.Alpine.destroyTree.mock.invocationCallOrder[0]).toBeLessThan(window.Alpine.initTree.mock.invocationCallOrder[0]);
    });

    it('preserves functional spotlight state and pending work during repeated registration', async () => {
        document.body.innerHTML = '<div x-data="navigationSpotlight({})"></div>';
        const root = document.body.firstElementChild;
        const scope = { show: vi.fn(), search: vi.fn(), results: [{ name: 'Baxx' }], value: 'Baxx', controller: new AbortController() };
        root._x_dataStack = [scope];
        window.Alpine = alpineMock();
        window.Alpine.$data.mockReturnValue(scope);

        await importSearch();
        vi.resetModules();
        await importSearch();

        expect(window.Alpine.destroyTree).not.toHaveBeenCalled();
        expect(window.Alpine.initTree).not.toHaveBeenCalled();
        expect(scope.value).toBe('Baxx');
        expect(scope.results).toEqual([{ name: 'Baxx' }]);
        expect(scope.controller.signal.aborted).toBe(false);
    });

    it.each(['initTree', 'destroyTree', '$data'])('still registers when Alpine lacks the %s hydration API', async (name) => {
        const Alpine = alpineMock();
        const { initTree, destroyTree } = Alpine;
        delete Alpine[name];
        window.Alpine = Alpine;

        const module = await importSearch();

        expect(Alpine.data).toHaveBeenCalledWith('navigationSpotlight', module.navigationSpotlight);
        expect(initTree).not.toHaveBeenCalled();
        expect(destroyTree).not.toHaveBeenCalled();
    });

    it.each([undefined, {}])('ignores an init event without a registration API: %j', async (Alpine) => {
        window.Alpine = Alpine;
        await importSearch();

        expect(() => document.dispatchEvent(new Event('alpine:init'))).not.toThrow();
    });
});

it('repairs a real Alpine root after late loading and preserves its active search on registration', async () => {
    vi.useFakeTimers();
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => [{ name: 'Chronik', link: '/chronik' }] }));
    const { default: Alpine } = await import('alpinejs');
    const errors = vi.fn();
    Alpine.setErrorHandler(errors);
    window.Alpine = Alpine;
    document.body.innerHTML = `
        <button data-testid="navigation-search-trigger">Suche</button>
        <div x-data='navigationSpotlight({url: "/mary/spotlight", scope: "member"})'
            x-on:mary-search-open.window="show()" x-on:keydown.window.capture="shortcut($event)">
            <dialog x-ref="dialog"><input x-ref="input" x-model="value" x-on:input="schedule()">
                <span x-text="value"></span><ul x-ref="results"></ul>
            </dialog>
        </div>`;
    const root = document.querySelector('[x-data]');
    const dialog = root.querySelector('dialog');
    dialog.showModal = vi.fn(() => { dialog.open = true; });
    dialog.close = vi.fn(() => { dialog.open = false; });
    try {
        Alpine.initTree(root);
        await vi.advanceTimersByTimeAsync(0);
        expect(errors).toHaveBeenCalled();
        errors.mockClear();

        await importSearch();
        await vi.advanceTimersByTimeAsync(0);
        expect(errors).not.toHaveBeenCalled();
        document.querySelector('button').focus();
        window.dispatchEvent(new CustomEvent('mary-search-open'));
        expect(dialog.open).toBe(true);
        expect(document.activeElement).toBe(dialog.querySelector('input'));

        const input = dialog.querySelector('input');
        input.value = 'Chronik';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        await vi.advanceTimersByTimeAsync(250);
        expect(fetch).toHaveBeenCalledOnce();
        expect(Alpine.$data(root).results).toEqual([{ name: 'Chronik', link: '/chronik' }]);

        const destroy = vi.spyOn(Alpine, 'destroyTree');
        const init = vi.spyOn(Alpine, 'initTree');
        vi.resetModules();
        await importSearch();
        expect(destroy).not.toHaveBeenCalled();
        expect(init).not.toHaveBeenCalled();
        expect(Alpine.$data(root).value).toBe('Chronik');

        Alpine.$data(root).close(false);
        const shortcut = new KeyboardEvent('keydown', { key: 'k', metaKey: true, cancelable: true });
        window.dispatchEvent(shortcut);
        expect(shortcut.defaultPrevented).toBe(true);
        expect(dialog.showModal).toHaveBeenCalledTimes(2);
        expect(dialog.open).toBe(true);
        expect(Alpine.$data(root).value).toBe('');
        expect(errors).not.toHaveBeenCalled();
    } finally {
        Alpine.destroyTree(root);
    }
});
