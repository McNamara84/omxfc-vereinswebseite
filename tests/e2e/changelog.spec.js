import { test, expect } from '@playwright/test';

test('changelog page displays heading and version information', async ({ page }) => {
  await page.goto('/changelog');
  await expect(page.locator('h1')).toHaveText('Changelog');
  await expect(page.locator('footer')).toContainText('Version');
});
