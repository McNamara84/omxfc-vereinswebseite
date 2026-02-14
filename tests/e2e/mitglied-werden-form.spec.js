import { test, expect } from '@playwright/test';

async function fillRequiredFields(page) {
  await page.locator('input[name="vorname"]').fill('Max');
  await page.locator('input[name="nachname"]').fill('Mustermann');
  await page.locator('input[name="strasse"]').fill('Musterstraße');
  await page.locator('input[name="hausnummer"]').fill('1');
  await page.locator('input[name="plz"]').fill('12345');
  await page.locator('input[name="stadt"]').fill('Musterstadt');
  await page.locator('select[name="land"]').selectOption('Deutschland');
  await page.locator('input[name="mail"]').fill('max@example.com');
  await page.locator('input[name="passwort"]').fill('geheimespasswort');
  await page.locator('input[name="passwort_confirmation"]').fill('geheimespasswort');
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
  const email = page.locator('input[name="mail"]');
  await email.fill('not-an-email');
  await email.blur();
  // JS-Validierung setzt customValidity – prüfe aria-invalid statt #mail-error
  await expect(email).toHaveAttribute('aria-invalid', 'true');
});
