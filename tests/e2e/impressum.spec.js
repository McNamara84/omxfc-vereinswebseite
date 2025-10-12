import { test, expect } from '@playwright/test';

test('impressum page shows contact email link', async ({ page }) => {
  await page.goto('/impressum');
  await expect(page).toHaveURL(/\/impressum$/);
  const link = page.getByRole('link', { name: 'vorstand@maddrax-fanclub.de' });
  await expect(link).toHaveAttribute('href', 'mailto:vorstand@maddrax-fanclub.de');
});
