import { jest } from '@jest/globals';

describe('maddraxiversum', () => {
  let mod;
  let mockMap;
  let mockMarker;
  let mockLatLngBounds;

  beforeEach(async () => {
    jest.resetModules();
    mockMap = {
      setView: jest.fn().mockReturnThis(),
      addLayer: jest.fn(),
      fitBounds: jest.fn(),
      panTo: jest.fn(),
      removeLayer: jest.fn(),
      on: jest.fn(),
    };
    mockMarker = {
      setLatLng: jest.fn(),
      addTo: jest.fn().mockReturnThis(),
    };
    mockLatLngBounds = jest.fn(() => ({}));
    await jest.unstable_mockModule('leaflet', () => ({
      default: {
        map: jest.fn(() => mockMap),
        tileLayer: jest.fn(() => ({ addTo: jest.fn() })),
        icon: jest.fn(),
        markerClusterGroup: jest.fn(() => ({ addLayer: jest.fn() })),
        marker: jest.fn(() => mockMarker),
        divIcon: jest.fn(() => ({})),
        latLngBounds: mockLatLngBounds,
      },
    }));

    await jest.unstable_mockModule('leaflet.markercluster', () => ({}));

    const axios = (await import('axios')).default;
    jest.spyOn(axios, 'get').mockResolvedValue({ data: { query: { results: {} } } });
    jest.spyOn(axios, 'post').mockResolvedValue({ data: { status: 'completed', mission: {} } });

    document.body.innerHTML = `
      <div id="mission-modal" class="hidden"></div>
      <h3 id="mission-title"></h3>
      <p id="mission-description"></p>
      <span id="mission-duration"></span>
      <button id="start-mission"></button>
      <button id="close-mission-modal"></button>
      <div id="map"></div>
    `;
    global.tileUrl = 'http://tiles.example';

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
    expect(modalEl.classList.contains('flex')).toBe(true);
    expect(modalEl.classList.contains('hidden')).toBe(false);
  });

  test('close button hides mission modal', () => {
    const { openMissionModal } = mod;
    const mission = { name: 'Test', description: 'Desc', mission_duration: 10 };
    openMissionModal(mission);
    const modalEl = document.getElementById('mission-modal');
    document.getElementById('close-mission-modal').click();
    expect(modalEl.classList.contains('hidden')).toBe(true);
    expect(modalEl.classList.contains('flex')).toBe(false);
  });

  test('animateGlider moves marker along path and cleans up', async () => {
    const { animateGlider, calculateBearing } = mod;
    jest.useFakeTimers();
    const logSpy = jest.spyOn(console, 'log').mockImplementation(() => {});
    const promise = animateGlider([0, 0], [1, 1], 1);
    expect(mockLatLngBounds).toHaveBeenCalledWith([[0, 0], [1, 1]]);
    expect(mockMap.fitBounds).toHaveBeenCalled();
    jest.runAllTimers();
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
  let axios;
  let popupOpen;
  let domReady;

  async function setup({ cityResults = {}, statusResult = { data: { status: 'none' } } } = {}) {
    jest.resetModules();
    popupOpen = undefined;
    mockMap = {
      setView: jest.fn().mockReturnThis(),
      addLayer: jest.fn(),
      fitBounds: jest.fn(),
      panTo: jest.fn(),
      removeLayer: jest.fn(),
      on: jest.fn((evt, cb) => {
        if (evt === 'popupopen') popupOpen = cb;
      }),
    };
    mockMarker = {
      setLatLng: jest.fn(),
      addTo: jest.fn().mockReturnThis(),
      bindPopup: jest.fn().mockReturnThis(),
    };
    mockCluster = { addLayer: jest.fn() };
    mockLatLngBounds = jest.fn(() => ({}));
    const originalAdd = document.addEventListener;
    document.addEventListener = (evt, cb) => {
      if (evt === 'DOMContentLoaded') domReady = cb;
    };

    await jest.unstable_mockModule('leaflet', () => ({
      default: {
        map: jest.fn(() => mockMap),
        tileLayer: jest.fn(() => ({ addTo: jest.fn() })),
        icon: jest.fn(() => ({})),
        markerClusterGroup: jest.fn(() => mockCluster),
        marker: jest.fn(() => mockMarker),
        divIcon: jest.fn(() => ({})),
        latLngBounds: mockLatLngBounds,
      },
    }));
    await jest.unstable_mockModule('leaflet.markercluster', () => ({}));
    axios = (await import('axios')).default;
    jest.spyOn(axios, 'get').mockImplementation((url) =>
      url === '/maddraxikon-staedte'
        ? Promise.resolve({ data: { query: { results: cityResults } } })
        : Promise.resolve(statusResult)
    );
    jest.spyOn(axios, 'post').mockResolvedValue({ data: { status: 'completed', mission: {} } });

    document.body.innerHTML = `
      <div id="mission-modal" class="hidden"></div>
      <h3 id="mission-title"></h3>
      <p id="mission-description"></p>
      <span id="mission-duration"></span>
      <button id="start-mission"></button>
      <button id="close-mission-modal"></button>
      <div id="map"></div>
      <meta name="csrf-token" content="TOKEN" />
    `;
    global.tileUrl = 'http://tiles.example';
    global.csrfToken = 'TOKEN';

    mod = await import('../../resources/js/maddraxiversum.js');
    document.addEventListener = originalAdd;
  }

  test('adds city markers and mission link opens modal', async () => {
    await setup({
      cityResults: {
        Waashton: {
          printouts: { Koordinaten: [{ lat: 1, lon: 2 }] },
          fullurl: '/waashton',
        },
      },
    });
    expect(axios.get).toHaveBeenCalledWith('/maddraxikon-staedte');
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
    expect(modalEl.classList.contains('flex')).toBe(true);
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
    global.alert = jest.fn();
    jest.useFakeTimers();
    startBtn.click();
    await jest.runAllTimersAsync();
    expect(axios.post).toHaveBeenCalledWith(
      '/mission/starten',
      expect.any(Object),
      expect.objectContaining({ headers: { 'X-CSRF-TOKEN': 'TOKEN' } })
    );
    expect(mockMap.fitBounds).toHaveBeenCalled();
    jest.useRealTimers();
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
    global.alert = jest.fn();
    jest.useFakeTimers();
    domReady();
    await jest.runAllTimersAsync();
    jest.useRealTimers();
    expect(axios.get).toHaveBeenCalledWith('/mission/status');
    expect(mockMap.fitBounds).toHaveBeenCalled();
  });
});

