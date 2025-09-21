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

    const currentTheme = await page.evaluate(() => document.documentElement.dataset.theme);
    expect(currentTheme).toBe('dark');
  });

  test('prioritises stored light preference over system dark mode', async ({ page }) => {
    await page.addInitScript(() => {
      window.localStorage.setItem('theme', 'light');
    });

    await page.goto('/');
    const hasDarkClass = await page.evaluate(() => document.documentElement.classList.contains('dark'));
    expect(hasDarkClass).toBe(false);

    const currentTheme = await page.evaluate(() => document.documentElement.dataset.theme);
    expect(currentTheme).toBe('light');

    await page.evaluate(() => window.localStorage.removeItem('theme'));
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
      window.localStorage.removeItem('theme');
    });

    await page.goto('/');
    await page.waitForFunction(() => typeof window.__omxfcApplySystemTheme === 'function');
    let hasDarkClass = await page.evaluate(() => document.documentElement.classList.contains('dark'));
    expect(hasDarkClass).toBe(false);

    const applied = await page.evaluate(() => window.__omxfcApplySystemTheme?.(true));

    expect(applied).toBe(true);

    const currentTheme = await page.evaluate(() => document.documentElement.dataset.theme);
    expect(currentTheme).toBe('dark');
  });

  test('reacts to storage theme updates', async ({ page }) => {
    await page.addInitScript(() => {
      window.localStorage.removeItem('theme');
    });

    await page.goto('/');
    await page.waitForFunction(() => typeof window.__omxfcApplyStoredTheme === 'function');
    const applied = await page.evaluate(() => {
      window.localStorage.setItem('theme', 'dark');
      return window.__omxfcApplyStoredTheme?.();
    });

    expect(applied).toBe(true);

    const currentTheme = await page.evaluate(() => document.documentElement.dataset.theme);
    expect(currentTheme).toBe('dark');

    const reverted = await page.evaluate(() => {
      window.localStorage.setItem('theme', 'light');
      return window.__omxfcApplyStoredTheme?.();
    });

    expect(reverted).toBe(false);

    const revertedTheme = await page.evaluate(() => document.documentElement.dataset.theme);
    expect(revertedTheme).toBe('light');

    await page.evaluate(() => window.localStorage.removeItem('theme'));
  });

  test('light mode keeps sufficient color contrast in main content', async ({ page }) => {
    await page.goto('/');
    const results = await analyzeContrast(page);
    expect(results.violations).toEqual([]);
  });
});
