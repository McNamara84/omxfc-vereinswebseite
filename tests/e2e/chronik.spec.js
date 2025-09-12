import { test, expect } from '@playwright/test';

test('chronik page displays timeline images with alt text', async ({ page }) => {
  await page.goto('/chronik');
  await expect(page.getByRole('heading', { level: 1, name: /Chronik/ })).toBeVisible();
  await expect(page.getByAltText('Gr\u00fcndungsversammlung in Berlin 2023')).toBeVisible();
});
