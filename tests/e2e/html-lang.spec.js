import { test, expect } from '@playwright/test';

test('html element declares German language for accessibility', async ({ page }) => {
  await page.goto('/');
  await expect(page.locator('html')).toHaveAttribute('lang', 'de');
});
