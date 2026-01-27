import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * Romantauschbörse Stapel-Angebote E2E Tests
 *
 * TEST-ISOLATION HINWEISE:
 * 
 * 1. Sequentielle Ausführung:
 *    Diese Tests verwenden test.describe.serial() nicht, laufen aber in Playwright
 *    standardmäßig sequentiell im selben Worker. Bei paralleler Worker-Konfiguration
 *    könnten Kollisionen auftreten.
 *
 * 2. Buchnummern-Strategie:
 *    Jeder Test verwendet eindeutige Buchnummern-Bereiche um Kollisionen zu vermeiden:
 *    - Stapel erstellen: 10-15
 *    - Bearbeiten-Test: 90-92
 *    - Löschen-Test: 50-52
 *    - etc.
 *    Falls neue Tests hinzugefügt werden, sollten nicht verwendete Bereiche gewählt werden.
 *
 * 3. Parallele Ausführung:
 *    Falls in Zukunft parallele Ausführung aktiviert wird, sollten entweder:
 *    - test.describe.serial() verwendet werden für Tests die dieselben Daten modifizieren
 *    - Oder eindeutige Datensätze pro Test erstellt werden (z.B. mit Zeitstempel-Suffix)
 *
 * 4. DB-Reset:
 *    CI führt vor jedem Playwright-Run frische Migrations mit Seeding aus.
 *    Lokal sollte bei Testfehlern die DB neu geseedet werden.
 */

/**
 * Helper: Login as member
 *
 * WICHTIG: Der Test-User 'playwright-member@example.com' wird vom
 * BookPlaywrightSeeder erstellt (database/seeders/BookPlaywrightSeeder.php).
 * Dieser Seeder muss in der Test-Datenbank ausgeführt werden, bevor die
 * E2E-Tests laufen. In CI wird dies automatisch durch die Migrations/Seeding
 * in .github/workflows/playwright.yml erledigt.
 *
 * SICHERHEITSWARNUNG: Diese Credentials (playwright-member@example.com / password)
 * sind AUSSCHLIESSLICH für CI- und lokale Testumgebungen bestimmt!
 * - NIEMALS in Produktion verwenden
 * - NIEMALS dieses Pattern für echte Authentifizierung kopieren
 * - Der BookPlaywrightSeeder sollte NUR in Test-Datenbanken ausgeführt werden
 */
const loginAsMember = async (page, email = 'playwright-member@example.com', password = 'password') => {
    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
};

// Die Enum-Werte aus BookType.php
const SERIES_MADDRAX = 'Maddrax - Die dunkle Zukunft der Erde';
const CONDITION_Z1 = 'Z1';
const CONDITION_Z2 = 'Z2';
const CONDITION_Z3 = 'Z3';

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

        test('Formular validiert Mindestanzahl von 2 Büchern', async ({ page }) => {
            await loginAsMember(page);
            await page.goto('/romantauschboerse/stapel-angebot-erstellen');

            // Formular ausfüllen mit nur einem Buch
            await page.selectOption('select[name="series"]', SERIES_MADDRAX);
            await page.fill('input[name="book_numbers"]', '1');
            await page.selectOption('select[name="condition"]', CONDITION_Z2);

            // Absenden
            await page.click('button[type="submit"]');

            // Fehler sollte im Formular-Kontext angezeigt werden
            // Suche nach Validierungsfehler unter dem book_numbers-Feld oder Session-Error im Formular
            //
            // HINWEIS zu Selektoren:
            // Der Selektor kombiniert ARIA-Rolle und CSS-Klasse für Robustheit:
            // - [role="alert"]: Semantisch korrekt, Framework-unabhängig
            // - .bg-red-100: Tailwind-spezifisch, als Fallback
            // Für bessere Wartbarkeit könnte ein data-testid="validation-error" Attribut
            // in die Blade-Views eingefügt werden. Aktuell ist die Mischung akzeptabel
            // da beide Selektoren funktional äquivalent sind.
            const form = page.locator('#bundle-offer-form');
            await expect(
                form.locator('[role="alert"], .bg-red-100, [data-testid="validation-error"]')
            ).toBeVisible();
        });

        test('Erfolgreiches Erstellen eines Stapel-Angebots', async ({ page }) => {
            await loginAsMember(page);
            await page.goto('/romantauschboerse/stapel-angebot-erstellen');

            // Formular ausfüllen
            await page.selectOption('select[name="series"]', SERIES_MADDRAX);
            await page.fill('input[name="book_numbers"]', '1-5');
            await page.selectOption('select[name="condition"]', CONDITION_Z2);

            // Absenden
            await page.click('button[type="submit"]');

            // Sollte zur Übersicht weiterleiten
            await expect(page).toHaveURL(/romantauschboerse$/);

            // WICHTIG: Erfolgsmeldung prüfen - dieser Check hätte den Bug mit dem
            // fehlenden 'properties'-Feld in Activity::create() gefangen, da bei
            // einem DB-Fehler keine Erfolgsmeldung angezeigt wird.
            const successMessage = page.locator('[data-testid="flash-success"], .bg-green-100, [role="alert"]').filter({ hasText: /Stapel-Angebot.*erstellt/i });
            await expect(successMessage).toBeVisible();
        });
    });

    test.describe('Stapel in der Übersicht', () => {
        test('Stapel werden gruppiert angezeigt', async ({ page }) => {
            await loginAsMember(page);
            
            // Erstelle zuerst ein Stapel-Angebot
            await page.goto('/romantauschboerse/stapel-angebot-erstellen');
            await page.selectOption('select[name="series"]', SERIES_MADDRAX);
            await page.fill('input[name="book_numbers"]', '10-15');
            await page.selectOption('select[name="condition"]', CONDITION_Z1);
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
            await page.selectOption('select[name="series"]', SERIES_MADDRAX);
            await page.fill('input[name="book_numbers"]', '20-25');
            await page.selectOption('select[name="condition"]', CONDITION_Z2);
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
            await page.selectOption('select[name="series"]', SERIES_MADDRAX);
            await page.fill('input[name="book_numbers"]', '30-35');
            await page.selectOption('select[name="condition"]', CONDITION_Z1);
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
            
            // Erstelle ein Stapel-Angebot mit Nummern im Bereich 90-92.
            // Diese Nummern existieren im BookPlaywrightSeeder (1-100).
            // HINWEIS zur Test-Isolation: Playwright-Tests laufen sequentiell in einem
            // Browser-Kontext mit frischer DB pro Workflow-Run. Bei paralleler Ausführung
            // oder persistenten Testdaten könnten Kollisionen auftreten. In diesem Fall
            // sollten eindeutige Nummern pro Test verwendet werden (z.B. 90-92, 93-95, 96-98).
            await page.goto('/romantauschboerse/stapel-angebot-erstellen');
            await page.selectOption('select[name="series"]', SERIES_MADDRAX);
            await page.fill('input[name="book_numbers"]', '90-92');
            await page.selectOption('select[name="condition"]', CONDITION_Z2);
            await page.click('button[type="submit"]');

            await expect(page).toHaveURL(/romantauschboerse$/);

            // Finde das Bundle über data-book-numbers-display mit "90-92" Substring.
            // .first() wählt das erste Match falls mehrere existieren (Test-Isolation).
            const bundleWithNumbers = page.locator('[data-bundle-id][data-book-numbers-display*="90"]').first();
            await expect(bundleWithNumbers).toBeVisible({ timeout: 5000 });
            const editLink = bundleWithNumbers.locator('a[href*="/stapel/"][href*="/bearbeiten"]');
            await editLink.click();

            // Warte auf die Seite und Alpine.js Initialisierung
            await page.waitForURL(/stapel\/.*\/bearbeiten$/);
            
            // Aktuelle Roman-Nummern sollten im Eingabefeld stehen
            // Warte bis Alpine.js das Input-Feld mit dem initialen Wert befüllt hat
            const bookNumbersInput = page.locator('input[name="book_numbers"]');
            await expect(bookNumbersInput).toHaveValue(/90/, { timeout: 10000 });
        });

        test('Stapel kann gelöscht werden', async ({ page }) => {
            await loginAsMember(page);
            
            // Erstelle ein Stapel-Angebot
            await page.goto('/romantauschboerse/stapel-angebot-erstellen');
            await page.selectOption('select[name="series"]', SERIES_MADDRAX);
            await page.fill('input[name="book_numbers"]', '50-52');
            await page.selectOption('select[name="condition"]', CONDITION_Z3);
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
            await page.selectOption('select[name="series"]', SERIES_MADDRAX);
            await page.fill('input[name="book_numbers"]', '60-65');
            await page.selectOption('select[name="condition"]', CONDITION_Z1);
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
            await page.selectOption('select[name="series"]', SERIES_MADDRAX);
            await page.fill('input[name="book_numbers"]', '70-75');
            await page.selectOption('select[name="condition"]', CONDITION_Z2);
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
