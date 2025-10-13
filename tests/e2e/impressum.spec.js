import { test, expect } from '@playwright/test';

test('impressum page reveals contact email after captcha', async ({ page }) => {
  await page.goto('/impressum');
  await expect(page).toHaveURL(/\/impressum$/);

  const revealButton = page.getByRole('button', { name: 'E-Mail-Adresse anzeigen' });
  await expect(revealButton).toBeVisible();

  await page.evaluate(() => {
    window.dispatchEvent(new CustomEvent('mock-hcaptcha-token', { detail: { token: 'test-token' } }));
  });

  await revealButton.click();

  const emailLink = page.getByRole('link', { name: 'vorstand@maddrax-fanclub.de' });
  await expect(emailLink).toBeVisible();
  await expect(emailLink).toHaveAttribute('href', 'mailto:vorstand@maddrax-fanclub.de');
});
