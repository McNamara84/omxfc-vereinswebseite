import { afterEach, describe, expect, it, vi } from 'vitest';
import { buildOperation, rpgProgression } from '../../resources/js/alpine/rpg-progression.js';

const config = {
    mode: 'improve', submission_key: 'test-key', revision: 2,
    previewUrl: '/preview', saveUrl: '/save', languages: ['Englisch'],
    names: { skill: ['Nahkampf'], advantage: ['Panzerung'] },
};
const component = (overrides = {}) => {
    const value = rpgProgression({ ...config, ...overrides });
    value.$root = document.createElement('form');
    value.$root.innerHTML = '<input name="_token" value="csrf-test"><button>Speichern</button>';
    value.$el = value.$root.querySelector('button');
    value.$refs = { error: { focus: vi.fn() } };
    value.$nextTick = (callback) => callback();
    value.init();
    return value;
};

afterEach(() => vi.unstubAllGlobals());

describe('RPG progression forms', () => {
    it('sends intended operations without preview prices or UI fields', () => {
        const value = component();
        value.operations[0].reason = 'Training';
        expect(value.payload()).toEqual({
            submission_key: 'test-key', revision: 2,
            operations: [{ type: 'skill', name: 'Nahkampf', steps: 1, reason: 'Training', target: '' }],
        });
        expect(value.payload()).not.toHaveProperty('cost');
    });

    it('transmits language choices, specializations and equipment only for relevant purchases', () => {
        const value = component();
        const operation = value.operations[0];
        operation.name = 'Sprachen';
        operation.languageText = 'Englisch\n Französisch \n';
        expect(buildOperation(operation).languages).toEqual(['Englisch', 'Französisch']);
        operation.name = 'Kunde';
        operation.specialization = 'Wetter';
        expect(buildOperation(operation).name).toBe('Kunde: Wetter');
        expect(buildOperation(operation)).not.toHaveProperty('languages');
        value.changeType(Object.assign(operation, { type: 'advantage' }));
        expect(operation.steps).toBe(1);
        expect(operation.specialization).toBe('');
        operation.name = 'High-Tech-Ausrüstung';
        operation.items = ['a', 'b', 'c', 'd'];
        expect(buildOperation(operation).items).toHaveLength(4);
    });

    it('prevents duplicate participants and converts hours and explicit zero overrides', () => {
        const value = component({ mode: 'award', today: '2026-09-26', characters: [{ id: 1, name: 'Arkon' }] });
        value.selectedCharacter = '1';
        value.addParticipant();
        value.selectedCharacter = '1';
        value.addParticipant();
        expect(value.participants).toHaveLength(1);
        value.hours = 5;
        value.minutes = 59;
        expect(value.payload().minutes).toBe(359);
        expect(value.payload().participants[0].points).toBeNull();
        value.participants[0].points = '0';
        expect(value.payload().participants[0].points).toBe(0);
        expect(value.payload().participants[0]).not.toHaveProperty('label');
    });

    it('uses server validation and announces errors accessibly', async () => {
        const value = component();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, json: async () => ({ errors: { operations: ['Nicht genug EP'] } }) }));
        await value.preview();
        expect(value.error).toBe('Nicht genug EP');
        expect(value.$refs.error.focus).toHaveBeenCalled();
        expect(value.busy).toBe(false);
        expect(fetch.mock.calls[0][1].headers['X-CSRF-TOKEN']).toBe('csrf-test');
    });

    it('discards a response when input changed while the preview was in flight', async () => {
        const value = component();
        let resolve;
        vi.stubGlobal('fetch', vi.fn(() => new Promise((done) => { resolve = done; })));
        const pending = value.preview();
        value.invalidate();
        resolve({ ok: true, json: async () => ({ cost: 6 }) });
        await pending;
        expect(value.previewResult).toBeNull();
        expect(value.busy).toBe(false);
    });

    it('cannot save without preview and prevents duplicate in-flight requests', async () => {
        const value = component();
        vi.stubGlobal('fetch', vi.fn());
        await value.save();
        value.busy = true;
        await value.preview();
        expect(fetch).not.toHaveBeenCalled();
    });

    it('shows a useful error for expired sessions and network failures', async () => {
        const value = component();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, json: async () => { throw new Error('HTML'); } }));
        await value.preview();
        expect(value.error).toContain('Seite neu laden');
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('Netzwerk nicht verfügbar')));
        await value.preview();
        expect(value.error).toContain('Netzwerk');
    });

    it('saves from a child button using the form token and redirects only after success', async () => {
        const value = component();
        const assign = vi.fn();
        vi.stubGlobal('window', { location: { assign } });
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => ({ redirect: '/requests/1' }) }));
        value.previewResult = { cost: 6 };
        await value.save();
        expect(fetch.mock.calls[0][0]).toBe('/save');
        expect(fetch.mock.calls[0][1].headers['X-CSRF-TOKEN']).toBe('csrf-test');
        expect(assign).toHaveBeenCalledWith('/requests/1');
    });

    it('keeps the form usable when saving fails', async () => {
        const value = component();
        value.previewResult = { cost: 6 };
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, json: async () => ({ errors: { revision: ['Charakter wurde geändert'] } }) }));
        await value.save();
        expect(value.error).toContain('Charakter wurde geändert');
        expect(value.busy).toBe(false);
        expect(value.$refs.error.focus).toHaveBeenCalled();
    });

    it('accepts the current preview and limits the operation count', async () => {
        const value = component();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => ({ cost: 6 }) }));
        await value.preview();
        expect(value.previewResult).toEqual({ cost: 6 });
        for (let index = 0; index < 40; index++) value.addOperation();
        expect(value.operations).toHaveLength(30);
        expect(value.previewResult).toBeNull();
    });
});
