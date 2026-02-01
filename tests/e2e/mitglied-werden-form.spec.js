import { test, expect } from '@playwright/test';

async function fillRequiredFields(page) {
  await page.getByLabel('Vorname').fill('Max');
  await page.getByLabel('Nachname').fill('Mustermann');
  await page.getByLabel('Straße').fill('Musterstraße');
  await page.getByLabel('Hausnummer').fill('1');
  await page.getByLabel('Postleitzahl').fill('12345');
  await page.getByLabel('Stadt').fill('Musterstadt');
  await page.getByLabel('Land').selectOption('Deutschland');
  await page.getByLabel('Mailadresse').fill('max@example.com');
  await page.getByLabel('Passwort', { exact: true }).fill('geheimespasswort');
  await page.getByLabel('Passwort wiederholen').fill('geheimespasswort');
}

test('form requires acceptance of Satzung before enabling submit', async ({ page }) => {
  await page.goto('/mitglied-werden');
  await fillRequiredFields(page);
  const submitButton = page.getByRole('button', { name: 'Antrag absenden' });
  await expect(submitButton).toBeDisabled();
  await page.locator('#satzung_check').check();
  await expect(submitButton).toBeEnabled();
});

test('updates membership fee output when slider changes', async ({ page }) => {
  await page.goto('/mitglied-werden');
  const output = page.locator('#beitrag-output');
  await expect(output).toHaveText('12€');
  await page.locator('#mitgliedsbeitrag').fill('60');
  await expect(output).toHaveText('60€');
});

test('shows error message for invalid email address', async ({ page }) => {
  await page.goto('/mitglied-werden');
  const email = page.getByLabel('Mailadresse');
  await email.fill('not-an-email');
  await email.blur();
  await expect(page.locator('#mail-error')).toHaveText('Bitte gültige Mailadresse eingeben.');
});
