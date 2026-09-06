import { vi } from 'vitest';

async function runBootstrap(matches, storage = {}) {
  vi.resetModules();
  window.localStorage.clear();
  document.documentElement.className = '';
  document.documentElement.dataset.theme = 'caramellatte';

  for (const [key, value] of Object.entries(storage)) {
    window.localStorage.setItem(key, value);
  }

  window.matchMedia = vi.fn().mockReturnValue({ matches });
  await import('../../resources/js/theme/bootstrap-inline.js');
}

describe('pre-paint theme bootstrap', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('applies and prepares the dark system theme without marking it explicit', async () => {
    await runBootstrap(true);

    expect(document.documentElement.dataset.theme).toBe('coffee');
    expect(document.documentElement.classList.contains('dark')).toBe(true);
    expect(window.localStorage.getItem('mary-theme')).toBe('"coffee"');
    expect(window.localStorage.getItem('mary-class')).toBe('"dark"');
    expect(window.localStorage.getItem('omxfc-theme-explicit')).toBe('0');
  });

  it('treats an existing maryUI value as an explicit preference', async () => {
    await runBootstrap(true, {
      'mary-theme': '"caramellatte"',
      'mary-class': '""',
    });

    expect(document.documentElement.dataset.theme).toBe('caramellatte');
    expect(document.documentElement.classList.contains('dark')).toBe(false);
    expect(window.localStorage.getItem('omxfc-theme-explicit')).toBe('1');
  });

  it('migrates the former theme key and preserves the explicit choice', async () => {
    await runBootstrap(false, { theme: 'dark' });

    expect(document.documentElement.dataset.theme).toBe('coffee');
    expect(document.documentElement.classList.contains('dark')).toBe(true);
    expect(window.localStorage.getItem('theme')).toBeNull();
    expect(window.localStorage.getItem('omxfc-theme-explicit')).toBe('1');
  });

  it('continues following system changes for an automatically persisted value', async () => {
    await runBootstrap(false, {
      'mary-theme': '"caramellatte"',
      'mary-class': '""',
      'omxfc-theme-explicit': '0',
    });

    window.matchMedia = vi.fn().mockReturnValue({ matches: true });
    expect(window.__omxfcApplyStoredTheme()).toBe(true);
    expect(document.documentElement.dataset.theme).toBe('coffee');
  });

  it.each([
    {
      systemIsDark: true,
      storedTheme: 'caramellatte',
      storedClass: '',
      expectedTheme: 'coffee',
      expectedClass: 'dark',
    },
    {
      systemIsDark: false,
      storedTheme: 'coffee',
      storedClass: 'dark',
      expectedTheme: 'caramellatte',
      expectedClass: '',
    },
  ])(
    'refreshes an automatically persisted $storedTheme theme from the current system preference',
    async ({ systemIsDark, storedTheme, storedClass, expectedTheme, expectedClass }) => {
      await runBootstrap(systemIsDark, {
        'mary-theme': JSON.stringify(storedTheme),
        'mary-class': JSON.stringify(storedClass),
        'omxfc-theme-explicit': '0',
      });

      expect(document.documentElement.dataset.theme).toBe(expectedTheme);
      expect(document.documentElement.classList.contains('dark')).toBe(systemIsDark);
      expect(window.localStorage.getItem('mary-theme')).toBe(JSON.stringify(expectedTheme));
      expect(window.localStorage.getItem('mary-class')).toBe(JSON.stringify(expectedClass));
      expect(window.localStorage.getItem('omxfc-theme-explicit')).toBe('0');
    },
  );
});
