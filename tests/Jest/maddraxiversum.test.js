import { jest } from '@jest/globals';

describe('maddraxiversum', () => {
  let mod;
  let mockMap;
  let mockMarker;
  let mockLatLngBounds;
  let mockAxios;

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
    mockAxios = {
      get: jest.fn(() => Promise.resolve({ data: { query: { results: {} } } })),
      post: jest.fn(() => Promise.resolve({ data: { status: 'completed', mission: {} } })),
    };

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

    await jest.unstable_mockModule('axios', () => ({ default: mockAxios }));

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
