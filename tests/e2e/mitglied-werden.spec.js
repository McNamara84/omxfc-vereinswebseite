import { test, expect } from '@playwright/test';

test('mitglied-werden form enables submit button after validation and terms acceptance', async ({ page }) => {
  await page.goto('/mitglied-werden');
  await expect(page).toHaveURL(/\/mitglied-werden$/);
  await page.getByLabel('Vorname').fill('Max');
  await page.getByLabel('Nachname').fill('Mustermann');
  await page.getByLabel('Straße').fill('Musterstraße');
  await page.getByLabel('Hausnummer').fill('1');
  await page.getByLabel('Postleitzahl').fill('12345');
  await page.getByLabel('Stadt').fill('Berlin');
  await page.getByLabel('Land').selectOption('Deutschland');
  await page.getByLabel('Mailadresse').fill('max@example.com');
  await page.getByLabel('Passwort').fill('Passwort1');
  await page.getByLabel('Passwort wiederholen').fill('Passwort1');
  const submitButton = page.getByRole('button', { name: 'Antrag absenden' });
  await expect(submitButton).toBeDisabled();
  await page.getByRole('checkbox', { name: /Satzung/ }).check();
  await expect(submitButton).toBeEnabled();
});
