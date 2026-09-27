import { expect, test, clickAndWaitForLivewireUpdate } from './test-support.js';
import { runArtisan } from './utils/artisan.js';

test('Bestätigte Teilnahme wird beim Archivieren einmalig vergütet und gesperrt', async ({ page }) => {
    test.setTimeout(120_000);
    await runArtisan(['db:seed', '--class=Database\\Seeders\\VeranstaltungsBaxxPlaywrightSeeder']);
    await page.goto('/login');
    await page.locator('input[name="email"]').fill('info@maddraxikon.com');
    await page.locator('input[name="password"]').fill('password');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));

    const editUrl = '/admin/veranstaltungen/baxx-browserpruefung/bearbeiten';
    const listUrl = '/admin/veranstaltungen/baxx-browserpruefung/anmeldungen';
    await page.goto(editUrl);
    const amount = page.getByLabel('Baxx für bestätigte Teilnahme');
    await expect(amount).toHaveValue('10');
    await amount.fill('23');
    await page.getByRole('button', { name: 'Änderungen speichern', exact: true }).click();
    await page.waitForLoadState('networkidle');
    await page.reload();
    await expect(amount).toHaveValue('23');

    await page.goto(listUrl);
    const attendance = page.getByRole('checkbox', { name: 'Teilnahme von Baxx Anwesend bestätigen', exact: true });
    await clickAndWaitForLivewireUpdate(page, attendance);
    await expect(attendance).toBeChecked();
    await expect(page.getByText('Voraussichtliche Vergabe: 1 Mitglieder, insgesamt 23 Baxx.')).toBeVisible();
    // Das Entfernen und erneute Bestätigen muss auch im echten Browser funktionieren.
    await clickAndWaitForLivewireUpdate(page, attendance);
    await expect(attendance).not.toBeChecked();
    await clickAndWaitForLivewireUpdate(page, attendance);
    await expect(attendance).toBeChecked();
    await page.reload();
    await expect(attendance).toBeChecked();

    await page.goto(editUrl);
    await page.getByRole('combobox', { name: 'Status', exact: true }).selectOption('archiviert');
    page.once('dialog', (dialog) => dialog.dismiss());
    await page.getByRole('button', { name: 'Änderungen speichern', exact: true }).click();
    await expect(page.getByLabel('Baxx für bestätigte Teilnahme')).toBeVisible();
    page.once('dialog', async (dialog) => {
        expect(dialog.message()).toContain('23 Baxx');
        await dialog.accept();
    });
    await page.getByRole('button', { name: 'Änderungen speichern', exact: true }).click();
    await expect(page.getByText(/Vergabe abgeschlossen am/)).toBeVisible();
    await expect(page.locator('input[name="teilnahme_baxx"]')).toHaveCount(0);

    await page.goto(listUrl);
    await expect(page.getByText('Vergabe abgeschlossen: 1 Mitglieder, insgesamt 23 Baxx.')).toBeVisible();
    await expect(page.getByRole('checkbox')).toHaveCount(0);
    await expect(page.getByText('23 Baxx vergeben', { exact: true })).toHaveCount(1);
    const absentRow = page.getByRole('row').filter({ hasText: 'Baxx Unbestaetigt' });
    await expect(absentRow).toContainText('Nicht bestätigt');
    await expect(absentRow).toContainText('Keine Gutschrift');

    await page.goto(editUrl);
    await page.getByRole('button', { name: 'Änderungen speichern', exact: true }).click();
    await page.goto(listUrl);
    await expect(page.getByText('Vergabe abgeschlossen: 1 Mitglieder, insgesamt 23 Baxx.')).toBeVisible();
});
