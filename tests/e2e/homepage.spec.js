import { test, expect } from '@playwright/test';

test('homepage shows MADDRAX Fanclub title', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveTitle(/MADDRAX Fanclub/);
});
