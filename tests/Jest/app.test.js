import { jest } from '@jest/globals';

// Using jest.unstable_mockModule because Jest's stable mock API currently
// lacks full support for mocking ES modules loaded via dynamic import. This
// lets us stub out side-effect-heavy dependencies when testing app.js.

const originalMatchMedia = window.matchMedia;
const originalL = window.L;

afterEach(() => {
  document.body.innerHTML = '';
  window.matchMedia = originalMatchMedia;
  window.L = originalL;
  delete window.omxfc;
});

async function loadApp(matches, options = {}) {
  jest.resetModules();
  document.documentElement.className = '';
  document.documentElement.dataset.theme = '';
  delete window.L;
  delete window.__omxfcPrefersDark;
  delete window.__omxfcApplySystemTheme;
  delete window.__omxfcApplyStoredTheme;
  delete window.omxfc;

  document.documentElement.classList.toggle('dark', matches);
  document.documentElement.dataset.theme = matches ? 'dark' : 'light';

  let handler;
  window.matchMedia = jest.fn().mockReturnValue({
    matches,
    addEventListener: options.legacyListener
      ? undefined
      : jest.fn((event, cb) => {
          if (event === 'change') handler = cb;
        }),
    addListener: options.legacyListener
      ? jest.fn((cb) => {
          handler = cb;
        })
      : undefined,
  });
  await jest.unstable_mockModule('../../resources/js/bootstrap.js', () => ({}));
  await jest.unstable_mockModule('../../resources/js/chronik.js', () => ({}));
  await jest.unstable_mockModule('../../resources/js/char-editor.js', () => ({}));
  await jest.unstable_mockModule('leaflet', () => ({ default: {} }));

  if (typeof options.setupDom === 'function') {
    options.setupDom();
  }

  await import('../../resources/js/app.js');
  const mediaQueryList = window.matchMedia.mock.results[0].value;
  return { handler, mediaQueryList };
}

describe('app module', () => {
  test('applies dark class based on preference', async () => {
    const { handler } = await loadApp(true);
    expect(document.documentElement.classList.contains('dark')).toBe(true);
    expect(document.documentElement.dataset.theme).toBe('dark');
    handler({ matches: false });
    expect(document.documentElement.classList.contains('dark')).toBe(false);
    expect(document.documentElement.dataset.theme).toBe('light');
  });

  test('adds dark class when preference changes to dark', async () => {
    const { handler } = await loadApp(false);
    expect(document.documentElement.classList.contains('dark')).toBe(false);
    expect(document.documentElement.dataset.theme).toBe('light');
    handler({ matches: true });
    expect(document.documentElement.classList.contains('dark')).toBe(true);
    expect(document.documentElement.dataset.theme).toBe('dark');
  });

  test('uses legacy addListener hook when addEventListener is unavailable', async () => {
    const { handler, mediaQueryList } = await loadApp(false, { legacyListener: true });

    expect(mediaQueryList.addListener).toHaveBeenCalledTimes(1);
    const registeredHandler = mediaQueryList.addListener.mock.calls[0][0];
    expect(handler).toBe(registeredHandler);

    registeredHandler({ matches: true });
    expect(document.documentElement.classList.contains('dark')).toBe(true);
    expect(document.documentElement.dataset.theme).toBe('dark');
  });

  test('exposes Leaflet globally', async () => {
    await loadApp(true);
    expect(window.L).toEqual({});
  });

  test('ensures the mobile navigation toggle keeps its x-ref', async () => {
    const toggle = document.createElement('button');
    toggle.setAttribute('aria-controls', 'mobile-navigation');
    toggle.setAttribute('x-ref', '');
    document.body.appendChild(toggle);

    await loadApp(false, {
      setupDom: () => {
        if (!document.body.contains(toggle)) {
          document.body.appendChild(toggle);
        }
      },
    });

    expect(toggle.getAttribute('x-ref')).toBe('mobileToggle');

    toggle.setAttribute('x-ref', '');
    await new Promise((resolve) => {
      setTimeout(resolve, 0);
    });

    expect(toggle.getAttribute('x-ref')).toBe('mobileToggle');
  });
});
