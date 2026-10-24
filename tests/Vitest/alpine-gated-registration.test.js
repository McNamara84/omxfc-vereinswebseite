import { afterEach, expect, it, vi } from 'vitest';
afterEach(() => { delete window.Alpine; document.body.innerHTML = ''; });
it('leaves gated RPG roots untouched until their feature mount', async () => {
    vi.resetModules();
    document.body.innerHTML = '<div data-feature="char-editor" x-ignore><form x-data="charEditor()"></form></div><div data-feature="rpg-checks" x-ignore><form x-data="rpgCheckForm({})"></form><section x-data="rpgChecks({})"></section></div>';
    window.Alpine = { data: vi.fn(), initTree: vi.fn(), destroyTree: vi.fn(), $data: vi.fn() };
    await import('../../resources/js/alpine/char-editor');
    await import('../../resources/js/alpine/rpg-checks');
    expect(window.Alpine.data).toHaveBeenCalledTimes(3);
    expect(window.Alpine.initTree).not.toHaveBeenCalled();
    expect(window.Alpine.destroyTree).not.toHaveBeenCalled();
});
