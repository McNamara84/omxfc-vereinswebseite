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

test.describe('Veranstaltungsanmeldung', () => {
    test('Seite ist erreichbar und zeigt das Anmeldeformular', async ({ page }) => {
        await gotoFantreffenAnmeldung(page);

        // Hauptüberschrift sichtbar
        await expect(page.locator('h1')).toBeVisible();

        // Anmeldeformular mit Überschrift vorhanden
        await expect(page.locator('h2:has-text("Anmeldung")')).toBeVisible();
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

    test('T-Shirt Checkbox schaltet Größen-Dropdown korrekt um', async ({ page }) => {
        await gotoFantreffenAnmeldung(page);

        const tshirtContainer = page.getByTestId('fantreffen-tshirt-container');
        const checkbox = page.getByTestId('fantreffen-tshirt-checkbox');

        // Container ist initial versteckt (Alpine.js x-show setzt display:none)
        await expect(tshirtContainer).toBeHidden();

        // Checkbox anklicken → Container sichtbar
        await checkbox.check();
        await expect(tshirtContainer).toBeVisible();

        // Größen-Select ist jetzt required
        const select = page.getByTestId('fantreffen-tshirt-groesse');
        await expect(select).toHaveAttribute('required');

        // Checkbox abwählen → Container wird wieder versteckt
        await checkbox.uncheck();
        await expect(tshirtContainer).toBeHidden();
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

    test('T-Shirt-Größe blockiert das Formular nicht wenn Checkbox nicht gesetzt', async ({ page }) => {
        await gotoFantreffenAnmeldung(page);

        // tshirt_groesse darf nicht required sein wenn Checkbox nicht gesetzt
        const selectRequired = await page.evaluate(() => {
            const select = document.querySelector('select[name="tshirt_groesse"]');
            return select?.required ?? null;
        });

        expect(selectRequired).toBe(false);
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
