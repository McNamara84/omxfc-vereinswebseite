import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const analyzeContrast = async (page) =>
  new AxeBuilder({ page })
    .include('main')
    .withTags(['cat.color'])
    .withRules(['color-contrast'])
    .analyze();

test.describe('dark mode respects preferences', () => {
  test.use({ colorScheme: 'dark' });

  test('applies dark class immediately on load when system prefers dark', async ({ page }) => {
    const response = await page.goto('/');
    expect(response?.ok()).toBeTruthy();

    const hasDarkClass = await page.evaluate(() => document.documentElement.classList.contains('dark'));
    expect(hasDarkClass).toBe(true);

    // daisyUI uses 'coffee' theme for dark mode
    const currentTheme = await page.evaluate(() => document.documentElement.dataset.theme);
    expect(currentTheme).toBe('coffee');
  });

  test('prioritises stored light preference over system dark mode', async ({ page }) => {
    await page.addInitScript(() => {
      window.localStorage.setItem('mary-theme', JSON.stringify('caramellatte'));
      window.localStorage.setItem('mary-class', JSON.stringify(''));
    });

    await page.goto('/');
    const hasDarkClass = await page.evaluate(() => document.documentElement.classList.contains('dark'));
    expect(hasDarkClass).toBe(false);

    // daisyUI uses 'caramellatte' theme for light mode
    const currentTheme = await page.evaluate(() => document.documentElement.dataset.theme);
    expect(currentTheme).toBe('caramellatte');

    await page.evaluate(() => {
      window.localStorage.removeItem('mary-theme');
      window.localStorage.removeItem('mary-class');
    });
  });

  test('dark mode keeps sufficient color contrast in main content', async ({ page }) => {
    await page.goto('/');
    const results = await analyzeContrast(page);
    expect(results.violations).toEqual([]);
  });
});

test.describe('system preference change handling', () => {
  test.use({ colorScheme: 'light' });

  test('updates theme when system preference changes and no stored preference exists', async ({ page }) => {
    await page.addInitScript(() => {
      window.localStorage.removeItem('mary-theme');
      window.localStorage.removeItem('mary-class');
    });

    await page.goto('/');
    let hasDarkClass = await page.evaluate(() => document.documentElement.classList.contains('dark'));
    expect(hasDarkClass).toBe(false);

    // Systempräferenz auf Dark ändern via Playwright
    await page.emulateMedia({ colorScheme: 'dark' });

    // Warten bis der Change-Handler reagiert hat
    await page.waitForFunction(() => document.documentElement.classList.contains('dark'));

    // daisyUI uses 'coffee' theme for dark mode
    const currentTheme = await page.evaluate(() => document.documentElement.dataset.theme);
    expect(currentTheme).toBe('coffee');
  });

  test('reacts to storage theme updates', async ({ page }) => {
    await page.addInitScript(() => {
      window.localStorage.removeItem('mary-theme');
      window.localStorage.removeItem('mary-class');
    });

    await page.goto('/');
    await page.waitForFunction(() => typeof window.__omxfcApplyStoredTheme === 'function');
    const applied = await page.evaluate(() => {
      window.localStorage.setItem('mary-theme', JSON.stringify('coffee'));
      window.localStorage.setItem('mary-class', JSON.stringify('dark'));
      return window.__omxfcApplyStoredTheme?.();
    });

    expect(applied).toBe(true);

    // daisyUI uses 'coffee' theme for dark mode
    const currentTheme = await page.evaluate(() => document.documentElement.dataset.theme);
    expect(currentTheme).toBe('coffee');

    const reverted = await page.evaluate(() => {
      window.localStorage.setItem('mary-theme', JSON.stringify('caramellatte'));
      window.localStorage.setItem('mary-class', JSON.stringify(''));
      return window.__omxfcApplyStoredTheme?.();
    });

    expect(reverted).toBe(false);

    // daisyUI uses 'caramellatte' theme for light mode
    const revertedTheme = await page.evaluate(() => document.documentElement.dataset.theme);
    expect(revertedTheme).toBe('caramellatte');

    await page.evaluate(() => {
      window.localStorage.removeItem('mary-theme');
      window.localStorage.removeItem('mary-class');
    });
  });

  test('light mode keeps sufficient color contrast in main content', async ({ page }) => {
    await page.goto('/');
    const results = await analyzeContrast(page);
    expect(results.violations).toEqual([]);
  });
});
