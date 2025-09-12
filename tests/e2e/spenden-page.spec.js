import { test, expect } from '@playwright/test';

test('spenden page has accessible PayPal donate button', async ({ page }) => {
  await page.goto('/spenden');
  await expect(page.getByAltText('Spenden mit PayPal')).toBeVisible();
});
