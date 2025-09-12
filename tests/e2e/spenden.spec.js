import { test, expect } from '@playwright/test';

test('spenden page contains PayPal donate button', async ({ page }) => {
  await page.goto('/spenden');
  await expect(page.getByRole('button', { name: 'Spenden mit PayPal' })).toBeVisible();
});
