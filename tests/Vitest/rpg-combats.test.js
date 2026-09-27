import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Alpine from 'alpinejs';
import { mountCombat, initCombats, destroyCombats } from '../../resources/js/rpg-combats.js';

let cleanup;
const response = (data, status = 200) => ({ ok: status < 400, status, json: async () => data });
beforeEach(() => {
    vi.useFakeTimers();
    vi.stubGlobal('Alpine', Alpine);
    Object.defineProperty(document, 'hidden', { configurable: true, value: false });
    document.body.innerHTML = '<div data-rpg-combat data-url="/fight" data-revision="1"><p data-combat-notice></p><div data-combat-content><form><input name="move"><button>Würfeln</button></form></div></div>';
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(response({ revision: 2, html: '<p>Neue Entscheidung</p>' })));
});
afterEach(() => { cleanup?.(); cleanup = null; destroyCombats(); Alpine.destroyTree(root()); vi.useRealTimers(); vi.unstubAllGlobals(); });
const root = () => document.querySelector('[data-rpg-combat]');
it('updates new revisions automatically', async () => {
    cleanup = mountCombat(root());
    await vi.advanceTimersByTimeAsync(5000);
    expect(root().textContent).toContain('Neue Entscheidung');
    expect(fetch.mock.calls[0][1].cache).toBe('no-store');
});
it('cleans up replaced Alpine forms and initializes conditional controls after every revision', async () => {
    const lifecycle = vi.fn();
    const listener = vi.fn();
    vi.stubGlobal('combatLifecycle', lifecycle);
    vi.stubGlobal('combatListener', listener);
    const formHtml = label => `<form
        x-data="{ kind: 'attack', init() { window.combatLifecycle('${label}:init') }, destroy() { window.combatLifecycle('${label}:destroy') } }"
        @combat-probe.window="window.combatListener('${label}')">
        <select name="kind" x-model="kind"><option value="attack">Attack</option><option value="wait">Wait</option></select>
        <fieldset x-show="kind === 'attack'" :disabled="kind !== 'attack'"><input name="weapon" value="fist"></fieldset>
        <fieldset x-show="kind === 'psychic'" :disabled="kind !== 'psychic'"><input name="power" value="Pyrokinese"></fieldset>
    </form>`;
    const content = root().querySelector('[data-combat-content]');
    content.innerHTML = formHtml('old');
    Alpine.initTree(content);
    cleanup = mountCombat(root());
    for (const [revision, label] of [[2, 'new'], [3, 'latest']]) {
        fetch.mockResolvedValue(response({ revision, html: formHtml(label) }));
        await vi.advanceTimersByTimeAsync(5000);
        const form = content.querySelector('form');
        expect(Object.fromEntries(new FormData(form))).toEqual({ kind: 'attack', weapon: 'fist' });
        expect(form.querySelector('[name="power"]').closest('fieldset').style.display).toBe('none');
    }
    await vi.advanceTimersByTimeAsync(5000);
    expect(lifecycle.mock.calls.flat()).toEqual(['old:init', 'old:destroy', 'new:init', 'new:destroy', 'latest:init']);
    window.dispatchEvent(new Event('combat-probe'));
    expect(listener.mock.calls.flat()).toEqual(['latest']);

    const form = content.querySelector('form');
    const select = form.querySelector('select');
    select.value = 'wait';
    select.dispatchEvent(new Event('change', { bubbles: true }));
    await vi.advanceTimersByTimeAsync(50);
    expect(Object.fromEntries(new FormData(form))).toEqual({ kind: 'wait' });
    expect(form.querySelector('[name="weapon"]').closest('fieldset').style.display).toBe('none');
});
it('never overwrites a pending choice', async () => {
    cleanup = mountCombat(root());
    document.querySelector('input').dispatchEvent(new Event('input', { bubbles: true }));
    await vi.advanceTimersByTimeAsync(5000);
    expect(document.querySelector('input')).not.toBeNull();
    expect(root().textContent).toContain('nicht überschrieben');
});
it('preserves keyboard focus and refreshes after leaving an unchanged form', async () => {
    cleanup = mountCombat(root());
    const input = document.querySelector('input');
    input.focus();
    await vi.advanceTimersByTimeAsync(5000);
    expect(document.activeElement).toBe(input);
    input.blur();
    await vi.advanceTimersByTimeAsync(5000);
    expect(root().textContent).toContain('Neue Entscheidung');
});
it('blocks duplicate form submissions and pauses polling', async () => {
    cleanup = mountCombat(root());
    const form = document.querySelector('form');
    const first = new Event('submit', { bubbles: true, cancelable: true });
    const second = new Event('submit', { bubbles: true, cancelable: true });
    form.dispatchEvent(first); form.dispatchEvent(second);
    expect(first.defaultPrevented).toBe(false);
    expect(second.defaultPrevented).toBe(true);
    expect(document.querySelector('button').disabled).toBe(false);
    await vi.advanceTimersByTimeAsync(10000);
    expect(fetch).not.toHaveBeenCalled();
});
it('revokes controls when access is lost', async () => {
    fetch.mockResolvedValue(response({}, 403));
    cleanup = mountCombat(root());
    await vi.advanceTimersByTimeAsync(5000);
    expect(document.querySelector('button').disabled).toBe(true);
    expect(root().textContent).toContain('Berechtigung');
});
it('ignores old revisions and cleans up when navigating', async () => {
    fetch.mockResolvedValue(response({ revision: 1, html: '<p>alt</p>' }));
    initCombats(); initCombats();
    await vi.advanceTimersByTimeAsync(5000);
    expect(fetch).toHaveBeenCalledTimes(1);
    expect(document.querySelector('input')).not.toBeNull();
    destroyCombats();
    await vi.advanceTimersByTimeAsync(10000);
    expect(fetch).toHaveBeenCalledTimes(1);
});
