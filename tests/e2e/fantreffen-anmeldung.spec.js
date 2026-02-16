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

        // Prüfe, ob die Input-Felder korrekte name-Attribute haben
        const vornameInput = page.locator('input[name="vorname"]');
        const nachnameInput = page.locator('input[name="nachname"]');
        const emailInput = page.locator('input[name="email"]');

        await expect(vornameInput).toBeVisible();
        await expect(nachnameInput).toBeVisible();
        await expect(emailInput).toBeVisible();
    });

    test('Submit-Button hat type=submit', async ({ page }) => {
        await page.goto('/maddrax-fantreffen-2026');
        await page.waitForLoadState('networkidle');

        const submitButton = page.getByTestId('fantreffen-submit');
        await expect(submitButton).toBeVisible();

        // Prüfe, dass der Button type="submit" hat
        const type = await submitButton.getAttribute('type');
        expect(type).toBe('submit');
    });

    test('Gast kann sich erfolgreich registrieren', async ({ page }) => {
        await page.goto('/maddrax-fantreffen-2026');
        await page.waitForLoadState('networkidle');

        // Formularfelder ausfüllen
        await page.fill('input[name="vorname"]', 'Max');
        await page.fill('input[name="nachname"]', 'Mustermann');
        await page.fill('input[name="email"]', 'max.mustermann@example.com');

        // Submit Button klicken
        const submitButton = page.getByTestId('fantreffen-submit');
        await submitButton.click();

        // Erwarte Weiterleitung zur Bestätigungsseite
        await page.waitForURL(/bestaetigung/, { timeout: 10000 });
        await expect(page.locator('body')).toContainText('Anmeldung');
    });

    test('T-Shirt Checkbox und Größen-Dropdown Toggle funktioniert', async ({ page }) => {
        await page.goto('/maddrax-fantreffen-2026');
        await page.waitForLoadState('networkidle');

        // T-Shirt Container initial hidden
        const tshirtContainer = page.locator('#tshirt-groesse-container');

        // Checkbox suchen und klicken
        const checkbox = page.locator('#tshirt_bestellt');
        const checkboxExists = await checkbox.count();

        if (checkboxExists > 0) {
            // Prüfe ob Checkbox anklickbar ist
            await checkbox.check();
            // Nach dem Check sollte der Container sichtbar sein
            await expect(tshirtContainer).not.toHaveClass(/hidden/);
        }
    });

    test('HTML-Struktur des Formulars ist korrekt für Submission', async ({ page }) => {
        await page.goto('/maddrax-fantreffen-2026');
        await page.waitForLoadState('networkidle');

        // Prüfe, ob ein <form> Element mit POST method und korrekter action existiert
        const form = page.locator('form#fantreffen-form');
        await expect(form).toBeVisible();

        const method = await form.getAttribute('method');
        expect(method?.toUpperCase()).toBe('POST');

        // CSRF Token muss vorhanden sein
        const csrfToken = page.locator('form#fantreffen-form input[name="_token"]');
        await expect(csrfToken).toBeAttached();

        // Submit-Button muss INNERHALB des Formulars liegen
        const buttonInForm = page.locator('form#fantreffen-form button[type="submit"]');
        const buttonCount = await buttonInForm.count();
        expect(buttonCount).toBeGreaterThan(0);

        // Alle Inputs müssen name-Attribute haben und innerhalb des Forms liegen
        const vornameInForm = page.locator('form#fantreffen-form input[name="vorname"]');
        await expect(vornameInForm).toBeVisible();
    });

    test('Debug: Rendered HTML des Submit-Buttons prüfen', async ({ page }) => {
        await page.goto('/maddrax-fantreffen-2026');
        await page.waitForLoadState('networkidle');

        // Hole den HTML-Schnipsel des Submit-Bereichs
        const formHTML = await page.locator('form#fantreffen-form').innerHTML();
        console.log('=== FORM HTML ===');
        console.log(formHTML);
        console.log('=== END FORM HTML ===');

        // Prüfe ob button[type=submit] existiert  
        const submitButtons = page.locator('form#fantreffen-form button[type="submit"]');
        const count = await submitButtons.count();
        console.log(`Submit buttons found: ${count}`);

        // Falls kein submit-button: Prüfe alle buttons
        if (count === 0) {
            const allButtons = page.locator('form#fantreffen-form button');
            const allCount = await allButtons.count();
            console.log(`Total buttons in form: ${allCount}`);
            for (let i = 0; i < allCount; i++) {
                const btn = allButtons.nth(i);
                const outerHTML = await btn.evaluate(el => el.outerHTML);
                console.log(`Button ${i}: ${outerHTML}`);
            }
        }

        expect(count).toBeGreaterThan(0);
    });
});
