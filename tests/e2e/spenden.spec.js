import { test, expect } from './test-support.js';

test('spenden page contains PayPal donate button', async ({ page }) => {
  await page.goto('/spenden');
  await expect(page).toHaveURL(/\/spenden$/);
  await expect(page.getByAltText('Spenden mit PayPal')).toBeVisible();
});
