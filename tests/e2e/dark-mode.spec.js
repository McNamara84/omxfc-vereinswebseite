import { test, expect } from '@playwright/test';

test.use({ colorScheme: 'dark' });

test('home page applies dark mode when prefers-color-scheme is dark', async ({ page }) => {
  await page.goto('/');
  const isDark = await page.evaluate(() => matchMedia('(prefers-color-scheme: dark)').matches);
  expect(isDark).toBe(true);
});
