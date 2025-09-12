import { test, expect } from '@playwright/test';

test('home page applies dark mode when prefers-color-scheme is dark', async ({ page }) => {
  await page.emulateMedia({ colorScheme: 'dark' });
  await page.goto('/');
  await expect(page.locator('html')).toHaveClass(/dark/);
});
