import { test, expect } from './test-support.js';
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
    await page.goto('/login', { waitUntil: 'domcontentloaded' });

    const emailInput = page.locator('input[name="email"]');
    const passwordInput = page.locator('input[name="password"]');

    await expect(emailInput).toBeVisible();
    await expect(passwordInput).toBeVisible();
    await emailInput.fill(email);
    await passwordInput.fill(password);

    await Promise.all([
        page.waitForURL(/\/dashboard$/, { waitUntil: 'domcontentloaded', timeout: 30000 }),
        passwordInput.press('Enter'),
    ]);
};

const gotoBundleCreateForm = async (page) => {
    await page.goto('/romantauschboerse/stapel-angebot-erstellen', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/stapel-angebot-erstellen$/, { timeout: 15000 });
    await expect(page.locator('#bundle-offer-form')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('select[name="series"]')).toBeVisible({ timeout: 15000 });
};

const submitBundleForm = async (page, waitForUrl = null) => {
    const submitButton = page.locator('#bundle-offer-form button[type="submit"]');

    if (waitForUrl) {
        await Promise.all([
            page.waitForURL(waitForUrl, { waitUntil: 'domcontentloaded', timeout: 30000 }),
            submitButton.click(),
        ]);

        return;
    }

    await submitButton.click();
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
            await gotoBundleCreateForm(page);

            await expect(page).toHaveURL(/stapel-angebot-erstellen$/);
            await expect(page.locator('[data-testid="page-title"]')).toContainText(/Stapel-Angebot erstellen/i);
        });

        test('Formular zeigt Serien-Dropdown und Nummern-Eingabe', async ({ page }) => {
            await loginAsMember(page);
            await gotoBundleCreateForm(page);

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
            await gotoBundleCreateForm(page);

            // Formular ausfüllen mit nur einem Buch
            await page.selectOption('select[name="series"]', SERIES_MADDRAX);
            await page.fill('input[name="book_numbers"]', '1');
            await page.selectOption('select[name="condition"]', CONDITION_Z2);

            // Absenden
            await submitBundleForm(page);

            const form = page.locator('#bundle-offer-form');
            await expect(form.getByText('Ein Stapel-Angebot muss mindestens 2 Romane enthalten.')).toBeVisible();
        });

        test('Erfolgreiches Erstellen eines Stapel-Angebots', async ({ page }) => {
            await loginAsMember(page);
            await gotoBundleCreateForm(page);

            // Formular ausfüllen
            await page.selectOption('select[name="series"]', SERIES_MADDRAX);
            await page.fill('input[name="book_numbers"]', '1-5');
            await page.selectOption('select[name="condition"]', CONDITION_Z2);

            // Absenden
            await submitBundleForm(page, /romantauschboerse$/);

            await expect(page.locator('[data-testid="page-title"]')).toContainText(/Romantauschbörse/i, { timeout: 15000 });

            // Dauerhafter Erfolgspfad statt flüchtigem Flash: Das neue Bundle muss
            // auf der Übersicht sichtbar sein. Damit fallen DB-Fehler weiterhin auf.
            await expect(page.getByText('Nummern: 1-5').first()).toBeVisible({ timeout: 15000 });
        });
    });

    test.describe('Stapel in der Übersicht', () => {
        test('Stapel werden gruppiert angezeigt', async ({ page }) => {
            await loginAsMember(page);
            
            // Erstelle zuerst ein Stapel-Angebot
            await gotoBundleCreateForm(page);
            await page.selectOption('select[name="series"]', SERIES_MADDRAX);
            await page.fill('input[name="book_numbers"]', '10-15');
            await page.selectOption('select[name="condition"]', CONDITION_Z1);
            await submitBundleForm(page, /romantauschboerse$/);

            // Stapel-Bereich sollte sichtbar sein
            const bundleSection = page.locator('[data-bundle-id]').first();
            await expect(bundleSection).toBeVisible();
        });

        test('Stapel zeigt Bearbeiten-Link für Eigentümer', async ({ page }) => {
            await loginAsMember(page);
            
            // Erstelle ein Stapel-Angebot
            await gotoBundleCreateForm(page);
            await page.selectOption('select[name="series"]', SERIES_MADDRAX);
            await page.fill('input[name="book_numbers"]', '20-25');
            await page.selectOption('select[name="condition"]', CONDITION_Z2);
            await submitBundleForm(page, /romantauschboerse$/);

            // Bearbeiten-Link sollte für eigene Stapel sichtbar sein
            const editLink = page.locator('a[href*="/stapel/"][href*="/bearbeiten"]').first();
            await expect(editLink).toBeVisible();
        });
    });

    test.describe('Stapel bearbeiten', () => {
        test('Bearbeiten-Seite lädt für eigene Stapel', async ({ page }) => {
            await loginAsMember(page);
            
            // Erstelle ein Stapel-Angebot
            await gotoBundleCreateForm(page);
            await page.selectOption('select[name="series"]', SERIES_MADDRAX);
            await page.fill('input[name="book_numbers"]', '30-35');
            await page.selectOption('select[name="condition"]', CONDITION_Z1);
            await submitBundleForm(page, /romantauschboerse$/);

            // Klicke auf Bearbeiten
            const editLink = page.locator('a[href*="/stapel/"][href*="/bearbeiten"]').first();
            await editLink.click();

            // Bearbeiten-Seite sollte laden
            await expect(page).toHaveURL(/stapel\/.*\/bearbeiten$/);
            await expect(page.locator('[data-testid="page-title"]')).toContainText(/Stapel-Angebot bearbeiten/i);
        });

        test('Bearbeiten-Formular zeigt aktuelle Werte', async ({ page }) => {
            await loginAsMember(page);
            
            // Erstelle ein Stapel-Angebot mit Nummern im Bereich 90-92.
            // Diese Nummern existieren im BookPlaywrightSeeder (1-100).
            // HINWEIS zur Test-Isolation: Playwright-Tests laufen sequentiell in einem
            // Browser-Kontext mit frischer DB pro Workflow-Run. Bei paralleler Ausführung
            // oder persistenten Testdaten könnten Kollisionen auftreten. In diesem Fall
            // sollten eindeutige Nummern pro Test verwendet werden (z.B. 90-92, 93-95, 96-98).
            await gotoBundleCreateForm(page);
            await page.selectOption('select[name="series"]', SERIES_MADDRAX);
            await page.fill('input[name="book_numbers"]', '90-92');
            await page.selectOption('select[name="condition"]', CONDITION_Z2);
            await submitBundleForm(page, /romantauschboerse$/);

            // Finde das Bundle über data-book-numbers-display mit "90-92" Substring.
            // .first() wählt das erste Match falls mehrere existieren (Test-Isolation).
            const bundleWithNumbers = page.locator('[data-bundle-id][data-book-numbers-display*="90"]').first();
            await expect(bundleWithNumbers).toBeVisible({ timeout: 5000 });
            const editLink = bundleWithNumbers.locator('a[href*="/stapel/"][href*="/bearbeiten"]');
            await editLink.click();

            // Warte auf die Seite und Alpine.js Initialisierung
            await page.waitForURL(/stapel\/.*\/bearbeiten$/, { waitUntil: 'domcontentloaded' });
            
            // Aktuelle Roman-Nummern sollten im Eingabefeld stehen
            // Warte bis Alpine.js das Input-Feld mit dem initialen Wert befüllt hat
            const bookNumbersInput = page.locator('input[name="book_numbers"]');
            await expect(bookNumbersInput).toHaveValue(/90/, { timeout: 10000 });
        });

        test('Stapel kann gelöscht werden', async ({ page }) => {
            test.setTimeout(60000);

            await loginAsMember(page);
            
            // Erstelle ein Stapel-Angebot
            await gotoBundleCreateForm(page);
            await page.selectOption('select[name="series"]', SERIES_MADDRAX);
            await page.fill('input[name="book_numbers"]', '50-52');
            await page.selectOption('select[name="condition"]', CONDITION_Z3);
            await submitBundleForm(page, /romantauschboerse$/);

            // Gehe zur Bearbeiten-Seite des gerade erstellten Stapels.
            const bundleWithNumbers = page.locator('[data-bundle-id][data-book-numbers-display*="50"]').first();
            await expect(bundleWithNumbers).toBeVisible({ timeout: 15000 });
            const editLink = bundleWithNumbers.locator('a[href*="/stapel/"][href*="/bearbeiten"]');
            await editLink.click();

            // Lösch-Button sollte vorhanden sein
            const deleteButton = page.getByRole('button', { name: 'Stapel löschen' });
            await expect(deleteButton).toBeVisible();

            // wire:confirm erzeugt einen Browser-Dialog
            page.on('dialog', dialog => dialog.accept());

            // Löschen
            await deleteButton.click();

            // Sollte zur Übersicht weiterleiten (Firefox braucht mehr Zeit für Form-Submit nach Dialog)
            await expect(page).toHaveURL(/romantauschboerse$/, { timeout: 15000 });
            await expect(page.locator('[data-bundle-id][data-book-numbers-display*="50"]')).toHaveCount(0);
        });
    });

    test.describe('Accessibility', () => {
        test('Stapel-Angebot Formular erfüllt WCAG AA Richtlinien', async ({ page }) => {
            await loginAsMember(page);
            await gotoBundleCreateForm(page);

            const accessibilityScanResults = await new AxeBuilder({ page })
                .withTags(['wcag2a', 'wcag2aa'])
                .exclude('.leaflet-container')
                // maryUI ThemeToggle erzeugt ein verstecktes Checkbox-Element ohne zugängliches Label
                .exclude('input.theme-controller')
                // Livewire wire:navigate Progress-Bar (NProgress) nutzt ungültiges role="bar"
                .exclude('#nprogress [role="bar"]')
                // Deaktiviere nested-interactive - bekanntes maryUI Dropdown Problem
                .disableRules(['nested-interactive'])
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
            await gotoBundleCreateForm(page);
            await page.selectOption('select[name="series"]', SERIES_MADDRAX);
            await page.fill('input[name="book_numbers"]', '60-65');
            await page.selectOption('select[name="condition"]', CONDITION_Z1);
            await submitBundleForm(page, /romantauschboerse$/);

            // Gehe zur Bearbeiten-Seite
            const editLink = page.locator('a[href*="/stapel/"][href*="/bearbeiten"]').first();
            await editLink.click();

            // wire:navigate navigiert per SPA – auf die Bearbeiten-Seite warten
            await page.waitForURL(/\/bearbeiten/, { waitUntil: 'domcontentloaded' });

            const accessibilityScanResults = await new AxeBuilder({ page })
                .withTags(['wcag2a', 'wcag2aa'])
                .exclude('.leaflet-container')
                // maryUI ThemeToggle erzeugt ein verstecktes Checkbox-Element ohne zugängliches Label
                .exclude('input.theme-controller')
                // Livewire wire:navigate Progress-Bar (NProgress) nutzt ungültiges role="bar"
                .exclude('#nprogress [role="bar"]')
                .disableRules(['nested-interactive'])
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
            test.setTimeout(60_000);

            
            // Erstelle ein Stapel-Angebot damit die Übersicht Stapel enthält
            await gotoBundleCreateForm(page);
            await page.selectOption('select[name="series"]', SERIES_MADDRAX);
            await page.fill('input[name="book_numbers"]', '70-75');
            await page.selectOption('select[name="condition"]', CONDITION_Z2);
            await submitBundleForm(page, /romantauschboerse$/);

            const accessibilityScanResults = await new AxeBuilder({ page })
                .withTags(['wcag2a', 'wcag2aa'])
                .exclude('.leaflet-container')
                // maryUI ThemeToggle erzeugt ein verstecktes Checkbox-Element ohne zugängliches Label
                .exclude('input.theme-controller')
                // Livewire wire:navigate Progress-Bar (NProgress) nutzt ungültiges role="bar"
                .exclude('#nprogress [role="bar"]')
                // Deaktiviere nested-interactive - bekanntes maryUI Dropdown Problem
                .disableRules(['nested-interactive'])
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
            await gotoBundleCreateForm(page);

            // maryUI rendert Labels als <legend> innerhalb von <fieldset>,
            // daher prüfen wir den Labeltext über die fieldset-legend Klasse
            await expect(page.locator('select[name="condition"]')).toBeVisible();
            await expect(page.locator('select[name="condition_max"]')).toBeVisible();
            await expect(page.locator('.fieldset-legend:has-text("Von")')).toBeVisible();
            await expect(page.locator('.fieldset-legend:has-text("Bis")')).toBeVisible();
        });

        test('Abbrechen-Button führt zurück zur Übersicht', async ({ page }) => {
            await loginAsMember(page);
            await gotoBundleCreateForm(page);

            const cancelLink = page.locator('a:has-text("Abbrechen")');
            await expect(cancelLink).toBeVisible();
            await Promise.all([
                page.waitForURL(/romantauschboerse$/, { waitUntil: 'domcontentloaded' }),
                cancelLink.click({ noWaitAfter: true }),
            ]);
        });

        test('Foto-Upload-Feld ist vorhanden', async ({ page }) => {
            await loginAsMember(page);
            await gotoBundleCreateForm(page);

            const photoInput = page.locator('input[type="file"]#photos');
            await expect(photoInput).toBeVisible();
            await expect(photoInput).toHaveAttribute('multiple', '');
            await expect(photoInput).toHaveAttribute('accept', 'image/*');
        });
    });
});
