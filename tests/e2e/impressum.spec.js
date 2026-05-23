import { test, expect } from './test-support.js';

test('impressum page shows contact email link', async ({ page }) => {
  await page.goto('/impressum');
  await expect(page).toHaveURL(/\/impressum$/);
  const link = page.getByRole('link', { name: 'vorstand@maddrax-fanclub.de' });
  await expect(link).toHaveAttribute('href', 'mailto:vorstand@maddrax-fanclub.de');
});
