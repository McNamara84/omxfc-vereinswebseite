import { test, expect } from '@playwright/test';

test('navbar link navigates to Mitglied werden page', async ({ page }) => {
  await page.goto('/');
  await page.getByTestId('nav-featured-links').getByRole('link', { name: 'Mitglied werden' }).click();
  await expect(page).toHaveURL(/\/mitglied-werden$/);
  await expect(page.getByRole('heading', { level: 1, name: 'Mitglied werden' })).toBeVisible();
});

test('mobile navigation groups quick links and sections', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/');

  const menuToggle = page.locator('button[aria-controls="mobile-navigation"]');
  await expect(menuToggle).toHaveAccessibleName(/Menü öffnen/i);
  await menuToggle.click();
  await expect(menuToggle).toHaveAttribute('aria-expanded', 'true');
  await expect(menuToggle).toContainText(/Schließen/i);

  await expect(page.getByTestId('mobile-navigation-menu')).toBeVisible();
  await expect(page.getByTestId('mobile-nav-featured-heading')).toContainText('Schnellzugriff');
  await expect(page.getByTestId('mobile-nav-sections-heading')).toContainText('Bereiche');
  await expect(page.getByRole('link', { name: 'Mitglied werden' }).first()).toBeVisible();
});
