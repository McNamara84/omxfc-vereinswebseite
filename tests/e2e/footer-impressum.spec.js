import { test, expect } from './test-support.js';

test('footer link navigates to Impressum page', async ({ page }) => {
  await page.goto('/');
  await page.getByRole('link', { name: 'Impressum' }).click();
  await expect(page).toHaveURL(/\/impressum$/);
  await expect(page.getByRole('heading', { level: 1, name: 'Impressum' })).toBeVisible();
});
