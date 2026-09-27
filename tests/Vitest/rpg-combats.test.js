import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { mountCombat, initCombats, destroyCombats } from '../../resources/js/rpg-combats.js';

let cleanup;
const response = (data, status = 200) => ({ ok: status < 400, status, json: async () => data });
beforeEach(() => {
    vi.useFakeTimers();
    Object.defineProperty(document, 'hidden', { configurable: true, value: false });
    document.body.innerHTML = '<div data-rpg-combat data-url="/fight" data-revision="1"><p data-combat-notice></p><div data-combat-content><form><input name="move"><button>Würfeln</button></form></div></div>';
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(response({ revision: 2, html: '<p>Neue Entscheidung</p>' })));
});
afterEach(() => { cleanup?.(); cleanup = null; destroyCombats(); vi.useRealTimers(); vi.unstubAllGlobals(); });
const root = () => document.querySelector('[data-rpg-combat]');
it('updates new revisions automatically', async () => {
    cleanup = mountCombat(root());
    await vi.advanceTimersByTimeAsync(5000);
    expect(root().textContent).toContain('Neue Entscheidung');
    expect(fetch.mock.calls[0][1].cache).toBe('no-store');
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
