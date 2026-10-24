import { beforeEach, afterEach, expect, it, vi } from 'vitest';
const mocks = vi.hoisted(() => {
    const map = { setView: vi.fn().mockReturnThis(), remove: vi.fn() };
    const marker = { addTo: vi.fn().mockReturnThis(), bindPopup: vi.fn() };
    const legend = { addTo: vi.fn() };
    return { map, marker, legend, leaflet: { map: vi.fn(() => map), tileLayer: vi.fn(() => ({ addTo: vi.fn() })), divIcon: vi.fn(), marker: vi.fn(() => marker), control: vi.fn(() => legend), DomUtil: { create: () => document.createElement('div') } } };
});
vi.mock('leaflet', () => ({ default: mocks.leaflet }));
import { mountMemberMap } from '../../resources/js/mitglieder/karte';
const utils = window.omxfcMemberMap;
beforeEach(() => {
    vi.clearAllMocks();
    document.body.innerHTML = '<div id="map" data-member-map></div><div id="member-map-config"></div>';
    window.omxfcMemberMap = utils;
});
afterEach(() => { vi.restoreAllMocks(); });
it('creates role markers, accessible legend, and releases Leaflet on removal', () => {
    const config = document.getElementById('member-map-config');
    config.dataset.members = JSON.stringify([{ name: 'Test', city: 'Berlin', role: 'Mitglied', lat: 1, lon: 2, profile_url: '/profil' }]);
    config.dataset.stammtische = JSON.stringify([{ name: 'Regional', lat: 2, lon: 3, address: 'Hier', info: 'Info' }]);
    const cleanup = mountMemberMap(document.getElementById('map'));
    expect(mocks.leaflet.marker).toHaveBeenCalledTimes(3);
    const legend = mocks.legend.onAdd();
    expect(legend.getAttribute('aria-label')).toBe('Legende der Mitgliederkarte');
    cleanup();
    expect(mocks.map.remove).toHaveBeenCalledTimes(1);
});
it('escapes fallback popup content and rejects unsafe links', () => {
    delete window.omxfcMemberMap;
    document.getElementById('member-map-config').dataset.members = JSON.stringify([
        { name: '<script>"\'&', city: null, role: 'Gast', lat: 1, lon: 2, profile_url: 'javascript:alert(1)' },
        { name: 'Valid', profile_url: '/profil', lat: 1, lon: 2 },
        { name: 'Missing', profile_url: null, lat: 1, lon: 2 },
    ]);
    const cleanup = mountMemberMap(document.getElementById('map'));
    expect(mocks.marker.bindPopup.mock.calls[0][0]).toContain('&lt;script&gt;');
    expect(mocks.marker.bindPopup.mock.calls[0][0]).toContain('href="#"');
    expect(mocks.legend.onAdd().textContent).toContain('Regionalstammtisch');
    cleanup();
});
it('does not initialize absent or invalid configuration', () => {
    expect(mountMemberMap(null)).toBeUndefined();
    document.getElementById('member-map-config').dataset.members = '{broken';
    vi.spyOn(console, 'error').mockImplementation(() => {});
    expect(mountMemberMap(document.getElementById('map'))).toBeUndefined();
    document.getElementById('member-map-config').remove();
    expect(mountMemberMap(document.getElementById('map'))).toBeUndefined();
    expect(mocks.leaflet.map).not.toHaveBeenCalled();
});
