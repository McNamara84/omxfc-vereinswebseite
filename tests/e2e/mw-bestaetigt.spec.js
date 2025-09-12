import { test, expect } from '@playwright/test';

test('mitglied werden bestaetigt page thanks user', async ({ page }) => {
  await page.goto('/mitglied-werden/bestaetigt');
  await expect(page.getByRole('heading', { level: 1, name: 'Vielen Dank für deine Bestätigung!' })).toBeVisible();
  await expect(page).toHaveURL(/\/mitglied-werden\/bestaetigt$/);
});
