import { vi } from 'vitest';


const originalMatchMedia = window.matchMedia;
const originalL = window.L;
const originalAlpine = window.Alpine;

afterEach(() => {
  window.matchMedia = originalMatchMedia;
  window.L = originalL;
  window.Alpine = originalAlpine;
  vi.restoreAllMocks();
});

async function loadApp(matches, { existingAlpine, bodyHtml = '' } = {}) {
  vi.resetModules();
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
  window.matchMedia = vi.fn().mockReturnValue({
    matches,
    addEventListener: (event, cb) => {
      if (event === 'change') handler = cb;
    },
  });
  vi.doMock('../../resources/js/bootstrap.js', () => ({}));
  vi.doMock('../../resources/js/features/index.js', () => ({}));
  vi.doMock('../../resources/js/alpine/char-editor.js', () => ({}));
  vi.doMock('../../resources/js/alpine/hoerbuch-role-repeater.js', () => ({}));
  vi.doMock('leaflet', () => ({ default: {} }));

  const anchorPlugin = { name: 'anchor' };
  const focusPlugin = { name: 'focus' };
  const persistPlugin = { name: 'persist' };
  const collapsePlugin = { name: 'collapse' };

  // Mock Alpine.js and its plugins
  const mockAlpine = {
    plugin: vi.fn(),
    start: vi.fn(),
    _x_dataStack: undefined,
    version: '3.15.4',
  };
  vi.doMock('alpinejs', () => ({ default: mockAlpine }));
  vi.doMock('@alpinejs/anchor', () => ({ default: anchorPlugin }));
  vi.doMock('@alpinejs/focus', () => ({ default: focusPlugin }));
  vi.doMock('@alpinejs/persist', () => ({ default: persistPlugin }));
  vi.doMock('@alpinejs/collapse', () => ({ default: collapsePlugin }));

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
  test('restores explicit light theme and ignores unrelated storage changes', async () => {
    const { handler } = await loadApp(true);
    window.localStorage.setItem('mary-theme', JSON.stringify('caramellatte'));
    window.localStorage.setItem('omxfc-theme-explicit', '1');
    window.__omxfcApplyStoredTheme();
    handler({ matches: true });
    expect(document.documentElement.dataset.theme).toBe('caramellatte');
    window.dispatchEvent(new StorageEvent('storage', { key: 'unrelated' }));
    expect(document.documentElement.dataset.theme).toBe('caramellatte');
  });

  test('falls back to system preference on malformed storage and events without matches', async () => {
    const { handler } = await loadApp(true);
    window.localStorage.setItem('mary-theme', '{broken');
    window.__omxfcApplyStoredTheme();
    handler({});
    expect(document.documentElement.dataset.theme).toBe('coffee');
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => { throw new Error('denied'); });
    window.__omxfcApplyStoredTheme();
    handler({ matches: false });
    expect(document.documentElement.dataset.theme).toBe('caramellatte');
  });

  test('bridges object and array Livewire toast payloads to maryUI', async () => {
    await loadApp(false);
    const callbacks = [];
    window.Livewire = { hook: vi.fn(), on: vi.fn((name, callback) => callbacks.push(callback)) };
    window.toast = vi.fn();
    document.dispatchEvent(new Event('livewire:init'));
    const toast = callbacks.at(-1);
    toast({ type: 'success', title: 'Gespeichert', description: 'Fertig', timeout: 42, position: 'toast-top' });
    expect(window.toast).toHaveBeenLastCalledWith({ toast: expect.objectContaining({ title: 'Gespeichert', css: 'alert-success', timeout: 42 }) });
    toast([{}]);
    expect(window.toast).toHaveBeenLastCalledWith({ toast: expect.objectContaining({ title: '', css: 'alert-info', timeout: 3000 }) });
    toast({ type: 'unknown' });
    delete window.toast;
    expect(() => toast({})).not.toThrow();
    delete window.Livewire;
  });
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
      bodyHtml: '<button data-theme-toggle-trigger aria-pressed="false"></button>',
    });

    const trigger = document.querySelector('[data-theme-toggle-trigger]');
    expect(trigger.getAttribute('aria-pressed')).toBe('true');
    expect(trigger.getAttribute('aria-label')).toBe('Helles Design aktivieren');
    expect(trigger.getAttribute('data-tip')).toBe('Helles Design aktivieren');
  });

  test('syncs theme toggle pressed state when system preference changes', async () => {
    const { handler } = await loadApp(false, {
      bodyHtml: '<button data-theme-toggle-trigger aria-pressed="false"></button>',
    });

    expect(document.querySelector('[data-theme-toggle-trigger]').getAttribute('aria-pressed')).toBe('false');

    handler({ matches: true });

    expect(document.documentElement.dataset.theme).toBe('coffee');
    expect(document.querySelector('[data-theme-toggle-trigger]').getAttribute('aria-pressed')).toBe('true');
  });

  test('keeps maryUI internal state aligned with system preference changes', async () => {
    const syncEvents = [];
    window.addEventListener('omxfc-theme-sync', (event) => syncEvents.push(event.detail), { once: true });

    const { handler } = await loadApp(false);
    handler({ matches: true });

    expect(syncEvents).toEqual([{ theme: 'coffee', class: 'dark' }]);
  });

  test('syncs theme toggle pressed state after storage theme updates', async () => {
    await loadApp(false, {
      bodyHtml: '<button data-theme-toggle-trigger aria-pressed="false"></button>',
    });

    window.localStorage.setItem('mary-theme', JSON.stringify('coffee'));
    window.localStorage.setItem('mary-class', JSON.stringify('dark'));
    window.localStorage.setItem('omxfc-theme-explicit', '1');

    const event = new Event('storage');
    Object.defineProperty(event, 'key', { value: 'mary-theme' });
    window.dispatchEvent(event);

    expect(document.documentElement.dataset.theme).toBe('coffee');
    expect(document.documentElement.classList.contains('dark')).toBe(true);
    expect(document.querySelector('[data-theme-toggle-trigger]').getAttribute('aria-pressed')).toBe('true');
  });

  test('syncs newly rendered theme toggles after livewire navigation', async () => {
    await loadApp(false);

    document.documentElement.dataset.theme = 'coffee';
    document.documentElement.classList.add('dark');
    document.body.innerHTML = '<button data-theme-toggle-trigger aria-pressed="false"></button>';
    document.dispatchEvent(new Event('livewire:navigated'));

    expect(document.querySelector('[data-theme-toggle-trigger]').getAttribute('aria-pressed')).toBe('true');
  });

  test('syncs the visible trigger when maryUI announces a theme change', async () => {
    await loadApp(false, {
      bodyHtml: '<button data-theme-toggle-trigger aria-pressed="false"></button>',
    });

    document.documentElement.dataset.theme = 'coffee';
    document.documentElement.classList.add('dark');
    window.dispatchEvent(new CustomEvent('theme-changed', { detail: 'coffee' }));

    const trigger = document.querySelector('[data-theme-toggle-trigger]');
    expect(trigger.getAttribute('aria-pressed')).toBe('true');
    expect(trigger.getAttribute('aria-label')).toBe('Helles Design aktivieren');
  });

  test('does not register a second click writer beside maryUI', async () => {
    await loadApp(false, {
      bodyHtml: '<button data-theme-toggle-trigger aria-pressed="false"></button>',
    });

    document.querySelector('[data-theme-toggle-trigger]').click();

    expect(document.documentElement.dataset.theme).toBe('caramellatte');
    expect(document.documentElement.classList.contains('dark')).toBe(false);
    expect(window.localStorage.getItem('mary-theme')).toBeNull();
    expect(window.localStorage.getItem('mary-class')).toBeNull();
  });

  test('does not load Leaflet on ordinary pages', async () => {
    await loadApp(true);
    expect(window.L).toBeUndefined();
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
      plugin: vi.fn(),
      start: vi.fn(),
      persist: vi.fn(),
      version: '3.15.4-livewire',
    };

    const { mockAlpine } = await loadApp(true, { existingAlpine: livewireAlpine });

    expect(mockAlpine.plugin).not.toHaveBeenCalled();
    expect(mockAlpine.start).not.toHaveBeenCalled();
    expect(livewireAlpine.plugin).not.toHaveBeenCalled();
    expect(livewireAlpine.start).not.toHaveBeenCalled();
  });
});
