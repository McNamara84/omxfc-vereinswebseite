import { test, expect } from '@playwright/test';

test('mitglied-werden form enables submit button after validation and terms acceptance', async ({ page }) => {
  await page.goto('/mitglied-werden');
  await expect(page).toHaveURL(/\/mitglied-werden$/);
  await page.locator('input[name="vorname"]').fill('Max');
  await page.locator('input[name="nachname"]').fill('Mustermann');
  await page.locator('input[name="strasse"]').fill('Musterstraße');
  await page.locator('input[name="hausnummer"]').fill('1');
  await page.locator('input[name="plz"]').fill('12345');
  await page.locator('input[name="stadt"]').fill('Berlin');
  await page.locator('select[name="land"]').selectOption('Deutschland');
  await page.locator('input[name="mail"]').fill('max@example.com');
  await page.locator('input[name="passwort"]').fill('Passwort1');
  await page.locator('input[name="passwort_confirmation"]').fill('Passwort1');
  const submitButton = page.getByRole('button', { name: 'Antrag absenden' });
  await expect(submitButton).toBeDisabled();
  await page.locator('#satzung_check').check();
  await expect(submitButton).toBeEnabled();
});
