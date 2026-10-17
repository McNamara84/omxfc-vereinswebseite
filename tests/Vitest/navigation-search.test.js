import { navigationSpotlight } from '../../resources/js/navigation-search';

describe('navigation search dialog', () => {
    let search;
    beforeEach(() => {
        vi.useFakeTimers();
        document.body.innerHTML = '<button data-testid="navigation-search-trigger">Suche</button><dialog><input><ul></ul></dialog>';
        const dialog = document.querySelector('dialog');
        dialog.showModal = vi.fn(() => { dialog.open = true; });
        dialog.close = vi.fn(() => { dialog.open = false; });
        search = navigationSpotlight({ url: '/mary/spotlight', scope: 'member' });
        search.$refs = { dialog, input: dialog.querySelector('input'), results: dialog.querySelector('ul') };
        search.$nextTick = (callback) => callback();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => [{ name: 'Chronik', link: '/chronik' }] }));
    });
    afterEach(() => {
        search.destroy();
        vi.useRealTimers();
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('opens from a shortcut, focuses input and closes other navigation surfaces', () => {
        const opening = vi.fn();
        const drawer = vi.fn();
        window.addEventListener('navigation-search-opening', opening, { once: true });
        window.addEventListener('member-drawer-close', drawer, { once: true });
        const trigger = document.querySelector('button');
        trigger.focus();
        const event = new KeyboardEvent('keydown', { key: 'k', ctrlKey: true, cancelable: true });
        search.shortcut(event);
        expect(event.defaultPrevented).toBe(true);
        expect(opening).toHaveBeenCalledOnce();
        expect(drawer).toHaveBeenCalledOnce();
        expect(search.$refs.dialog.open).toBe(true);
        expect(document.activeElement).toBe(search.$refs.input);
    });

    it.each([{ key: 'k' }, { key: 'k', ctrlKey: true, shiftKey: true }, { key: 'k', metaKey: true, repeat: true }, { key: 'x', ctrlKey: true }])('leaves other keyboard commands alone: %j', (options) => {
        search.shortcut(new KeyboardEvent('keydown', options));
        expect(search.$refs.dialog.showModal).not.toHaveBeenCalled();
    });

    it('debounces queries, encodes special characters and clears results for short input', async () => {
        search.value = 'A & B?';
        search.schedule();
        await vi.advanceTimersByTimeAsync(249);
        expect(fetch).not.toHaveBeenCalled();
        await vi.advanceTimersByTimeAsync(1);
        expect(fetch.mock.calls[0][0]).toBe('/mary/spotlight?search=A+%26+B%3F&scope=member');
        expect(search.results).toHaveLength(1);
        expect(search.loading).toBe(false);
        search.value = 'a';
        search.schedule();
        await vi.runAllTimersAsync();
        expect(search.results).toEqual([]);
        expect(search.searched).toBe(false);
        expect(fetch).toHaveBeenCalledOnce();
    });

    it('discards an old response that arrives after a newer one', async () => {
        let resolveOld;
        fetch.mockImplementationOnce(() => new Promise(resolve => { resolveOld = resolve; }));
        search.value = 'alt';
        const old = search.search();
        search.value = 'neu';
        await search.search();
        resolveOld({ ok: true, json: async () => [{ name: 'Veraltet' }] });
        await old;
        expect(search.results[0].name).toBe('Chronik');
    });

    it.each([
        () => Promise.reject(new Error('offline')),
        () => Promise.resolve({ ok: false }),
        () => Promise.resolve({ ok: true, json: async () => ({ message: 'not an array' }) }),
        () => Promise.resolve({ ok: true, json: async () => { throw new Error('invalid json'); } }),
    ])('reports failures separately from no matches and clears loading', async (response) => {
        fetch.mockImplementationOnce(response);
        search.value = 'Test';
        await search.search();
        expect(search.error).toContain('nicht erreichbar');
        expect(search.results).toEqual([]);
        expect(search.loading).toBe(false);
        expect(search.searched).toBe(false);
    });

    it('announces an empty successful search without an error', async () => {
        fetch.mockResolvedValueOnce({ ok: true, json: async () => [] });
        search.value = 'Test';
        await search.search();
        expect(search.error).toBe('');
        expect(search.searched).toBe(true);
    });

    it('clears pending requests and results on close and ignores an aborted response', async () => {
        fetch.mockRejectedValueOnce(new DOMException('aborted', 'AbortError'));
        search.value = 'Test';
        const pending = search.search();
        search.close(false);
        await pending;
        expect(search.error).toBe('');
        expect(search.loading).toBe(false);
        expect(search.results).toEqual([]);
        expect(search.value).toBe('');
    });

    it('moves through result links in both directions', () => {
        search.show();
        search.$refs.results.innerHTML = '<li><a href="/eins">Eins</a></li><li><a href="/zwei">Zwei</a></li>';
        const links = search.$refs.results.querySelectorAll('a');
        search.$refs.input.focus();
        search.move(1);
        expect(document.activeElement).toBe(links[0]);
        search.move(1);
        expect(document.activeElement).toBe(links[1]);
        search.move(1);
        expect(document.activeElement).toBe(links[0]);
        search.move(-1);
        expect(document.activeElement).toBe(links[1]);
    });

    it('restores focus to an available search trigger and starts every opening empty', () => {
        const trigger = document.querySelector('button');
        search.show();
        search.value = 'privat';
        search.results = [{ name: 'privat' }];
        search.close();
        expect(document.activeElement).toBe(trigger);
        search.show();
        expect(search.value).toBe('');
        expect(search.results).toEqual([]);
    });

    it('settles input focus after a drawer releases its focus trap', async () => {
        search.show();
        document.querySelector('button').focus();
        await vi.advanceTimersByTimeAsync(100);
        expect(document.activeElement).toBe(search.$refs.input);
        expect(search.focusTimer).toBeNull();
    });

    it('keeps a single open dialog and does not refocus a dialog closed externally', async () => {
        const focus = vi.spyOn(search.$refs.input, 'focus');
        search.show();
        search.show();
        expect(search.$refs.dialog.showModal).toHaveBeenCalledOnce();
        const calls = focus.mock.calls.length;
        search.$refs.dialog.close();
        await vi.advanceTimersByTimeAsync(100);
        expect(focus).toHaveBeenCalledTimes(calls);
    });

    it('restores a connected visible opener rather than the fallback trigger', () => {
        const opener = document.createElement('button');
        document.body.prepend(opener);
        opener.getClientRects = () => [{}];
        opener.focus();
        search.show();
        search.close();
        expect(document.activeElement).toBe(opener);
    });

    it.each(['hidden', 'collapse'])('falls back when the opener has rectangles but visibility is %s', (visibility) => {
        const opener = document.createElement('button');
        document.body.prepend(opener);
        opener.getClientRects = () => [{}];
        opener.focus();
        search.show();
        opener.style.visibility = visibility;

        search.close();

        expect(document.activeElement).toBe(document.querySelector('[data-testid="navigation-search-trigger"]'));
    });

    it('falls back when the visible opener becomes disabled and cannot receive focus', () => {
        const opener = document.createElement('button');
        document.body.prepend(opener);
        opener.getClientRects = () => [{}];
        opener.focus();
        search.show();
        opener.disabled = true;

        search.close();

        expect(document.activeElement).toBe(document.querySelector('[data-testid="navigation-search-trigger"]'));
    });

    it('falls back when the opener was removed while search was open', () => {
        const opener = document.createElement('button');
        document.body.prepend(opener);
        opener.focus();
        search.show();
        opener.remove();

        search.close();

        expect(document.activeElement).toBe(document.querySelector('[data-testid="navigation-search-trigger"]'));
    });

    it('ignores a direct short query and arrow navigation without results', async () => {
        search.value = 'x';
        await search.search();
        search.move(-1);
        expect(fetch).not.toHaveBeenCalled();
    });

    it('moves backwards from the input to the final result', () => {
        search.show();
        search.$refs.results.innerHTML = '<li><a href="/eins">Eins</a></li><li><a href="/zwei">Zwei</a></li>';
        search.$refs.input.focus();
        search.move(-1);
        expect(document.activeElement.textContent).toBe('Zwei');
    });

});
