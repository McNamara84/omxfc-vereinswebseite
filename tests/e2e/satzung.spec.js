import { test, expect } from '@playwright/test';

test('satzung page displays correct main heading', async ({ page }) => {
  await page.goto('/satzung');
  await expect(page).toHaveURL(/\/satzung$/);
  await expect(page.getByRole('heading', { level: 1, name: 'Satzung des Offiziellen MADDRAX Fanclub e.V.' })).toBeVisible();
});
