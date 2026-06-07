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

async function loadApp(matches, { existingAlpine, bodyHtml = '' } = {}) {
  jest.resetModules();
  window.localStorage.clear();
  document.body.innerHTML = bodyHtml;
  document.documentElement.className = '';
  document.documentElement.dataset.theme = '';
  delete window.L;
  delete window.Alpine;
  delete window.__omxfcPrefersDark;
  delete window.__omxfcApplyStoredTheme;

  document.documentElement.classList.toggle('dark', matches);
  document.documentElement.dataset.theme = matches ? 'coffee' : 'caramellatte';

  let handler;
  window.matchMedia = jest.fn().mockReturnValue({
    matches,
    addEventListener: (event, cb) => {
      if (event === 'change') handler = cb;
    },
  });
  await jest.unstable_mockModule('../../resources/js/bootstrap.js', () => ({}));
  await jest.unstable_mockModule('../../resources/js/alpine/char-editor.js', () => ({}));
  await jest.unstable_mockModule('../../resources/js/alpine/hoerbuch-role-repeater.js', () => ({}));
  await jest.unstable_mockModule('leaflet', () => ({ default: {} }));

  const anchorPlugin = { name: 'anchor' };
  const focusPlugin = { name: 'focus' };
  const persistPlugin = { name: 'persist' };
  const collapsePlugin = { name: 'collapse' };

  // Mock Alpine.js and its plugins
  const mockAlpine = {
    plugin: jest.fn(),
    start: jest.fn(),
    _x_dataStack: undefined,
    version: '3.15.4',
  };
  await jest.unstable_mockModule('alpinejs', () => ({ default: mockAlpine }));
  await jest.unstable_mockModule('@alpinejs/anchor', () => ({ default: anchorPlugin }));
  await jest.unstable_mockModule('@alpinejs/focus', () => ({ default: focusPlugin }));
  await jest.unstable_mockModule('@alpinejs/persist', () => ({ default: persistPlugin }));
  await jest.unstable_mockModule('@alpinejs/collapse', () => ({ default: collapsePlugin }));

  if (existingAlpine) {
    window.Alpine = existingAlpine;
  }

  await import('../../resources/js/app.js');
  return {
    handler,
    mockAlpine,
    plugins: {
      anchorPlugin,
      focusPlugin,
      persistPlugin,
      collapsePlugin,
    },
  };
}

describe('app module', () => {
  test('applies dark class based on preference', async () => {
    const { handler } = await loadApp(true);
    expect(document.documentElement.classList.contains('dark')).toBe(true);
    expect(document.documentElement.dataset.theme).toBe('coffee');
    handler({ matches: false });
    expect(document.documentElement.classList.contains('dark')).toBe(false);
    expect(document.documentElement.dataset.theme).toBe('caramellatte');
  });

  test('adds dark class when preference changes to dark', async () => {
    const { handler } = await loadApp(false);
    expect(document.documentElement.classList.contains('dark')).toBe(false);
    expect(document.documentElement.dataset.theme).toBe('caramellatte');
    handler({ matches: true });
    expect(document.documentElement.classList.contains('dark')).toBe(true);
    expect(document.documentElement.dataset.theme).toBe('coffee');
  });

  test('syncs theme toggle pressed state on initial load', async () => {
    await loadApp(true, {
      bodyHtml: '<button data-theme-toggle aria-pressed="false"></button>',
    });

    expect(document.querySelector('[data-theme-toggle]').getAttribute('aria-pressed')).toBe('true');
  });

  test('syncs theme toggle pressed state when system preference changes', async () => {
    const { handler } = await loadApp(false, {
      bodyHtml: '<button data-theme-toggle aria-pressed="false"></button>',
    });

    expect(document.querySelector('[data-theme-toggle]').getAttribute('aria-pressed')).toBe('false');

    handler({ matches: true });

    expect(document.documentElement.dataset.theme).toBe('coffee');
    expect(document.querySelector('[data-theme-toggle]').getAttribute('aria-pressed')).toBe('true');
  });

  test('syncs theme toggle pressed state after storage theme updates', async () => {
    await loadApp(false, {
      bodyHtml: '<button data-theme-toggle aria-pressed="false"></button>',
    });

    window.localStorage.setItem('mary-theme', JSON.stringify('coffee'));
    window.localStorage.setItem('mary-class', JSON.stringify('dark'));

    const event = new Event('storage');
    Object.defineProperty(event, 'key', { value: 'mary-theme' });
    window.dispatchEvent(event);

    expect(document.documentElement.dataset.theme).toBe('coffee');
    expect(document.documentElement.classList.contains('dark')).toBe(true);
    expect(document.querySelector('[data-theme-toggle]').getAttribute('aria-pressed')).toBe('true');
  });

  test('syncs newly rendered theme toggles after livewire navigation', async () => {
    await loadApp(false);

    document.documentElement.dataset.theme = 'coffee';
    document.documentElement.classList.add('dark');
    document.body.innerHTML = '<button data-theme-toggle aria-pressed="false"></button>';
    document.dispatchEvent(new Event('livewire:navigated'));

    expect(document.querySelector('[data-theme-toggle]').getAttribute('aria-pressed')).toBe('true');
  });

  test('toggles from class-only dark state back to light mode', async () => {
    await loadApp(false, {
      bodyHtml: '<button data-theme-toggle aria-pressed="false"></button>',
    });

    document.documentElement.dataset.theme = '';
    document.documentElement.classList.add('dark');

    document.querySelector('[data-theme-toggle]').click();

    expect(document.documentElement.dataset.theme).toBe('caramellatte');
    expect(document.documentElement.classList.contains('dark')).toBe(false);
    expect(document.querySelector('[data-theme-toggle]').getAttribute('aria-pressed')).toBe('false');
    expect(window.localStorage.getItem('mary-theme')).toBe('"caramellatte"');
    expect(window.localStorage.getItem('mary-class')).toBe('""');
  });

  test('exposes Leaflet globally', async () => {
    await loadApp(true);
    expect(window.L).toEqual({});
  });

  test('registers all Alpine plugins required by the layout', async () => {
    const { mockAlpine, plugins } = await loadApp(true);

    expect(mockAlpine.plugin).toHaveBeenCalledTimes(4);
    expect(mockAlpine.plugin).toHaveBeenNthCalledWith(1, plugins.anchorPlugin);
    expect(mockAlpine.plugin).toHaveBeenNthCalledWith(2, plugins.focusPlugin);
    expect(mockAlpine.plugin).toHaveBeenNthCalledWith(3, plugins.persistPlugin);
    expect(mockAlpine.plugin).toHaveBeenNthCalledWith(4, plugins.collapsePlugin);
  });

  test('does not re-register Alpine plugins when Livewire Alpine already exists', async () => {
    const livewireAlpine = {
      plugin: jest.fn(),
      start: jest.fn(),
      persist: jest.fn(),
      version: '3.15.4-livewire',
    };

    const { mockAlpine } = await loadApp(true, { existingAlpine: livewireAlpine });

    expect(mockAlpine.plugin).not.toHaveBeenCalled();
    expect(mockAlpine.start).not.toHaveBeenCalled();
    expect(livewireAlpine.plugin).not.toHaveBeenCalled();
    expect(livewireAlpine.start).not.toHaveBeenCalled();
  });
});
