import { test, expect } from './test-support.js';

test('footer link navigates to Changelog page', async ({ page }) => {
  await page.goto('/');
  await page.getByRole('contentinfo').getByRole('link', { name: 'Changelog' }).click();
  await expect(page).toHaveURL(/\/changelog$/);
  await expect(page.getByRole('heading', { level: 1, name: 'Changelog' })).toBeVisible();
});
