import { test, expect } from '@playwright/test';

test('ehrenmitglieder page shows honorary member image with alt text', async ({ page }) => {
  await page.goto('/ehrenmitglieder');
  await expect(page).toHaveURL(/\/ehrenmitglieder$/);
  await expect(page.getByAltText('Michael Edelbrock')).toBeVisible();
});
