import { test, expect } from '@playwright/test';

test('navbar shows login link for guests', async ({ page }) => {
  await page.goto('/');
  const link = page.getByRole('link', { name: 'Login' }).first();
  await expect(link).toHaveAttribute('href', /\/login$/);
});
