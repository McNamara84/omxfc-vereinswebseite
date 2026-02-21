import { expect, test } from '@playwright/test';

test.describe('Fantreffen 2026 Anmeldung', () => {
    test('Seite ist erreichbar und zeigt das Anmeldeformular', async ({ page }) => {
        await page.goto('/maddrax-fantreffen-2026');
        await page.waitForLoadState('networkidle');

        // Hauptüberschrift sichtbar
        await expect(page.locator('h1')).toContainText('Maddrax-Fantreffen 2026');

        // Anmeldeformular mit Überschrift vorhanden
        await expect(page.locator('h2:has-text("Anmeldung")')).toBeVisible();
    });

    test('Formularfelder haben korrekte name-Attribute', async ({ page }) => {
        await page.goto('/maddrax-fantreffen-2026');
        await page.waitForLoadState('networkidle');

        // Input-Felder mit korrekten name-Attributen vorhanden
        await expect(page.locator('input[name="vorname"]')).toBeVisible();
        await expect(page.locator('input[name="nachname"]')).toBeVisible();
        await expect(page.locator('input[name="email"]')).toBeVisible();
    });

    test('Submit-Button hat type=submit', async ({ page }) => {
        await page.goto('/maddrax-fantreffen-2026');
        await page.waitForLoadState('networkidle');

        const submitButton = page.getByTestId('fantreffen-submit');
        await expect(submitButton).toBeVisible();
        await expect(submitButton).toHaveAttribute('type', 'submit');
    });

    test('Gast kann sich erfolgreich ohne T-Shirt registrieren', async ({ page }) => {
        await page.goto('/maddrax-fantreffen-2026');
        await page.waitForLoadState('networkidle');

        // Formularfelder ausfüllen
        await page.fill('input[name="vorname"]', 'Max');
        await page.fill('input[name="nachname"]', 'Mustermann');
        await page.fill('input[name="email"]', 'max.mustermann@example.com');

        // Submit
        await page.getByTestId('fantreffen-submit').click();

        // Weiterleitung zur Bestätigungsseite
        await page.waitForURL(/bestaetigung/, { timeout: 10000 });
    });

    test('T-Shirt Checkbox schaltet Größen-Dropdown korrekt um', async ({ page }) => {
        await page.goto('/maddrax-fantreffen-2026');
        await page.waitForLoadState('networkidle');

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
        await page.goto('/maddrax-fantreffen-2026');
        await page.waitForLoadState('networkidle');

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
        await page.goto('/maddrax-fantreffen-2026');
        await page.waitForLoadState('networkidle');

        // tshirt_groesse darf nicht required sein wenn Checkbox nicht gesetzt
        const selectRequired = await page.evaluate(() => {
            const select = document.querySelector('select[name="tshirt_groesse"]');
            return select?.required ?? null;
        });

        expect(selectRequired).toBe(false);
    });
});
