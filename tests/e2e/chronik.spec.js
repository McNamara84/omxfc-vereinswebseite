import { test, expect } from '@playwright/test';

test('chronik page displays timeline images with alt text', async ({ page }) => {
  await page.goto('/chronik');
  await expect(page.locator('h1')).toContainText('Chronik');
  await expect(page.locator('img[alt="Gr\u00fcndungsversammlung in Berlin 2023"]')).toBeVisible();
});
