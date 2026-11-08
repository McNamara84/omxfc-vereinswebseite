import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { rpgItems, registerRpgItems } from '../../resources/js/alpine/rpg-items';
import { rpgInventory } from '../../resources/js/alpine/rpg-inventory';

const common = ['name', 'description', 'category', 'size', 'rarity', 'beautiful', 'material', 'high_tech', 'tech_factor', 'utility', 'bucks', 'reference', 'minimum_education'];
const ranged = ['hands', 'weapon_type', 'precision', 'fire_rate', 'range_increment', 'max_range', 'magazine', 'ammunition_id', 'ammunition_name', 'ammunition_unit'];
const config = () => ({ common, input: {}, fields: { low_tech: [], high_tech: [], armor: ['effectiveness', 'movement_modifier'],
    melee_weapons: [...ranged, 'masterwork', 'attributes', 'throwable'], ranged_weapons: ranged },
revision: 0, submissionKey: 'key', previewUrl: '/preview', saveUrl: '/save' });
function component() {
    const form = rpgItems(config());
    form.$root = { querySelector: () => ({ value: 'csrf' }) };
    form.$nextTick = (callback) => callback(); form.$refs = { error: { focus: vi.fn() } };
    return form;
}

describe('item designer', () => {
    beforeEach(() => vi.stubGlobal('fetch', vi.fn()));
    afterEach(() => vi.unstubAllGlobals());
    it('sends only category fields and preserves entered values on category changes', () => {
        const form = component(); form.input.name = 'Klinge'; form.input.category = 'melee_weapons';
        expect(form.payload().input).not.toHaveProperty('precision');
        expect(form.payload().input).toHaveProperty('attributes', ['st']);
        form.input.category = 'armor'; form.categoryChanged();
        expect(form.payload().input).not.toHaveProperty('hands');
        expect(form.input.name).toBe('Klinge');
    });
    it('normalizes type-specific education and throwing profiles', () => {
        const form = component(); form.input.category = 'ranged_weapons'; form.input.weapon_type = 'Energiewaffe';
        expect(form.payload().input.minimum_education).toBe(3);
        form.input.weapon_type = 'Schießpulverwaffe'; expect(form.payload().input.minimum_education).toBe(2);
        form.input.category = 'melee_weapons'; form.input.throwable = true;
        expect(form.payload().input).toMatchObject({ weapon_type: 'Wurfgeschoss', magazine: 0, fire_rate: 'E' });
    });
    it('never submits a technology factor on a low-tech object', () => {
        const form = component(); expect(form.payload().input.tech_factor).toBe(1);
        form.input.category = 'high_tech'; form.categoryChanged(); expect(form.payload().input.tech_factor).toBe(5);
    });
    it('reopens a saved low-tech version without an invalid hidden technology control', () => {
        const form = rpgItems({ ...config(), input: { tech_factor: 1 } });
        expect(form.input.tech_factor).toBe(5); expect(form.payload().input.tech_factor).toBe(1);
        expect(form.has('hands')).toBe(false); form.input.category = 'melee_weapons'; expect(form.has('hands')).toBe(true);
        form.input.category = 'unknown'; expect(form.fields()).toEqual(common);
    });
    it.each([[undefined, 5], [null, 5], [1, 5], [5, 5], ['12', 12], [1000, 1000]])('normalizes a saved technology factor %s to %s', (techFactor, expected) => {
        const form = rpgItems({ ...config(), input: { tech_factor: techFactor } });
        expect(form.input.tech_factor).toBe(expected);
        expect(form.payload().input.tech_factor).toBe(1);
        form.input.category = 'high_tech'; form.categoryChanged();
        expect(form.payload().input.tech_factor).toBe(expected);
    });
    it('invalidates a checked preview after an edit', () => {
        const form = component(); form.result = { preview_hash: 'checked' }; form.invalidate();
        expect(form.result).toBeNull(); expect(form.payload().preview_hash).toBeNull();
    });
    it('ignores a preview that arrives after the form changed', async () => {
        let resolve; fetch.mockImplementation(() => new Promise((callback) => { resolve = callback; }));
        const form = component(); const pending = form.preview(); form.input.name = 'changed'; form.invalidate();
        resolve({ ok: true, json: async () => ({ preview_hash: 'stale' }) }); await pending;
        expect(form.result).toBeNull(); expect(form.busy).toBe(false);
    });
    it('retains inputs and focuses the server error', async () => {
        fetch.mockResolvedValue({ ok: false, json: async () => ({ errors: { input: ['Ungültige Größe'] } }) });
        const form = component(); form.input.name = 'Klinge'; await form.preview();
        expect(form.error).toBe('Ungültige Größe'); expect(form.input.name).toBe('Klinge'); expect(form.$refs.error.focus).toHaveBeenCalled();
    });
    it('requires a current preview before saving and blocks duplicate clicks', async () => {
        const form = component(); await form.save(); expect(fetch).not.toHaveBeenCalled();
        form.busy = true; await form.preview(); expect(fetch).not.toHaveBeenCalled();
    });
    it('sends the preview hash and retains the form after a save conflict', async () => {
        fetch.mockResolvedValue({ ok: false, json: async () => ({ errors: { item: ['Vorschau veraltet'] } }) });
        const form = component(); form.result = { preview_hash: 'hash' }; await form.save();
        expect(JSON.parse(fetch.mock.calls[0][1].body).preview_hash).toBe('hash'); expect(form.result).toBeNull(); expect(form.error).toBe('Vorschau veraltet');
    });
    it('accepts a current preview and navigates to the saved object', async () => {
        const assign = vi.fn(); vi.stubGlobal('window', { location: { assign } });
        fetch.mockResolvedValueOnce({ ok: true, json: async () => ({ preview_hash: 'hash', definition: { name: 'Klinge' } }) });
        const form = component(); await form.preview(); expect(form.result.preview_hash).toBe('hash');
        fetch.mockResolvedValueOnce({ ok: true, json: async () => ({ redirect: '/rpg/gegenstaende/1' }) });
        await form.save(); expect(assign).toHaveBeenCalledWith('/rpg/gegenstaende/1'); expect(form.busy).toBe(false);
    });
    it('reports network and non-validation server failures', async () => {
        const form = component(); fetch.mockRejectedValueOnce(new Error('Offline')); await form.preview(); expect(form.error).toBe('Offline');
        fetch.mockResolvedValueOnce({ ok: false, json: async () => ({}) }); await form.preview(); expect(form.error).toContain('Bitte neu laden');
    });
    it('registers only when Alpine exists', () => {
        registerRpgItems(); window.Alpine = { data: vi.fn() }; registerRpgItems();
        expect(window.Alpine.data).toHaveBeenCalledWith('rpgItems', rpgItems); delete window.Alpine;
    });
    it('submits explicit version changes without money or extra stock movements', () => {
        const form = rpgInventory({ state: { confirmed: true }, today: '2026-11-08', peers: [], catalog: {} });
        form.kind = 'item_version_change'; form.resetKind(); form.versionChange = { source: 'item:old', target: 'item:new', quantity: '2' };
        expect(form.payload()).toMatchObject({ amount: null, incoming: [], outgoing: [], version_change: { source: 'item:old', target: 'item:new', quantity: 2 } });
    });
});
