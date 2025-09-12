import { test, expect } from '@playwright/test';

test('home page uses exactly one h1 heading', async ({ page }) => {
  await page.goto('/');
  await expect(page.locator('h1')).toHaveCount(1);
});
