import { test, expect } from '@playwright/test';

test('chronik page displays timeline images with alt text', async ({ page }) => {
  await page.goto('/chronik');
  await expect(page).toHaveURL(/\/chronik$/);
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Chronik');
  await expect(page.getByAltText('Gründungsversammlung in Berlin 2023')).toBeVisible();
});
