import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * Tests für die hoerbuch-role-repeater Alpine-Komponente.
 *
 * hoerbuch-role-repeater.js registriert Alpine.data() im 'alpine:init'-Event.
 * Wir mocken window.Alpine.data() und dispatchen das Event nach dem Import.
 */

let repeaterFactory;

beforeEach(async () => {
    window.Alpine = {
        data: vi.fn((name, factory) => {
            if (name === 'hoerbuchRoleRepeater') {
                repeaterFactory = factory;
            }
        }),
    };

    vi.resetModules();
    await import('@/alpine/hoerbuch-role-repeater.js');
    document.dispatchEvent(new CustomEvent('alpine:init'));
});

function createRepeater(overrides = {}) {
    const defaults = { initialRoles: [], members: [], previousSpeakerUrl: '' };
    const instance = repeaterFactory({ ...defaults, ...overrides });
    return instance;
}

describe('hoerbuchRoleRepeater – addRole / removeRole', () => {
    it('addRole fügt eine leere Rolle hinzu', () => {
        const r = createRepeater();
        expect(r.roles).toHaveLength(0);

        r.addRole();
        expect(r.roles).toHaveLength(1);
        expect(r.roles[0]).toMatchObject({
            _key: 0,
            name: '',
            description: '',
            takes: 0,
            contact_email: '',
            speaker_pseudonym: '',
            member_name: '',
            member_id: '',
            uploaded: false,
            previousSpeaker: '',
        });
    });

    it('addRole inkrementiert nextKey', () => {
        const r = createRepeater();
        r.addRole();
        r.addRole();
        expect(r.roles[0]._key).toBe(0);
        expect(r.roles[1]._key).toBe(1);
        expect(r.nextKey).toBe(2);
    });

    it('nextKey startet bei initialRoles.length', () => {
        const r = createRepeater({
            initialRoles: [{ _key: 0, name: 'Rolle 1' }, { _key: 1, name: 'Rolle 2' }],
        });
        expect(r.nextKey).toBe(2);
        r.addRole();
        expect(r.roles[2]._key).toBe(2);
    });

    it('removeRole entfernt Rolle am Index', () => {
        const r = createRepeater();
        r.addRole();
        r.addRole();
        r.addRole();
        expect(r.roles).toHaveLength(3);

        r.removeRole(1);
        expect(r.roles).toHaveLength(2);
        expect(r.roles[0]._key).toBe(0);
        expect(r.roles[1]._key).toBe(2);
    });
});

describe('hoerbuchRoleRepeater – lookupMemberId', () => {
    it('setzt member_id wenn Name übereinstimmt', () => {
        const r = createRepeater({
            members: [
                { id: 42, name: 'Max Mustermann' },
                { id: 7, name: 'Erika Muster' },
            ],
        });
        const role = { member_name: 'Erika Muster', member_id: '' };
        r.lookupMemberId(role);
        expect(role.member_id).toBe('7');
    });

    it('leert member_id wenn Name nicht gefunden', () => {
        const r = createRepeater({
            members: [{ id: 42, name: 'Max Mustermann' }],
        });
        const role = { member_name: 'Unbekannt', member_id: '42' };
        r.lookupMemberId(role);
        expect(role.member_id).toBe('');
    });

    it('member_id ist immer ein String', () => {
        const r = createRepeater({
            members: [{ id: 123, name: 'Test' }],
        });
        const role = { member_name: 'Test', member_id: '' };
        r.lookupMemberId(role);
        expect(typeof role.member_id).toBe('string');
        expect(role.member_id).toBe('123');
    });
});

describe('hoerbuchRoleRepeater – fetchPreviousSpeaker', () => {
    it('leert previousSpeaker wenn name leer', async () => {
        const r = createRepeater({ previousSpeakerUrl: '/api/speaker' });
        const role = { name: '', previousSpeaker: 'alt' };
        await r.fetchPreviousSpeaker(role);
        expect(role.previousSpeaker).toBe('');
    });

    it('leert previousSpeaker wenn previousSpeakerUrl leer', async () => {
        const r = createRepeater({ previousSpeakerUrl: '' });
        const role = { name: 'Test', previousSpeaker: 'alt' };
        await r.fetchPreviousSpeaker(role);
        expect(role.previousSpeaker).toBe('');
    });

    it('setzt previousSpeaker bei erfolgreicher Abfrage', async () => {
        global.fetch = vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            json: () => Promise.resolve({ speaker: 'Max Mustermann' }),
        });

        const r = createRepeater({ previousSpeakerUrl: '/api/speaker' });
        const role = { name: 'Erzähler', previousSpeaker: '' };
        await r.fetchPreviousSpeaker(role);
        expect(role.previousSpeaker).toBe('Bisheriger Sprecher: Max Mustermann');
    });

    it('leert previousSpeaker wenn kein bisheriger Sprecher', async () => {
        global.fetch = vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            json: () => Promise.resolve({ speaker: null }),
        });

        const r = createRepeater({ previousSpeakerUrl: '/api/speaker' });
        const role = { name: 'Erzähler', previousSpeaker: 'alt' };
        await r.fetchPreviousSpeaker(role);
        expect(role.previousSpeaker).toBe('');
    });

    it('zeigt Fehlermeldung bei 401', async () => {
        global.fetch = vi.fn().mockResolvedValue({
            ok: false,
            status: 401,
        });

        const r = createRepeater({ previousSpeakerUrl: '/api/speaker' });
        const role = { name: 'Erzähler', previousSpeaker: '' };
        await r.fetchPreviousSpeaker(role);
        expect(role.previousSpeaker).toBe('Nicht berechtigt');
    });

    it('zeigt Fehlermeldung bei Netzwerkfehler', async () => {
        global.fetch = vi.fn().mockRejectedValue(new TypeError('Network error'));

        const r = createRepeater({ previousSpeakerUrl: '/api/speaker' });
        const role = { name: 'Erzähler', previousSpeaker: '' };
        await r.fetchPreviousSpeaker(role);
        expect(role.previousSpeaker).toBe('Fehler beim Laden des bisherigen Sprechers');
    });

    it('ignoriert AbortError ohne Fehlermeldung', async () => {
        const abortError = new DOMException('Aborted', 'AbortError');
        global.fetch = vi.fn().mockRejectedValue(abortError);

        const r = createRepeater({ previousSpeakerUrl: '/api/speaker' });
        const role = { name: 'Erzähler', previousSpeaker: 'vorher' };
        await r.fetchPreviousSpeaker(role);
        // previousSpeaker bleibt unverändert bei AbortError
        expect(role.previousSpeaker).toBe('vorher');
    });

    it('bricht vorherige Anfrage ab bei erneutem Aufruf', async () => {
        const abortFn = vi.fn();
        let callCount = 0;

        global.fetch = vi.fn().mockImplementation((url, opts) => {
            callCount++;
            // Erster Aufruf wird aborted, zweiter succeeds
            if (callCount === 1) {
                opts.signal.addEventListener('abort', abortFn);
                return new Promise(() => {}); // Never resolves
            }
            return Promise.resolve({
                ok: true,
                status: 200,
                json: () => Promise.resolve({ speaker: 'Neu' }),
            });
        });

        const r = createRepeater({ previousSpeakerUrl: '/api/speaker' });
        const role = { name: 'Erzähler', previousSpeaker: '' };

        // Erster Aufruf (wird nie aufgelöst)
        const first = r.fetchPreviousSpeaker(role);
        // Zweiter Aufruf bricht den ersten ab
        const second = r.fetchPreviousSpeaker(role);

        await second;
        expect(abortFn).toHaveBeenCalled();
        expect(role.previousSpeaker).toBe('Bisheriger Sprecher: Neu');
    });
});
