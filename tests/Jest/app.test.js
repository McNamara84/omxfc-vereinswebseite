import { jest } from '@jest/globals';

// Using jest.unstable_mockModule because Jest's stable mock API currently
// lacks full support for mocking ES modules loaded via dynamic import. This
// lets us stub out side-effect-heavy dependencies when testing app.js.

const originalMatchMedia = window.matchMedia;
const originalL = window.L;

afterEach(() => {
  window.matchMedia = originalMatchMedia;
  window.L = originalL;
});

async function loadApp(matches) {
  jest.resetModules();
  document.documentElement.className = '';
  delete window.L;
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
  await import('../../resources/js/app.js');
  return handler;
}

describe('app module', () => {
  test('applies dark class based on preference', async () => {
    const handler = await loadApp(true);
    expect(document.documentElement.classList.contains('dark')).toBe(true);
    handler({ matches: false });
    expect(document.documentElement.classList.contains('dark')).toBe(false);
  });

  test('adds dark class when preference changes to dark', async () => {
    const handler = await loadApp(false);
    expect(document.documentElement.classList.contains('dark')).toBe(false);
    handler({ matches: true });
    expect(document.documentElement.classList.contains('dark')).toBe(true);
  });

  test('exposes Leaflet globally', async () => {
    await loadApp(true);
    expect(window.L).toEqual({});
  });
});
