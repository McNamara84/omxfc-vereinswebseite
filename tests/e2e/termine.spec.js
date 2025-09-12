import { test, expect } from '@playwright/test';

test('termine page contains link to Google Kalender', async ({ page }) => {
  await page.goto('/termine');
  await expect(page).toHaveURL(/\/termine$/);
  const link = page.getByRole('link', { name: 'Google Kalender' });
  await expect(link).toHaveAttribute('href', /https?:\/\/calendar\.google\.com/);
});
