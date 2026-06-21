import { vi } from 'vitest';

function jsonResponse(data, { ok = true, status = 200, statusText = 'OK' } = {}) {
  return {
    ok,
    status,
    statusText,
    headers: { get: () => 'application/json' },
    json: vi.fn().mockResolvedValue(data),
    text: vi.fn().mockResolvedValue(JSON.stringify(data)),
  };
}

describe('maddraxiversum', () => {
  let mod;
  let mockMap;
  let mockMarker;
  let mockLatLngBounds;

  beforeEach(async () => {
    vi.resetModules();
    mockMap = {
      setView: vi.fn().mockReturnThis(),
      addLayer: vi.fn(),
      fitBounds: vi.fn(),
      panTo: vi.fn(),
      removeLayer: vi.fn(),
      on: vi.fn(),
    };
    mockMarker = {
      setLatLng: vi.fn(),
      addTo: vi.fn().mockReturnThis(),
    };
    mockLatLngBounds = vi.fn(() => ({}));
    vi.doMock('leaflet', () => ({
      default: {
        map: vi.fn(() => mockMap),
        tileLayer: vi.fn(() => ({ addTo: vi.fn() })),
        icon: vi.fn(),
        markerClusterGroup: vi.fn(() => ({ addLayer: vi.fn() })),
        marker: vi.fn(() => mockMarker),
        divIcon: vi.fn(() => ({})),
        latLngBounds: mockLatLngBounds,
      },
    }));

    vi.doMock('leaflet.markercluster', () => ({}));

    globalThis.fetch = vi.fn().mockImplementation((url) => {
      if (url === '/maddraxikon-staedte') {
        return Promise.resolve(jsonResponse({ query: { results: {} } }));
      }

      return Promise.resolve(jsonResponse({ status: 'completed', mission: {} }));
    });

    document.body.innerHTML = `
      <dialog id="mission-modal"></dialog>
      <h3 id="mission-title"></h3>
      <p id="mission-description"></p>
      <span id="mission-duration"></span>
      <button id="start-mission"></button>
      <div id="map"></div>
    `;

    // Polyfill: jsdom unterstützt keine native <dialog>-API
    const dialogEl = document.getElementById('mission-modal');
    dialogEl.showModal = vi.fn(function () { this.open = true; });
    dialogEl.close = vi.fn(function () { this.open = false; });

    globalThis.tileUrl = 'http://tiles.example';

    mod = await import('../../resources/js/maddraxiversum.js');
  });

  test('calculateBearing returns correct bearings', () => {
    const { calculateBearing } = mod;
    expect(calculateBearing([0, 0], [1, 0])).toBeCloseTo(0);
    expect(calculateBearing([0, 0], [0, 1])).toBeCloseTo(90);
    expect(calculateBearing([0, 0], [-1, 0])).toBeCloseTo(180);
    expect(calculateBearing([0, 0], [0, -1])).toBeCloseTo(270);
  });

  test('calculateBearing handles identical points and dateline crossings', () => {
    const { calculateBearing } = mod;
    expect(calculateBearing([0, 0], [0, 0])).toBeCloseTo(0);
    expect(calculateBearing([10, 170], [10, -170])).toBeCloseTo(88.246, 3);
    expect(calculateBearing([-10, -170], [-10, 170])).toBeCloseTo(268.246, 3);
  });

  test('openMissionModal populates and shows modal', () => {
    const { openMissionModal } = mod;
    const mission = { name: 'Test', description: 'Desc', mission_duration: 10 };
    openMissionModal(mission);
    expect(document.getElementById('mission-title').textContent).toBe('Test');
    expect(document.getElementById('mission-description').textContent).toBe('Desc');
    expect(document.getElementById('mission-duration').textContent).toBe('Dauer: 10 min');
    const startBtn = document.getElementById('start-mission');
    expect(startBtn.dataset.mission).toBe(JSON.stringify(mission));
    const modalEl = document.getElementById('mission-modal');
    expect(modalEl.showModal).toHaveBeenCalled();
    expect(modalEl.open).toBe(true);
  });

  test('animateGlider moves marker along path and cleans up', async () => {
    const { animateGlider, calculateBearing } = mod;
    vi.useFakeTimers();
    const logSpy = vi.spyOn(console, 'log').mockImplementation(() => {});
    const promise = animateGlider([0, 0], [1, 1], 1);
    expect(mockLatLngBounds).toHaveBeenCalledWith([[0, 0], [1, 1]]);
    expect(mockMap.fitBounds).toHaveBeenCalled();
    vi.runAllTimers();
    await promise;
    expect(logSpy).toHaveBeenCalledWith('Berechneter Kurs:', calculateBearing([0, 0], [1, 1]));
    expect(mockMarker.setLatLng).toHaveBeenCalled();
    expect(mockMap.panTo).toHaveBeenCalled();
    expect(mockMap.removeLayer).toHaveBeenCalledWith(mockMarker);
    expect(document.head.querySelector('style')).not.toBeNull();
    logSpy.mockRestore();
  });
});

describe('maddraxiversum extended behaviour', () => {
  let mod;
  let mockMap;
  let mockMarker;
  let mockCluster;
  let mockLatLngBounds;
  let popupOpen;
  let domReady;
  let addEventListenerSpy;

  async function setup({ cityResults = {}, statusResult = { data: { status: 'none' } } } = {}) {
    vi.resetModules();
    popupOpen = undefined;
    mockMap = {
      setView: vi.fn().mockReturnThis(),
      addLayer: vi.fn(),
      fitBounds: vi.fn(),
      panTo: vi.fn(),
      removeLayer: vi.fn(),
      on: vi.fn((evt, cb) => {
        if (evt === 'popupopen') popupOpen = cb;
      }),
    };
    mockMarker = {
      setLatLng: vi.fn(),
      addTo: vi.fn().mockReturnThis(),
      bindPopup: vi.fn().mockReturnThis(),
    };
    mockCluster = { addLayer: vi.fn() };
    mockLatLngBounds = vi.fn(() => ({}));
    const originalAdd = document.addEventListener;
    addEventListenerSpy = vi
      .spyOn(document, 'addEventListener')
      .mockImplementation((evt, cb, opts) => {
        if (evt === 'DOMContentLoaded') {
          domReady = cb;
          return;
        }
        return originalAdd.call(document, evt, cb, opts);
      });

    vi.doMock('leaflet', () => ({
      default: {
        map: vi.fn(() => mockMap),
        tileLayer: vi.fn(() => ({ addTo: vi.fn() })),
        icon: vi.fn(() => ({})),
        markerClusterGroup: vi.fn(() => mockCluster),
        marker: vi.fn(() => mockMarker),
        divIcon: vi.fn(() => ({})),
        latLngBounds: mockLatLngBounds,
      },
    }));
    vi.doMock('leaflet.markercluster', () => ({}));
    globalThis.fetch = vi.fn().mockImplementation((url) => {
      if (url === '/maddraxikon-staedte') {
        return Promise.resolve(jsonResponse({ query: { results: cityResults } }));
      }

      if (url === '/mission/status') {
        return Promise.resolve(jsonResponse(statusResult.data));
      }

      return Promise.resolve(jsonResponse({ status: 'completed', mission: {} }));
    });

    document.body.innerHTML = `
      <dialog id="mission-modal"></dialog>
      <h3 id="mission-title"></h3>
      <p id="mission-description"></p>
      <span id="mission-duration"></span>
      <button id="start-mission"></button>
      <div id="map"></div>
      <meta name="csrf-token" content="TOKEN" />
    `;

    // Polyfill: jsdom unterstützt keine native <dialog>-API
    const dialogEl = document.getElementById('mission-modal');
    dialogEl.showModal = vi.fn(function () { this.open = true; });
    dialogEl.close = vi.fn(function () { this.open = false; });

    globalThis.tileUrl = 'http://tiles.example';
    globalThis.csrfToken = 'TOKEN';

    mod = await import('../../resources/js/maddraxiversum.js');
  }

  afterEach(() => {
    addEventListenerSpy?.mockRestore();
  });

  test('adds city markers and mission link opens modal', async () => {
    await setup({
      cityResults: {
        Waashton: {
          printouts: { Koordinaten: [{ lat: 1, lon: 2 }] },
          fullurl: '/waashton',
        },
      },
    });
    expect(globalThis.fetch).toHaveBeenCalledWith('/maddraxikon-staedte', expect.objectContaining({
      method: 'GET',
    }));
    expect(mockMarker.bindPopup).toHaveBeenCalled();
    expect(mockCluster.addLayer).toHaveBeenCalledWith(mockMarker);
    expect(mockMap.addLayer).toHaveBeenCalledWith(mockCluster);

    // simulate popup open and click mission link
    const link = document.createElement('button');
    link.className = 'mission-link';
    link.dataset.city = 'Waashton';
    link.dataset.index = '0';
    document.body.appendChild(link);
    popupOpen();
    link.click();
    const modalEl = document.getElementById('mission-modal');
    expect(modalEl.showModal).toHaveBeenCalled();
    expect(modalEl.open).toBe(true);
  });

  test('start mission button posts data and animates', async () => {
    await setup();
    const startBtn = document.getElementById('start-mission');
    startBtn.dataset.mission = JSON.stringify({
      name: 'Test',
      destination: 'Salem',
      travel_duration: 1,
      mission_duration: 1,
    });
    globalThis.alert = vi.fn();
    vi.useFakeTimers();
    startBtn.click();
    await vi.runAllTimersAsync();
    const missionStartCall = globalThis.fetch.mock.calls.find(([url]) => url === '/mission/starten');
    expect(missionStartCall).toBeDefined();
    expect(missionStartCall[1].method).toBe('POST');
    expect(missionStartCall[1].headers.get('X-CSRF-TOKEN')).toBe('TOKEN');
    expect(mockMap.fitBounds).toHaveBeenCalled();
    vi.useRealTimers();
  });

  test('loadMissionStatus animates ongoing mission', async () => {
    const startedAt = new Date().toISOString();
    await setup({
      statusResult: {
        data: {
          status: 'traveling',
          mission: {
            origin: 'Waashton',
            destination: 'Salem',
            travel_duration: 1,
            mission_duration: 1,
            started_at: startedAt,
          },
          current_location: 'Waashton',
        },
      },
    });
    globalThis.alert = vi.fn();
    vi.useFakeTimers();
    domReady();
    await vi.runAllTimersAsync();
    vi.useRealTimers();
    const missionStatusCall = globalThis.fetch.mock.calls.find(([url]) => url === '/mission/status');
    expect(missionStatusCall).toBeDefined();
    expect(mockMap.fitBounds).toHaveBeenCalled();
  });
});

