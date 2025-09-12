import { test, expect } from '@playwright/test';

test('spenden page contains PayPal donate button', async ({ page }) => {
  await page.goto('/');
  await page.getByRole('link', { name: 'Spenden' }).click();
  await expect(page).toHaveURL(/\/spenden/);
  await expect(page.getByRole('button', { name: 'Spenden mit PayPal' })).toBeVisible();
});
