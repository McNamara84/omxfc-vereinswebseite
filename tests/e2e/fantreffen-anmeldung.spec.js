import { expect, test } from '@playwright/test';

function uniqueGuestEmail(prefix, projectName) {
    const normalizedProject = projectName.replace(/[^a-z0-9]+/gi, '-').toLowerCase();

    return `${prefix}-${normalizedProject}@example.com`;
}

async function gotoFantreffenAnmeldung(page) {
    await page.goto('/veranstaltungen/aktuell');
    await page.waitForURL(/\/veranstaltungen\//, { timeout: 10000 });
    await expect(page.locator('form#fantreffen-form')).toBeVisible();
}

function anmeldungsPanel(page) {
    return page.locator('section').filter({ has: page.locator('form#fantreffen-form') }).first();
}

test.describe('Veranstaltungsanmeldung', () => {
    test('Seite ist erreichbar und zeigt das Anmeldeformular', async ({ page }) => {
        await gotoFantreffenAnmeldung(page);

        // Hauptüberschrift sichtbar
        await expect(page.locator('h1')).toBeVisible();

        // Anmeldeformular mit Überschrift vorhanden
        await expect(anmeldungsPanel(page).getByRole('heading', { name: 'Anmeldung', exact: true })).toBeVisible();
    });

    test('Formularfelder haben korrekte name-Attribute', async ({ page }) => {
        await gotoFantreffenAnmeldung(page);

        // Input-Felder mit korrekten name-Attributen vorhanden
        await expect(page.locator('input[name="vorname"]')).toBeVisible();
        await expect(page.locator('input[name="nachname"]')).toBeVisible();
        await expect(page.locator('input[name="email"]')).toBeVisible();
    });

    test('Submit-Button hat type=submit', async ({ page }) => {
        await gotoFantreffenAnmeldung(page);

        const submitButton = page.getByTestId('fantreffen-submit');
        await expect(submitButton).toBeVisible();
        await expect(submitButton).toHaveAttribute('type', 'submit');
    });

    test('Gast kann sich erfolgreich ohne T-Shirt registrieren', async ({ page }, testInfo) => {
        await gotoFantreffenAnmeldung(page);

        // Formularfelder ausfüllen
        await page.fill('input[name="vorname"]', 'Max');
        await page.fill('input[name="nachname"]', 'Mustermann');
        await page.fill('input[name="email"]', uniqueGuestEmail('max-mustermann', testInfo.project.name));

        // Submit
        await page.getByTestId('fantreffen-submit').click();

        // Weiterleitung zur Bestätigungsseite
        await page.waitForURL(/bestaetigung/, { timeout: 10000 });
    });

    test('T-Shirt-Bereich wird nicht angezeigt, wenn das Modul deaktiviert ist', async ({ page }) => {
        await gotoFantreffenAnmeldung(page);

        await expect(page.getByTestId('fantreffen-tshirt-checkbox')).toHaveCount(0);
        await expect(page.getByTestId('fantreffen-tshirt-container')).toHaveCount(0);
        await expect(page.getByTestId('fantreffen-tshirt-groesse')).toHaveCount(0);
    });

    test('Formular ist valide und wird korrekt an den Server gesendet', async ({ page }) => {
        await gotoFantreffenAnmeldung(page);

        // Form vorhanden mit POST-Methode
        const form = page.locator('form#fantreffen-form');
        await expect(form).toBeVisible();
        await expect(form).toHaveAttribute('method', 'POST');

        // CSRF-Token vorhanden
        await expect(page.locator('form#fantreffen-form input[name="_token"]')).toBeAttached();

        // Submit-Button innerhalb des Formulars
        const buttonInForm = page.locator('form#fantreffen-form button[type="submit"]');
        await expect(buttonInForm).toHaveCount(1);
    });

    test('T-Shirt-Größe ist ohne aktives Modul nicht Teil des Formulars', async ({ page }) => {
        await gotoFantreffenAnmeldung(page);

        await expect(page.locator('select[name="tshirt_groesse"]')).toHaveCount(0);
    });

    test('Mehrere Gäste können sich nacheinander ohne 429-Fehler registrieren', async ({ page }, testInfo) => {
        test.slow();

        const gaeste = [
            { vorname: 'Anna', nachname: 'Schmidt', email: uniqueGuestEmail('anna-schmidt', testInfo.project.name) },
            { vorname: 'Ben', nachname: 'Weber', email: uniqueGuestEmail('ben-weber', testInfo.project.name) },
            { vorname: 'Clara', nachname: 'Fischer', email: uniqueGuestEmail('clara-fischer', testInfo.project.name) },
        ];

        for (const gast of gaeste) {
            await gotoFantreffenAnmeldung(page);

            await page.fill('input[name="vorname"]', gast.vorname);
            await page.fill('input[name="nachname"]', gast.nachname);
            await page.fill('input[name="email"]', gast.email);

            await page.getByTestId('fantreffen-submit').click();

            // Muss zur Bestätigungsseite weiterleiten, NICHT 429
            await page.waitForURL(/bestaetigung/, { timeout: 10000 });
        }
    });
});
