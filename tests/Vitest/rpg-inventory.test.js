import { afterEach, describe, expect, it, vi } from 'vitest';
import { registerRpgInventory, rpgInventory } from '../../resources/js/alpine/rpg-inventory';

function editor(confirmed = true) {
    const component = rpgInventory({ state: { confirmed, stocks: { 'item:bogen': 1, 'ammo:pfeile': 23 }, bucks: confirmed ? 100 : null,
        clothing: '', armor: '', shield: '', notes: 'Mein Bogen' },
    characterId: 1, revision: 4, submissionKey: 'unique-key', catalogVersion: 'rules', today: '2026-11-07',
    previewUrl: '/preview', saveUrl: '/save', peers: [{ id: 2, revision: 7, name: 'Anderer Charakter' }],
    catalog: { 'item:bogen': { name: 'Bogen', category: 'ranged_weapons' }, 'clothing:kleidung-einfach': { name: 'Einfache Kleidung', kind: 'clothing' } } });
    component.$root = document.createElement('div');
    component.$root.innerHTML = '<input name="_token" value="test-token">';
    component.$refs = { error: { focus: vi.fn() }, reason: { focus: vi.fn() } };
    component.$nextTick = (callback) => callback();
    component.init();
    return component;
}

afterEach(() => { vi.restoreAllMocks(); vi.unstubAllGlobals(); });

describe('inventory form', () => {
    it('requires an explicit takeover of unknown money and ammunition', () => {
        const form = editor(false);
        expect(form.kind).toBe('initialize');
        expect(form.amount).toBe(0);
        expect(form.payload().set).toEqual([{ id: 'item:bogen', quantity: 1 }, { id: 'ammo:pfeile', quantity: 23 }]);
    });

    it('sends only explicit quantities and prices and both character revisions', () => {
        const form = editor(); form.kind = 'purchase'; form.resetKind();
        form.addRow('incoming'); form.incoming[0].id = 'item:bogen'; form.incoming[0].quantity = '2';
        form.amount = '60'; form.externalParty = 'Zuvor ausgewählter Händler'; form.otherId = '2';
        expect(form.payload()).toMatchObject({ amount: 60, incoming: [{ id: 'item:bogen', quantity: 2 }],
            revision: 4, other_character_id: 2, other_revision: 7, external_party: '', metadata: {} });
        expect(form.payload().incoming[0]).not.toHaveProperty('uid');
    });

    it('retains stock and notes when preparing an equipment change', () => {
        const form = editor(); form.kind = 'equip'; form.resetKind();
        expect(form.metadata.notes).toBe('Mein Bogen');
        expect(form.incoming).toEqual([]);
        expect(form.payload().amount).toBeNull();
    });

    it('clears previous trade and preview data when switching operations', () => {
        const form = editor(); form.kind = 'purchase'; expect(form.isTrade()).toBe(true);
        form.otherId = '2'; form.externalParty = 'Händler'; form.priceReason = 'Rabatt';
        form.previewResult = { preview_hash: 'old' }; form.kind = 'correct'; form.resetKind();
        expect(form.previewResult).toBeNull(); expect(form.otherId).toBe(''); expect(form.priceReason).toBe('');
        expect(form.amount).toBe(100); expect(form.setRows).toHaveLength(2);
        expect(form.isTrade()).toBe(false);
    });

    it('filters catalogue names and categories while retaining selected stock', () => {
        const form = editor(); form.catalogSearch = ' kleidung ';
        expect(form.catalogEntries('')).toHaveLength(1);
        expect(form.catalogEntries('item:bogen')).toHaveLength(2);
        form.catalogSearch = ''; form.catalogCategory = 'ranged_weapons';
        expect(form.catalogEntries('')).toEqual([['item:bogen', form.config.catalog['item:bogen']]]);
        form.catalogCategory = 'clothing'; expect(form.catalogEntries('')[0][0]).toBe('clothing:kleidung-einfach');
    });

    it('prepares a reversal with the counterparty revision', () => {
        const form = editor(); form.beginReversal(9, 2);
        expect(form.payload()).toMatchObject({ kind: 'reversal', reversal_of: 9, other_revision: 7, amount: null, incoming: [], outgoing: [] });
        expect(form.$refs.reason.focus).toHaveBeenCalledOnce();
    });

    it('prepares a single-character reversal without inventing a counterparty', () => {
        const form = editor(); form.beginReversal(9, null);
        expect(form.payload().other_character_id).toBeNull();
    });

    it('invalidates a confirmed preview when inputs change', () => {
        const form = editor(); form.previewResult = { preview_hash: 'old' }; form.invalidate();
        expect(form.payload().preview_hash).toBeNull();
    });

    it('displays character and catalogue names without markup', () => {
        const form = editor();
        expect(form.label('1')).toBe('Dieser Charakter'); expect(form.label('2')).toBe('Anderer Charakter');
        expect(form.label('99')).toBe('Gegenpartei');
        expect([form.display(null), form.display(true), form.display(false), form.display(''), form.display('bogen'), form.display('kleidung-einfach'), form.display(23)])
            .toEqual(['Unbekannt', 'Ja', 'Nein', 'Keine', 'Bogen', 'Einfache Kleidung', '23']);
    });
});

describe('preview and booking requests', () => {
    it('sends CSRF and uses the authoritative preview for saving', async () => {
        const form = editor(); const fetch = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ preview_hash: 'server-hash', entries: {} }) });
        vi.stubGlobal('fetch', fetch); await form.preview();
        expect(form.previewResult.preview_hash).toBe('server-hash'); expect(form.busy).toBe(false);
        expect(fetch.mock.calls[0][1].headers['X-CSRF-TOKEN']).toBe('test-token');
        const assign = vi.fn(); vi.stubGlobal('window', { location: { assign } });
        fetch.mockResolvedValue({ ok: true, json: async () => ({ redirect: '/inventory' }) });
        await form.save();
        expect(JSON.parse(fetch.mock.calls[1][1].body).preview_hash).toBe('server-hash');
        expect(assign).toHaveBeenCalledWith('/inventory');
    });

    it('discards a preview response when the input changed while loading', async () => {
        const form = editor(); let resolve;
        vi.stubGlobal('fetch', vi.fn(() => new Promise((done) => { resolve = done; })));
        const pending = form.preview(); form.invalidate();
        resolve({ ok: true, json: async () => ({ preview_hash: 'obsolete' }) }); await pending;
        expect(form.previewResult).toBeNull(); expect(form.busy).toBe(false);
    });

    it('blocks concurrent requests and saving without a preview', async () => {
        const form = editor(); const fetch = vi.fn(); vi.stubGlobal('fetch', fetch);
        await form.save(); form.busy = true; await form.preview(); await form.save();
        expect(fetch).not.toHaveBeenCalled();
    });

    it('shows validation failures and clears an invalid booking preview', async () => {
        const form = editor(); form.previewResult = { preview_hash: 'old' };
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, json: async () => ({ errors: { inventory: ['Bestand geändert.'] } }) }));
        await form.save(); expect(form.error).toBe('Bestand geändert.');
        expect(form.previewResult).toBeNull(); expect(form.busy).toBe(false); expect(form.$refs.error.focus).toHaveBeenCalledOnce();
    });

    it('handles a non-JSON server error and allows retrying', async () => {
        const form = editor();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, json: async () => { throw new Error('invalid JSON'); } }));
        await form.preview(); expect(form.error).toContain('fehlgeschlagen'); expect(form.busy).toBe(false);
    });

    it('focuses the error when the preview request loses its connection', async () => {
        const form = editor(); vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('Verbindung unterbrochen')));
        await form.preview(); expect(form.error).toBe('Verbindung unterbrochen');
        expect(form.$refs.error.focus).toHaveBeenCalledOnce();
    });

    it('registers the lazy Alpine component', () => {
        const data = vi.fn(); vi.stubGlobal('window', { Alpine: { data } }); registerRpgInventory();
        expect(data).toHaveBeenCalledWith('rpgInventory', rpgInventory);
        vi.stubGlobal('window', {}); expect(() => registerRpgInventory()).not.toThrow();
    });
});
