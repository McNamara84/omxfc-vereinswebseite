import { test, expect } from '@playwright/test';

test('arbeitsgruppen page shows heading for public teams', async ({ page }) => {
  await page.goto('/arbeitsgruppen');
  await expect(page).toHaveURL(/\/arbeitsgruppen$/);
  await expect(page.getByRole('heading', { level: 1, name: 'Arbeitsgruppen des OMXFC e.V.' })).toBeVisible();
});
