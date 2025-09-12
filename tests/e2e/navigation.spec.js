import { test, expect } from '@playwright/test';

test('navbar link navigates to Mitglied werden page', async ({ page }) => {
  await page.goto('/');
  await page.getByRole('link', { name: 'Mitglied werden' }).first().click();
  await expect(page).toHaveURL(/\/mitglied-werden$/);
  await expect(page.getByRole('heading', { level: 1, name: 'Mitglied werden' })).toBeVisible();
});
