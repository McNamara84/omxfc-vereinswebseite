import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const importChecks = () => import('../../resources/js/alpine/rpg-checks.js');
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
});

describe('RPG check provider registration', () => {
    it('registers once before a later Alpine startup without manually initializing roots', async () => {
        document.body.innerHTML = '<form x-data="rpgCheckForm({})"></form><div x-data="rpgChecks({})"></div>';
        const module = await importChecks();
        window.Alpine = alpineMock();
        document.dispatchEvent(new Event('alpine:init'));
        document.dispatchEvent(new Event('alpine:init'));
        expect(window.Alpine.data.mock.calls).toEqual([
            ['rpgCheckForm', module.rpgCheckForm], ['rpgChecks', module.rpgChecks],
        ]);
        expect(window.Alpine.initTree).not.toHaveBeenCalled();
    });

    it('registers immediately and initializes both existing root types when Alpine is present', async () => {
        document.body.innerHTML = '<form x-data="rpgCheckForm({})"></form><div x-data="rpgChecks({})"></div><div x-data="unrelated"></div>';
        window.Alpine = alpineMock();
        const module = await importChecks();
        expect(window.Alpine.data.mock.calls).toEqual([
            ['rpgCheckForm', module.rpgCheckForm], ['rpgChecks', module.rpgChecks],
        ]);
        expect(window.Alpine.initTree.mock.calls).toEqual([
            [document.querySelector('form')], [document.querySelector('[x-data^="rpgChecks("]')],
        ]);
        expect(window.Alpine.destroyTree).not.toHaveBeenCalled();
    });

    it.each(['rpgCheckForm', 'rpgChecks'])('repairs a previously failed %s root before initializing it', async (name) => {
        document.body.innerHTML = `<div x-data="${name}({})"></div>`;
        const root = document.body.firstElementChild;
        root._x_dataStack = [{}];
        window.Alpine = alpineMock();
        await importChecks();
        expect(window.Alpine.destroyTree).toHaveBeenCalledWith(root);
        expect(window.Alpine.initTree).toHaveBeenCalledWith(root);
        expect(window.Alpine.destroyTree.mock.invocationCallOrder[0]).toBeLessThan(window.Alpine.initTree.mock.invocationCallOrder[0]);
    });

    it('preserves already functional forms and displays during repeated module registration', async () => {
        document.body.innerHTML = '<form x-data="rpgCheckForm"></form><div x-data="rpgChecks"></div>';
        const [form, display] = document.body.children;
        form._x_dataStack = [{ preview: vi.fn(), save: vi.fn(), participants: [{ character_id: 1 }] }];
        display._x_dataStack = [{ act: vi.fn(), refresh: vi.fn(), data: { checks: [{ id: 1 }] } }];
        window.Alpine = alpineMock();
        window.Alpine.$data.mockImplementation((element) => element._x_dataStack[0]);
        await importChecks();
        vi.resetModules();
        await importChecks();
        expect(window.Alpine.destroyTree).not.toHaveBeenCalled();
        expect(window.Alpine.initTree).not.toHaveBeenCalled();
        expect(form._x_dataStack[0].participants).toEqual([{ character_id: 1 }]);
        expect(display._x_dataStack[0].data.checks).toEqual([{ id: 1 }]);
    });

    it('registers providers even when Alpine does not expose hydration APIs', async () => {
        window.Alpine = { data: vi.fn() };
        await importChecks();
        expect(window.Alpine.data).toHaveBeenCalledTimes(2);
    });

    it('ignores an init event without an available Alpine registration API', async () => {
        window.Alpine = {};
        await importChecks();
        expect(() => document.dispatchEvent(new Event('alpine:init'))).not.toThrow();
    });
});

it('repairs real Alpine roots after late bundle loading and keeps one poller alive', async () => {
    vi.useFakeTimers();
    Object.defineProperty(document, 'hidden', { configurable: true, value: false });
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, status: 200, json: async () => ({ checks: [] }) }));
    const { default: Alpine } = await import('alpinejs');
    const errors = vi.fn();
    Alpine.setErrorHandler(errors);
    window.Alpine = Alpine;
    document.body.innerHTML = `
        <form x-data='rpgCheckForm({characters: [], skills: []})'><input x-model="description"></form>
        <section x-data='rpgChecks({initial: {checks: []}, url: "/checks"})'><span x-text="data.checks.length"></span></section>`;
    const [form, display] = document.body.children;
    try {
        Alpine.initTree(form);
        Alpine.initTree(display);
        await vi.advanceTimersByTimeAsync(0);
        expect(errors).toHaveBeenCalled();
        errors.mockClear();

        await importChecks();
        await vi.advanceTimersByTimeAsync(0);
        expect(errors).not.toHaveBeenCalled();
        expect(display.querySelector('span').textContent).toBe('0');
        const input = form.querySelector('input');
        input.value = 'Unfertige Aufforderung';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        expect(Alpine.$data(form).description).toBe('Unfertige Aufforderung');

        vi.resetModules();
        await importChecks();
        expect(Alpine.$data(form).description).toBe('Unfertige Aufforderung');
        await vi.advanceTimersByTimeAsync(5000);
        expect(fetch).toHaveBeenCalledTimes(1);
        await vi.advanceTimersByTimeAsync(5000);
        expect(fetch).toHaveBeenCalledTimes(2);
    } finally {
        Alpine.destroyTree(form);
        Alpine.destroyTree(display);
    }
    await vi.advanceTimersByTimeAsync(10000);
    expect(fetch).toHaveBeenCalledTimes(2);
});
