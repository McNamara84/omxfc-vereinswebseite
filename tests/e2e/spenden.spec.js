import { test, expect } from '@playwright/test';

test('spenden page contains PayPal donate button', async ({ page }) => {
  await page.goto('/spenden');
  const button = page.locator('input[alt="Spenden mit PayPal"]');
  await expect(button).toBeVisible();
});
