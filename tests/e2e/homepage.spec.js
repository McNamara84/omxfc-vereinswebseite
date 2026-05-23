import { test, expect } from './test-support.js';

test('homepage shows MADDRAX Fanclub title', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveTitle(/MADDRAX Fanclub/);
});
