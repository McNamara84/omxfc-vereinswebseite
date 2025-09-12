import { test, expect } from '@playwright/test';

test('home page provides meta description for SEO', async ({ page }) => {
  await page.goto('/');
  const description = await page.locator('head meta[name="description"]').getAttribute('content');
  expect(description).toBeTruthy();
});
