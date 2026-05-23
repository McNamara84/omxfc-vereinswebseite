import { test, expect } from './test-support.js';

test('html element declares German language for accessibility', async ({ page }) => {
  await page.goto('/');
  await expect(page.locator('html')).toHaveAttribute('lang', 'de');
});
