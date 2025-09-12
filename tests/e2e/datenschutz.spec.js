import { test, expect } from '@playwright/test';

test('datenschutz page has data protection contact email', async ({ page }) => {
  await page.goto('/datenschutz');
  const link = page.locator('a[href="mailto:omxfc.vorstand@gmail.com"]');
  await expect(link).toBeVisible();
});
