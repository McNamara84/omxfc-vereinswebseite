import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * Helper: Login as member
 */
const loginAsMember = async (page, email = 'playwright-member@example.com', password = 'password') => {
    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
};

test.describe('Romantauschbörse - Stapel-Angebote', () => {
    test.describe('Formular zum Stapel-Angebot erstellen', () => {
        test('Stapel-Angebot Formular ist erreichbar', async ({ page }) => {
            await loginAsMember(page);
            await page.goto('/romantauschboerse/stapel-angebot-erstellen');

            await expect(page).toHaveURL(/stapel-angebot-erstellen$/);
            await expect(page.getByRole('heading', { name: /Stapel-Angebot erstellen/i })).toBeVisible();
        });

        test('Formular zeigt Serien-Dropdown und Nummern-Eingabe', async ({ page }) => {
            await loginAsMember(page);
            await page.goto('/romantauschboerse/stapel-angebot-erstellen');

            // Serien-Dropdown vorhanden
            const seriesSelect = page.locator('select[name="series"]');
            await expect(seriesSelect).toBeVisible();

            // Nummern-Eingabefeld vorhanden
            const bookNumbersInput = page.locator('input[name="book_numbers"]');
            await expect(bookNumbersInput).toBeVisible();
            await expect(bookNumbersInput).toHaveAttribute('placeholder', /1-50, 52, 55-100/);

            // Zustand-Dropdowns vorhanden
            const conditionMin = page.locator('select[name="condition"]');
            const conditionMax = page.locator('select[name="condition_max"]');
            await expect(conditionMin).toBeVisible();
            await expect(conditionMax).toBeVisible();
        });

        test('Live-Vorschau zeigt erkannte Roman-Nummern', async ({ page }) => {
            await loginAsMember(page);
            await page.goto('/romantauschboerse/stapel-angebot-erstellen');

            const bookNumbersInput = page.locator('input[name="book_numbers"]');
            await bookNumbersInput.fill('1-5, 10');

            // Warte auf Alpine.js Debounce (300ms)
            await page.waitForTimeout(400);

            // Die Vorschau sollte "6 Romane erkannt" anzeigen
            const preview = page.locator('[x-show="numbers.length > 0"]');
            await expect(preview).toContainText('6');
            await expect(preview).toContainText('Romane erkannt');
        });

        test('Formular validiert Mindestanzahl von 2 Büchern', async ({ page }) => {
            await loginAsMember(page);
            await page.goto('/romantauschboerse/stapel-angebot-erstellen');

            // Formular ausfüllen mit nur einem Buch
            await page.selectOption('select[name="series"]', 'MaddraxDieDunkleZukunftDerErde');
            await page.fill('input[name="book_numbers"]', '1');
            await page.selectOption('select[name="condition"]', 'Z2');

            // Absenden
            await page.click('button[type="submit"]');

            // Fehler sollte angezeigt werden
            await expect(page.locator('.text-red-600, .text-red-500')).toBeVisible();
        });

        test('Erfolgreiches Erstellen eines Stapel-Angebots', async ({ page }) => {
            await loginAsMember(page);
            await page.goto('/romantauschboerse/stapel-angebot-erstellen');

            // Formular ausfüllen
            await page.selectOption('select[name="series"]', 'MaddraxDieDunkleZukunftDerErde');
            await page.fill('input[name="book_numbers"]', '1-5');
            await page.selectOption('select[name="condition"]', 'Z2');

            // Absenden
            await page.click('button[type="submit"]');

            // Sollte zur Übersicht weiterleiten mit Erfolgsmeldung
            await expect(page).toHaveURL(/romantauschboerse$/);
        });
    });

    test.describe('Stapel in der Übersicht', () => {
        test('Stapel werden gruppiert angezeigt', async ({ page }) => {
            await loginAsMember(page);
            
            // Erstelle zuerst ein Stapel-Angebot
            await page.goto('/romantauschboerse/stapel-angebot-erstellen');
            await page.selectOption('select[name="series"]', 'MaddraxDieDunkleZukunftDerErde');
            await page.fill('input[name="book_numbers"]', '10-15');
            await page.selectOption('select[name="condition"]', 'Z1');
            await page.click('button[type="submit"]');

            await expect(page).toHaveURL(/romantauschboerse$/);

            // Stapel-Bereich sollte sichtbar sein
            const bundleSection = page.locator('[data-bundle-id]').first();
            await expect(bundleSection).toBeVisible();
        });

        test('Stapel zeigt Bearbeiten-Link für Eigentümer', async ({ page }) => {
            await loginAsMember(page);
            
            // Erstelle ein Stapel-Angebot
            await page.goto('/romantauschboerse/stapel-angebot-erstellen');
            await page.selectOption('select[name="series"]', 'MaddraxDieDunkleZukunftDerErde');
            await page.fill('input[name="book_numbers"]', '20-25');
            await page.selectOption('select[name="condition"]', 'Z2');
            await page.click('button[type="submit"]');

            await expect(page).toHaveURL(/romantauschboerse$/);

            // Bearbeiten-Link sollte für eigene Stapel sichtbar sein
            const editLink = page.locator('a[href*="/stapel/"][href*="/bearbeiten"]').first();
            await expect(editLink).toBeVisible();
        });
    });

    test.describe('Stapel bearbeiten', () => {
        test('Bearbeiten-Seite lädt für eigene Stapel', async ({ page }) => {
            await loginAsMember(page);
            
            // Erstelle ein Stapel-Angebot
            await page.goto('/romantauschboerse/stapel-angebot-erstellen');
            await page.selectOption('select[name="series"]', 'MaddraxDieDunkleZukunftDerErde');
            await page.fill('input[name="book_numbers"]', '30-35');
            await page.selectOption('select[name="condition"]', 'Z1');
            await page.click('button[type="submit"]');

            await expect(page).toHaveURL(/romantauschboerse$/);

            // Klicke auf Bearbeiten
            const editLink = page.locator('a[href*="/stapel/"][href*="/bearbeiten"]').first();
            await editLink.click();

            // Bearbeiten-Seite sollte laden
            await expect(page).toHaveURL(/stapel\/.*\/bearbeiten$/);
            await expect(page.getByRole('heading', { name: /Stapel-Angebot bearbeiten/i })).toBeVisible();
        });

        test('Bearbeiten-Formular zeigt aktuelle Werte', async ({ page }) => {
            await loginAsMember(page);
            
            // Erstelle ein Stapel-Angebot
            await page.goto('/romantauschboerse/stapel-angebot-erstellen');
            await page.selectOption('select[name="series"]', 'MaddraxDieDunkleZukunftDerErde');
            await page.fill('input[name="book_numbers"]', '40-42');
            await page.selectOption('select[name="condition"]', 'Z2');
            await page.click('button[type="submit"]');

            await expect(page).toHaveURL(/romantauschboerse$/);

            // Gehe zur Bearbeiten-Seite
            const editLink = page.locator('a[href*="/stapel/"][href*="/bearbeiten"]').first();
            await editLink.click();

            // Aktuelle Roman-Nummern sollten im Eingabefeld stehen
            const bookNumbersInput = page.locator('input[name="book_numbers"]');
            await expect(bookNumbersInput).toHaveValue(/40/);
        });

        test('Stapel kann gelöscht werden', async ({ page }) => {
            await loginAsMember(page);
            
            // Erstelle ein Stapel-Angebot
            await page.goto('/romantauschboerse/stapel-angebot-erstellen');
            await page.selectOption('select[name="series"]', 'MaddraxDieDunkleZukunftDerErde');
            await page.fill('input[name="book_numbers"]', '50-52');
            await page.selectOption('select[name="condition"]', 'Z3');
            await page.click('button[type="submit"]');

            await expect(page).toHaveURL(/romantauschboerse$/);

            // Gehe zur Bearbeiten-Seite
            const editLink = page.locator('a[href*="/stapel/"][href*="/bearbeiten"]').first();
            await editLink.click();

            // Lösch-Button sollte vorhanden sein
            const deleteButton = page.locator('button:has-text("Stapel löschen")');
            await expect(deleteButton).toBeVisible();

            // Dialog akzeptieren
            page.on('dialog', dialog => dialog.accept());

            // Löschen
            await deleteButton.click();

            // Sollte zur Übersicht weiterleiten
            await expect(page).toHaveURL(/romantauschboerse$/);
        });
    });

    test.describe('Accessibility', () => {
        test('Stapel-Angebot Formular erfüllt WCAG AA Richtlinien', async ({ page }) => {
            await loginAsMember(page);
            await page.goto('/romantauschboerse/stapel-angebot-erstellen');

            const accessibilityScanResults = await new AxeBuilder({ page })
                .withTags(['wcag2a', 'wcag2aa'])
                .exclude('.leaflet-container') // Falls Karten vorhanden
                .analyze();

            const formattedViolations = accessibilityScanResults.violations
                .map((violation) => {
                    const targets = violation.nodes
                        .flatMap((node) => node.target)
                        .join(', ');
                    return `${violation.id}: ${violation.help} -> ${targets}`;
                })
                .join('\n');

            expect(accessibilityScanResults.violations, formattedViolations).toEqual([]);
        });

        test('Stapel-Bearbeiten Formular erfüllt WCAG AA Richtlinien', async ({ page }) => {
            await loginAsMember(page);
            
            // Erstelle erst ein Stapel-Angebot
            await page.goto('/romantauschboerse/stapel-angebot-erstellen');
            await page.selectOption('select[name="series"]', 'MaddraxDieDunkleZukunftDerErde');
            await page.fill('input[name="book_numbers"]', '60-65');
            await page.selectOption('select[name="condition"]', 'Z1');
            await page.click('button[type="submit"]');

            await expect(page).toHaveURL(/romantauschboerse$/);

            // Gehe zur Bearbeiten-Seite
            const editLink = page.locator('a[href*="/stapel/"][href*="/bearbeiten"]').first();
            await editLink.click();

            const accessibilityScanResults = await new AxeBuilder({ page })
                .withTags(['wcag2a', 'wcag2aa'])
                .exclude('.leaflet-container')
                .analyze();

            const formattedViolations = accessibilityScanResults.violations
                .map((violation) => {
                    const targets = violation.nodes
                        .flatMap((node) => node.target)
                        .join(', ');
                    return `${violation.id}: ${violation.help} -> ${targets}`;
                })
                .join('\n');

            expect(accessibilityScanResults.violations, formattedViolations).toEqual([]);
        });

        test('Romantauschbörse Übersicht mit Stapeln erfüllt WCAG AA Richtlinien', async ({ page }) => {
            await loginAsMember(page);
            
            // Erstelle ein Stapel-Angebot damit die Übersicht Stapel enthält
            await page.goto('/romantauschboerse/stapel-angebot-erstellen');
            await page.selectOption('select[name="series"]', 'MaddraxDieDunkleZukunftDerErde');
            await page.fill('input[name="book_numbers"]', '70-75');
            await page.selectOption('select[name="condition"]', 'Z2');
            await page.click('button[type="submit"]');

            await expect(page).toHaveURL(/romantauschboerse$/);

            const accessibilityScanResults = await new AxeBuilder({ page })
                .withTags(['wcag2a', 'wcag2aa'])
                .exclude('.leaflet-container')
                .analyze();

            const formattedViolations = accessibilityScanResults.violations
                .map((violation) => {
                    const targets = violation.nodes
                        .flatMap((node) => node.target)
                        .join(', ');
                    return `${violation.id}: ${violation.help} -> ${targets}`;
                })
                .join('\n');

            expect(accessibilityScanResults.violations, formattedViolations).toEqual([]);
        });
    });

    test.describe('Formular-Interaktionen', () => {
        test('Zustandsbereich zeigt beide Dropdowns', async ({ page }) => {
            await loginAsMember(page);
            await page.goto('/romantauschboerse/stapel-angebot-erstellen');

            // Labels für Zustandsbereich prüfen
            await expect(page.locator('label[for="condition-min"]')).toContainText(/Von/);
            await expect(page.locator('label[for="condition-max"]')).toContainText(/Bis/);
        });

        test('Abbrechen-Button führt zurück zur Übersicht', async ({ page }) => {
            await loginAsMember(page);
            await page.goto('/romantauschboerse/stapel-angebot-erstellen');

            const cancelLink = page.locator('a:has-text("Abbrechen")');
            await expect(cancelLink).toBeVisible();
            await cancelLink.click();

            await expect(page).toHaveURL(/romantauschboerse$/);
        });

        test('Foto-Upload-Feld ist vorhanden', async ({ page }) => {
            await loginAsMember(page);
            await page.goto('/romantauschboerse/stapel-angebot-erstellen');

            const photoInput = page.locator('input[type="file"][name="photos[]"]');
            await expect(photoInput).toBeVisible();
            await expect(photoInput).toHaveAttribute('multiple', '');
            await expect(photoInput).toHaveAttribute('accept', 'image/*');
        });
    });
});
