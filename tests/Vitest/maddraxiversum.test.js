import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => {
    const map = { setView: vi.fn().mockReturnThis(), addLayer: vi.fn(), fitBounds: vi.fn(), panTo: vi.fn(), removeLayer: vi.fn(), remove: vi.fn() };
    const marker = { setLatLng: vi.fn(), addTo: vi.fn().mockReturnThis(), bindPopup: vi.fn().mockReturnThis() };
    const cluster = { addLayer: vi.fn() };
    return { map, marker, cluster, leaflet: { map: vi.fn(() => map), tileLayer: vi.fn(() => ({ addTo: vi.fn() })), icon: vi.fn(), markerClusterGroup: vi.fn(() => cluster), marker: vi.fn(() => marker), divIcon: vi.fn(), latLngBounds: vi.fn() }, get: vi.fn(), post: vi.fn() };
});
vi.mock('leaflet', () => ({ default: mocks.leaflet }));
vi.mock('leaflet.markercluster', () => ({}));
vi.mock('../../resources/js/http/client', () => ({ default: { get: mocks.get, post: mocks.post } }));
import { calculateBearing, mountMaddraxiversum } from '../../resources/js/maddraxiversum';
let cleanup;
const tick = async () => { await vi.advanceTimersByTimeAsync(0); };
beforeEach(() => {
    vi.useFakeTimers();
    vi.clearAllMocks();
    vi.spyOn(console, 'log').mockImplementation(() => {});
    vi.spyOn(console, 'error').mockImplementation(() => {});
    window.alert = vi.fn();
    document.body.innerHTML = '<meta name="csrf-token" content="TOKEN"><div id="map" data-tile-url="/tiles/{z}/{x}/{y}.png"></div><dialog id="mission-modal"></dialog><h3 id="mission-title"></h3><p id="mission-description"></p><span id="mission-duration"></span><button id="start-mission"></button>';
    document.querySelector('dialog').showModal = vi.fn();
    document.querySelector('dialog').close = vi.fn();
    mocks.get.mockImplementation(url => Promise.resolve(url === '/maddraxikon-staedte'
        ? { data: { query: { results: { Waashton: { printouts: { Koordinaten: [{ lat: 1, lon: 2 }] }, fullurl: '/waashton' }, Empty: { printouts: {} } } } } }
        : { data: { status: 'none' } }));
    mocks.post.mockResolvedValue({ data: { status: 'completed' } });
});
afterEach(async () => { cleanup?.(); cleanup = undefined; await tick(); vi.useRealTimers(); vi.restoreAllMocks(); });
const mount = () => { cleanup = mountMaddraxiversum(document.getElementById('map')); };
const start = () => {
    const button = document.getElementById('start-mission');
    button.dataset.mission = JSON.stringify({ name: 'Test', destination: 'Salem', travel_duration: 1, mission_duration: 1 });
    button.click();
    return button;
};

describe('Maddraxiversum lifecycle', () => {
    it('rejects an incomplete dialog before allocating map resources', () => {
        document.getElementById('start-mission').remove();
        expect(mount).toThrow('Missionsdialog ist unvollständig');
        expect(mocks.leaflet.map).not.toHaveBeenCalled();
    });
    it('silently ignores a status response after disposal and cleans up only once', async () => {
        let resolveStatus;
        mocks.get.mockImplementation(url => url === '/mission/status'
            ? new Promise(resolve => { resolveStatus = resolve; }) : Promise.resolve({ data: { query: { results: {} } } }));
        mount();
        cleanup(); cleanup(); cleanup = undefined;
        resolveStatus({ data: { status: 'none' } });
        await tick();
        expect(mocks.map.remove).toHaveBeenCalledTimes(1);
        expect(console.error).not.toHaveBeenCalled();
    });
    it('calculates bearings including dateline crossings and identical coordinates', () => {
        expect(calculateBearing([0, 0], [1, 0])).toBeCloseTo(0);
        expect(calculateBearing([0, 0], [0, 1])).toBeCloseTo(90);
        expect(calculateBearing([0, 0], [-1, 0])).toBeCloseTo(180);
        expect(calculateBearing([0, 0], [0, -1])).toBeCloseTo(270);
        expect(calculateBearing([0, 0], [0, 0])).toBeCloseTo(0);
        expect(calculateBearing([10, 170], [10, -170])).toBeCloseTo(88.246, 3);
        expect(calculateBearing([-10, -170], [-10, 170])).toBeCloseTo(268.246, 3);
    });
    it('loads scoped tiles and cities and opens mission popups through one delegated listener', async () => {
        mount(); await tick();
        expect(mocks.leaflet.tileLayer).toHaveBeenCalledWith('/tiles/{z}/{x}/{y}.png', expect.any(Object));
        expect(mocks.cluster.addLayer).toHaveBeenCalledTimes(1);
        expect(mocks.map.addLayer).toHaveBeenCalledWith(mocks.cluster);
        document.getElementById('map').innerHTML = '<button class="mission-link" data-city="Waashton" data-index="0">Mission</button>';
        document.querySelector('.mission-link').click();
        expect(document.getElementById('mission-title').textContent).toBe('Hexenjäger von Salem');
        expect(document.getElementById('mission-description').textContent).toContain('Nosfera');
        expect(document.getElementById('mission-duration').textContent).toContain('12');
        expect(document.querySelector('dialog').showModal).toHaveBeenCalledTimes(1);
        document.getElementById('map').click();
        expect(document.querySelector('dialog').showModal).toHaveBeenCalledTimes(1);
    });
    it('starts once, flies both legs, completes, and removes transient styles', async () => {
        mount(); await tick();
        const button = start(); button.dispatchEvent(new Event('click'));
        await vi.runAllTimersAsync();
        expect(mocks.post.mock.calls.filter(([url]) => url === '/mission/starten')).toHaveLength(1);
        expect(mocks.map.fitBounds).toHaveBeenCalledTimes(2);
        expect(mocks.marker.setLatLng).toHaveBeenCalled();
        expect(mocks.map.panTo).toHaveBeenCalled();
        expect(mocks.map.removeLayer).toHaveBeenCalledTimes(2);
        expect(document.head.querySelector('style')).toBeNull();
        expect(window.alert).toHaveBeenCalledWith(expect.stringContaining('erfolgreich'));
        expect(button.disabled).toBe(false);
    });
    it.each(['traveling', 'in_mission'])('resumes a %s mission and sends completion', async status => {
        const original = mocks.get.getMockImplementation();
        mocks.get.mockImplementation(url => url === '/mission/status' ? Promise.resolve({ data: { status, mission: {
            origin: 'Waashton', destination: 'Salem', travel_duration: 1, mission_duration: 1, started_at: new Date().toISOString(),
        } } }) : original(url));
        mount(); await vi.runAllTimersAsync();
        expect(mocks.map.fitBounds).toHaveBeenCalled();
        expect(mocks.post).toHaveBeenCalledWith('/mission/status-pruefen', {}, expect.objectContaining({ signal: expect.any(AbortSignal) }));
    });
    it('aborts requests, animation, timers and listeners before a map is removed', async () => {
        mount(); await tick(); start(); await tick();
        const signal = mocks.get.mock.calls[0][1].signal;
        cleanup(); cleanup = undefined;
        const frames = mocks.map.panTo.mock.calls.length;
        await vi.runAllTimersAsync();
        expect(signal.aborted).toBe(true);
        expect(mocks.map.remove).toHaveBeenCalledTimes(1);
        expect(mocks.map.panTo).toHaveBeenCalledTimes(frames);
        expect(mocks.post.mock.calls.map(([url]) => url)).toEqual(['/mission/starten']);
        expect(document.head.querySelector('style')).toBeNull();
        expect(window.alert).not.toHaveBeenCalled();
        document.getElementById('start-mission').click();
        expect(mocks.post).toHaveBeenCalledTimes(1);
    });
    it('cancels the mission waiting period and ignores a late cities response', async () => {
        let resolveCities;
        mocks.get.mockImplementation(url => url === '/maddraxikon-staedte'
            ? new Promise(resolve => { resolveCities = resolve; }) : Promise.resolve({ data: { status: 'none' } }));
        mount(); start(); await vi.advanceTimersByTimeAsync(1200);
        cleanup(); cleanup = undefined;
        resolveCities({ data: { query: { results: {} } } });
        await vi.runAllTimersAsync();
        expect(mocks.map.addLayer).not.toHaveBeenCalled();
        expect(mocks.post).toHaveBeenCalledTimes(1);
    });
    it.each([{}, { response: { data: { error: 'Schon aktiv' } } }])('reports start failures and re-enables the button', async error => {
        mount(); await tick(); mocks.post.mockRejectedValue(error);
        const button = start(); await tick();
        expect(window.alert).toHaveBeenCalledWith(error.response?.data.error ?? 'Ein Fehler ist aufgetreten. Bitte versuche es später erneut.');
        expect(button.disabled).toBe(false);
    });
    it('reports unexpected completion statuses and request failures', async () => {
        mount(); await tick(); mocks.post.mockResolvedValue({ data: { status: 'traveling' } });
        start(); await vi.runAllTimersAsync();
        expect(window.alert).toHaveBeenCalledWith(expect.stringContaining('nicht als abgeschlossen'));
    });
    it('handles failed cities and status requests without unhandled rejections', async () => {
        mocks.get.mockRejectedValue(new Error('offline'));
        mount(); await tick();
        expect(console.error).toHaveBeenCalledTimes(2);
    });
});
