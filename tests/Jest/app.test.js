import { jest } from '@jest/globals';

// Using jest.unstable_mockModule because Jest's stable mock API currently
// lacks full support for mocking ES modules loaded via dynamic import. This
// lets us stub out side-effect-heavy dependencies when testing app.js.

const originalMatchMedia = window.matchMedia;
const originalL = window.L;
const originalAlpine = window.Alpine;

afterEach(() => {
  window.matchMedia = originalMatchMedia;
  window.L = originalL;
  window.Alpine = originalAlpine;
});

async function loadApp(matches) {
  jest.resetModules();
  document.documentElement.className = '';
  document.documentElement.dataset.theme = '';
  delete window.L;
  delete window.Alpine;
  delete window.__omxfcPrefersDark;
  delete window.__omxfcApplySystemTheme;
  delete window.__omxfcApplyStoredTheme;

  document.documentElement.classList.toggle('dark', matches);
  document.documentElement.dataset.theme = matches ? 'dark' : 'light';

  let handler;
  window.matchMedia = jest.fn().mockReturnValue({
    matches,
    addEventListener: (event, cb) => {
      if (event === 'change') handler = cb;
    },
  });
  await jest.unstable_mockModule('../../resources/js/bootstrap.js', () => ({}));
  await jest.unstable_mockModule('../../resources/js/chronik.js', () => ({}));
  await jest.unstable_mockModule('../../resources/js/char-editor.js', () => ({}));
  await jest.unstable_mockModule('leaflet', () => ({ default: {} }));
  
  // Mock Alpine.js and its focus plugin
  const mockAlpine = {
    plugin: jest.fn(),
    start: jest.fn(),
    _x_dataStack: undefined,
    version: '3.15.4',
  };
  await jest.unstable_mockModule('alpinejs', () => ({ default: mockAlpine }));
  await jest.unstable_mockModule('@alpinejs/focus', () => ({ default: {} }));
  
  await import('../../resources/js/app.js');
  return handler;
}

describe('app module', () => {
  test('applies dark class based on preference', async () => {
    const handler = await loadApp(true);
    expect(document.documentElement.classList.contains('dark')).toBe(true);
    expect(document.documentElement.dataset.theme).toBe('dark');
    handler({ matches: false });
    expect(document.documentElement.classList.contains('dark')).toBe(false);
    expect(document.documentElement.dataset.theme).toBe('light');
  });

  test('adds dark class when preference changes to dark', async () => {
    const handler = await loadApp(false);
    expect(document.documentElement.classList.contains('dark')).toBe(false);
    expect(document.documentElement.dataset.theme).toBe('light');
    handler({ matches: true });
    expect(document.documentElement.classList.contains('dark')).toBe(true);
    expect(document.documentElement.dataset.theme).toBe('dark');
  });

  test('exposes Leaflet globally', async () => {
    await loadApp(true);
    expect(window.L).toEqual({});
  });
});
