import { test, expect } from '@playwright/test';

test('impressum page shows contact email link', async ({ page }) => {
  await page.goto('/impressum');
  const link = page.locator('a[href="mailto:info@maddrax-fanclub.de"]');
  await expect(link).toBeVisible();
});
