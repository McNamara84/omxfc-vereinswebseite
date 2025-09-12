import { test, expect } from '@playwright/test';

test('datenschutz page has data protection contact email', async ({ page }) => {
  await page.goto('/datenschutz');
  await expect(page).toHaveURL(/\/datenschutz$/);
  const link = page.getByRole('link', { name: 'omxfc.vorstand@gmail.com' });
  await expect(link).toHaveAttribute('href', 'mailto:omxfc.vorstand@gmail.com');
});
