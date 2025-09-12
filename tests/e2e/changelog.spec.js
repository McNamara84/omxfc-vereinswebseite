import { test, expect } from '@playwright/test';

test('changelog page displays heading and version information', async ({ page }) => {
  await page.goto('/changelog');
  await expect(page.getByRole('heading', { level: 1, name: 'Changelog' })).toBeVisible();
  await expect(page.locator('footer')).toContainText('Version');
});
