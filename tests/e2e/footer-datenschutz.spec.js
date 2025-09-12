import { test, expect } from '@playwright/test';

test('footer link navigates to Datenschutz page', async ({ page }) => {
  await page.goto('/');
  await page.getByRole('link', { name: 'Datenschutz' }).click();
  await expect(page).toHaveURL(/\/datenschutz$/);
  await expect(page.getByRole('heading', { level: 1, name: 'Datenschutz' })).toBeVisible();
});
