import { jest } from '@jest/globals';

const originalMatchMedia = window.matchMedia;

afterEach(() => {
  window.matchMedia = originalMatchMedia;
});

async function loadApp(matches) {
  jest.resetModules();
  document.documentElement.className = '';
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
});
