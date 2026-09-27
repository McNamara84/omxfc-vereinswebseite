import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createCheckPoller } from '../../resources/js/rpg-check-polling.js';
import { rpgCheckForm, rpgChecks } from '../../resources/js/alpine/rpg-checks.js';

const disposables = [];
const response = (data, status = 200) => ({ ok: status < 400, status, json: async () => data });
const deferred = () => { let resolve; const promise = new Promise((r) => { resolve = r; }); return { promise, resolve }; };
const config = {
    submission_key: 'key', preview_url: '/preview', save_url: '/save', skills: ['Nahkampf', 'Heimlichkeit'],
    characters: [{ id: 1, label: 'Arkon', skills: ['Nahkampf', 'Heimlichkeit', 'Kunde: Wetter'] }, { id: 2, label: 'Birka', skills: ['Nahkampf', 'Heimlichkeit'] }],
};
function form() {
    const value = rpgCheckForm(config);
    value.$root = document.createElement('div');
    value.$root.innerHTML = '<input name="_token" value="test-token">';
    value.selectedCharacter = '1'; value.addParticipant();
    return value;
}
function poll(options = {}) {
    const value = createCheckPoller({ url: '/checks', onData: vi.fn(), onError: vi.fn(), ...options });
    disposables.push(value);
    return value;
}
beforeEach(() => {
    vi.useFakeTimers();
    Object.defineProperty(document, 'hidden', { configurable: true, value: false });
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(response({ checks: [] })));
});
afterEach(() => {
    disposables.forEach((item) => item.destroy());
    disposables.length = 0;
    vi.useRealTimers();
    vi.unstubAllGlobals();
});

describe('automatic check updates', () => {
    it('polls every five seconds and never overlaps requests', async () => {
        const pending = deferred();
        fetch.mockReturnValue(pending.promise);
        const onData = vi.fn();
        const value = poll({ onData });
        await vi.advanceTimersByTimeAsync(5000);
        expect(fetch).toHaveBeenCalledTimes(1);
        await value.refresh();
        await vi.advanceTimersByTimeAsync(20000);
        expect(fetch).toHaveBeenCalledTimes(1);
        pending.resolve(response({ checks: [1] }));
        await vi.advanceTimersByTimeAsync(0);
        expect(onData).toHaveBeenCalledWith({ checks: [1] });
        await vi.advanceTimersByTimeAsync(5000);
        expect(fetch).toHaveBeenCalledTimes(2);
    });

    it('pauses hidden tabs and immediately resumes when visible', async () => {
        poll();
        Object.defineProperty(document, 'hidden', { value: true });
        document.dispatchEvent(new Event('visibilitychange'));
        await vi.advanceTimersByTimeAsync(20000);
        expect(fetch).not.toHaveBeenCalled();
        Object.defineProperty(document, 'hidden', { value: false });
        document.dispatchEvent(new Event('visibilitychange'));
        await vi.advanceTimersByTimeAsync(0);
        expect(fetch).toHaveBeenCalledTimes(1);
    });

    it('aborts obsolete responses before a mutation and schedules a new poll', async () => {
        const pending = deferred();
        fetch.mockReturnValueOnce(pending.promise);
        const onData = vi.fn();
        const value = poll({ onData });
        void value.refresh();
        const signal = fetch.mock.calls[0][1].signal;
        value.pause();
        expect(signal.aborted).toBe(true);
        pending.resolve(response({ stale: true }));
        await vi.advanceTimersByTimeAsync(0);
        expect(onData).not.toHaveBeenCalled();
        value.resume();
        await vi.advanceTimersByTimeAsync(5000);
        expect(onData).toHaveBeenCalledWith({ checks: [] });
    });

    it('cleans timers, requests and listeners on navigation', async () => {
        const pending = deferred(); fetch.mockReturnValue(pending.promise);
        const onData = vi.fn(); const value = poll({ onData });
        void value.refresh();
        value.destroy();
        pending.resolve(response({ late: true }));
        document.dispatchEvent(new Event('visibilitychange'));
        await vi.advanceTimersByTimeAsync(15000);
        expect(fetch).toHaveBeenCalledTimes(1);
        expect(onData).not.toHaveBeenCalled();
    });

    it('discards a response whose body finishes loading after navigation', async () => {
        const body = deferred();
        fetch.mockResolvedValue({ ok: true, status: 200, json: () => body.promise });
        const onData = vi.fn();
        const value = poll({ onData });
        const request = value.refresh();
        await vi.advanceTimersByTimeAsync(0);
        value.destroy();
        body.resolve({ checks: ['obsolete'] });
        await request;
        expect(onData).not.toHaveBeenCalled();
    });

    it('does not report an intentional fetch abort as a connection failure', async () => {
        fetch.mockImplementation((_url, { signal }) => new Promise((_resolve, reject) => {
            signal.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')));
        }));
        const onError = vi.fn();
        const value = poll({ onError });
        const request = value.refresh();
        value.pause();
        await request;
        expect(onError).not.toHaveBeenCalled();
    });

    it.each([401, 403, 419])('stops on authentication or authorization status %s', async (status) => {
        fetch.mockResolvedValue(response({}, status));
        const onError = vi.fn(); poll({ onError });
        await vi.advanceTimersByTimeAsync(15000);
        expect(fetch).toHaveBeenCalledTimes(1);
        expect(onError).toHaveBeenCalledWith(expect.any(String), true);
    });

    it('recovers from server and network failures', async () => {
        fetch.mockResolvedValueOnce(response({}, 500)).mockRejectedValueOnce(new Error('offline')).mockResolvedValue(response({ checks: [1] }));
        const onError = vi.fn(); const onData = vi.fn(); poll({ onError, onData });
        await vi.advanceTimersByTimeAsync(15000);
        expect(onError).toHaveBeenCalledTimes(2);
        expect(onData).toHaveBeenCalledWith({ checks: [1] });
    });
});

describe('check request form', () => {
    it('deduplicates characters and uses shared settings without UI or result fields', () => {
        const value = form();
        value.selectedCharacter = '1'; value.addParticipant();
        value.selectedCharacter = '2'; value.addParticipant();
        value.common.check_type = 'skill'; value.common.skill_name = 'Nahkampf';
        value.addModifier(value.common);
        Object.assign(value.common.modifiers[0], { value: '-2', description: 'Dunkelheit' });
        const input = value.payload();
        expect(input.participants).toHaveLength(2);
        expect(input.participants[0]).toEqual({
            character_id: 1, check_type: 'skill', attribute_key: 'wa', skill_name: 'Nahkampf',
            modifiers: [{ value: -2, description: 'Dunkelheit' }],
        });
        expect(value.availableSkills(value.common)).toEqual(['Nahkampf', 'Heimlichkeit']);
        expect(value.specifications()).toEqual([value.common]);
    });

    it('opposed checks have two independent specifications and no difficulty', () => {
        const value = form(); value.mode = 'opposed'; value.changeMode();
        value.selectedCharacter = '1'; value.addParticipant();
        value.selectedCharacter = '2'; value.addParticipant();
        value.participants[0].settings.attribute_key = 'st';
        value.participants[1].settings.attribute_key = 'ge';
        const input = value.payload();
        expect(input.difficulty).toBeNull();
        expect(input.participants.map((p) => p.attribute_key)).toEqual(['st', 'ge']);
        expect(value.specifications()).toHaveLength(2);
        expect(value.specificationLabel(value.participants[0].settings)).toBe('Arkon');
    });

    it('discards a preview when form values changed while loading', async () => {
        const pending = deferred(); fetch.mockReturnValue(pending.promise);
        const value = form(); const promise = value.preview();
        value.invalidate();
        pending.resolve(response({ participants: [] }));
        await promise;
        expect(value.previewResult).toBeNull();
        expect(value.busy).toBe(false);
    });

    it('submits revisions only from the server preview and prevents duplicate previews', async () => {
        const value = form();
        fetch.mockResolvedValue(response({ participants: [{ rpg_character_id: 1, character_revision: 7 }] }));
        await Promise.all([value.preview(), value.preview()]);
        expect(fetch).toHaveBeenCalledTimes(1);
        expect(value.payload(true).participants[0].revision).toBe(7);
        expect(fetch.mock.calls[0][1].headers['X-CSRF-TOKEN']).toBe('test-token');
        expect(value.payload().participants[0]).not.toHaveProperty('revision');
    });

    it('shows server validation and prevents save without a preview', async () => {
        const value = form();
        await value.save();
        expect(fetch).not.toHaveBeenCalled();
        fetch.mockResolvedValue(response({ errors: { probe: ['Charakter geändert'] } }, 422));
        await value.preview();
        expect(value.error).toBe('Charakter geändert');
        expect(value.previewResult).toBeNull();
        expect(value.busy).toBe(false);
    });

    it('keeps a failed save retryable and does not invent new submission keys', async () => {
        const value = form();
        value.previewResult = { participants: [{ rpg_character_id: 1, character_revision: 0 }] };
        fetch.mockResolvedValue(response({}, 500));
        await value.save();
        expect(value.busy).toBe(false);
        expect(value.error).not.toBe('');
        expect(JSON.parse(fetch.mock.calls[0][1].body).submission_key).toBe('key');
    });

    it('navigates only after successful creation and handles malformed error bodies', async () => {
        const value = form();
        const assign = vi.fn();
        vi.stubGlobal('window', { location: { assign } });
        value.previewResult = { participants: [{ rpg_character_id: 1, character_revision: 0 }] };
        fetch.mockResolvedValueOnce({ ok: false, status: 502, json: async () => { throw new Error('not JSON'); } });
        await value.save();
        expect(value.error).not.toBe('');
        fetch.mockResolvedValueOnce(response({ redirect: '/rpg/proben' }));
        await value.save();
        expect(assign).toHaveBeenCalledWith('/rpg/proben');
    });

    it('limits modifiers and supplies available skills before character selection', () => {
        const value = form();
        value.participants = [];
        expect(value.availableSkills(value.common)).toEqual(config.skills);
        expect(value.specificationLabel(value.common)).toBe('Gemeinsame Vorgaben');
        for (let i = 0; i < 25; i++) value.addModifier(value.common);
        expect(value.common.modifiers).toHaveLength(20);
        value.mode = 'opposed'; value.selectedCharacter = '1'; value.addParticipant();
        expect(value.availableSkills(value.participants[0].settings)).toContain('Kunde: Wetter');
        value.selectedCharacter = '999'; value.addParticipant();
        expect(value.participants).toHaveLength(1);
        const legacy = rpgCheckForm({ ...config, characters: [{ id: 1, label: 'Arkon' }] });
        legacy.selectedCharacter = '1'; legacy.addParticipant();
        expect(legacy.availableSkills(legacy.common)).toEqual(config.skills);
    });
});

describe('check display', () => {
    function display() {
        const value = rpgChecks({ initial: { checks: [{ id: 1, status: 'pending' }] }, url: '/checks', detail: true });
        value.$root = document.createElement('div');
        value.$root.innerHTML = '<input name="_token" value="token">';
        value.init(); disposables.push(value);
        return value;
    }

    it('prevents double rolls and replaces only the affected check', async () => {
        const value = display(); const pending = deferred();
        const other = { id: 2, status: 'pending' };
        value.data.checks.push(other);
        fetch.mockReturnValue(pending.promise);
        const first = value.act('/roll');
        await value.act('/roll');
        expect(fetch).toHaveBeenCalledTimes(1);
        expect(JSON.parse(fetch.mock.calls[0][1].body)).toEqual({});
        pending.resolve(response({ id: 1, status: 'rolled', status_label: 'Gewürfelt' }));
        await first;
        expect(value.data.checks[0].status).toBe('rolled');
        expect(value.data.checks[1]).toEqual(other);
        expect(value.announcement).toBe('Gewürfelt');
        expect(value.busy).toBe(false);
    });

    it('clears previously displayed private data after revoked access', async () => {
        const value = display();
        fetch.mockResolvedValue(response({}, 403));
        await value.refresh();
        expect(value.data.checks).toEqual([]);
        expect(value.error).not.toBe('');
    });

    it('updates details and lists while avoiding repeated announcements for identical data', async () => {
        const value = display();
        fetch.mockResolvedValue(response({ id: 1, status: 'completed' }));
        await value.refresh();
        expect(value.data.checks[0].status).toBe('completed');
        expect(value.announcement).not.toBe('');
        value.announcement = '';
        await value.refresh();
        expect(value.announcement).toBe('');
        const listing = rpgChecks({ initial: { checks: [] }, url: '/checks' });
        listing.init(); disposables.push(listing);
        fetch.mockResolvedValue(response({ checks: [{ id: 2 }], total: 1 }));
        await listing.refresh();
        expect(listing.data.total).toBe(1);
        fetch.mockRejectedValueOnce(new Error('offline'));
        await listing.refresh();
        expect(listing.data.checks).toHaveLength(1);
        expect(listing.error).not.toBe('');
    });

    it('shows action validation and permanently stops polling after revoked mutation access', async () => {
        const value = display();
        fetch.mockResolvedValueOnce(response({ errors: { probe: ['Bereits storniert'] } }, 422));
        await value.act('/roll');
        expect(value.error).toBe('Bereits storniert');
        expect(value.data.checks).toHaveLength(1);
        fetch.mockResolvedValueOnce(response({}, 403));
        await value.act('/roll');
        expect(value.data.checks).toEqual([]);
        await vi.advanceTimersByTimeAsync(10000);
        expect(fetch).toHaveBeenCalledTimes(2);
    });

    it('registers the two Alpine components', () => {
        const data = vi.fn();
        vi.stubGlobal('window', { Alpine: { data } });
        document.dispatchEvent(new Event('alpine:init'));
        expect(data).toHaveBeenCalledWith('rpgChecks', rpgChecks);
        expect(data).toHaveBeenCalledWith('rpgCheckForm', rpgCheckForm);
    });

    it('labels outcomes without assuming that a pending roll failed', () => {
        const value = display();
        expect(value.resultLabel(null)).toBe('Auswertung ausstehend');
        expect(value.resultLabel(null, 'completed')).toBe('Ausgang durch die Spielleitung entschieden');
        expect(value.resultLabel(null, 'awaiting_decision')).toBe('Gleichstand');
        expect(value.resultLabel(null, 'cancelled')).toBe('Vergleich storniert');
        expect(value.resultLabel('fumble')).toBe('Patzer');
        expect(value.outcome({ mode: 'opposed', status: 'pending' })).toBe('');
        expect(value.outcome({ mode: 'opposed', status: 'completed', winner_position: null })).toBe('Unentschieden / anderer Ausgang');
        expect(value.outcome({ mode: 'opposed', status: 'completed', winner_position: 2, participants: [{ position: 2, character_name: 'Birka' }] })).toBe('Gewonnen: Birka');
    });
});
