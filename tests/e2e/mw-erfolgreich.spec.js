import { test, expect } from '@playwright/test';

test('mitglied werden erfolgreich page displays confirmation and home link', async ({ page }) => {
  await page.goto('/mitglied-werden/erfolgreich');
  await expect(page.getByRole('heading', { name: 'Antrag erfolgreich eingereicht!' })).toBeVisible();
  const homeLink = page.getByRole('link', { name: 'Zurück zur Startseite' });
  await expect(homeLink).toHaveAttribute('href', '/');
});
