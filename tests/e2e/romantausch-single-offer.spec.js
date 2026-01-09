import { test, expect } from '@playwright/test';

/**
 * Romantauschbörse Einzelangebot E2E Tests
 *
 * Diese Tests decken den Workflow zur Erstellung von Einzelangeboten ab
 * und prüfen insbesondere, dass der gesamte Request erfolgreich durchläuft
 * (inklusive Activity-Logging), um Regressions wie den properties-Bug zu vermeiden.
 *
 * TEST-ISOLATION HINWEISE:
 * - Jeder Test verwendet eindeutige Buchnummern um Kollisionen zu vermeiden
 * - Die Test-Datenbank wird vor jedem Playwright-Run frisch geseedet
 * - Der BookPlaywrightSeeder stellt Testdaten (Bücher 1-100) bereit
 */

/**
 * Helper: Login as member
 *
 * SICHERHEITSWARNUNG: Diese Credentials sind AUSSCHLIESSLICH für CI- und
 * lokale Testumgebungen bestimmt! NIEMALS in Produktion verwenden.
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

test.describe('Romantauschbörse - Einzelangebote', () => {
    test.describe('Angebot erstellen', () => {
        test('Angebot-Formular ist erreichbar', async ({ page }) => {
            await loginAsMember(page);
            await page.goto('/romantauschboerse/angebot-erstellen');

            await expect(page).toHaveURL(/angebot-erstellen$/);
            await expect(page.getByRole('heading', { name: /Neues Angebot erstellen/i })).toBeVisible();
        });

        test('Formular zeigt alle erforderlichen Felder', async ({ page }) => {
            await loginAsMember(page);
            await page.goto('/romantauschboerse/angebot-erstellen');

            // Serien-Dropdown vorhanden
            const seriesSelect = page.locator('select[name="series"]');
            await expect(seriesSelect).toBeVisible();

            // Buchnummer-Dropdown vorhanden
            const bookNumberSelect = page.locator('select[name="book_number"]');
            await expect(bookNumberSelect).toBeVisible();

            // Zustand-Dropdown vorhanden
            const conditionSelect = page.locator('select[name="condition"]');
            await expect(conditionSelect).toBeVisible();
        });

        // HINWEIS: Kein Pflichtfeld-Validierungstest, da die Dropdowns bereits
        // Standardwerte haben (erste Option vorausgewählt). Das Formular kann
        // daher immer erfolgreich abgeschickt werden.

        test('Erfolgreiches Erstellen eines Einzelangebots', async ({ page }) => {
            await loginAsMember(page);
            await page.goto('/romantauschboerse/angebot-erstellen');

            // Formular ausfüllen
            await page.selectOption('select[name="series"]', SERIES_MADDRAX);
            
            // Warte bis die filterBooks()-Funktion das Dropdown aktualisiert hat
            // Die Funktion setzt option.hidden = true für nicht passende Serien,
            // daher prüfen wir mit waitForFunction ob die Option nicht hidden ist
            await page.waitForFunction(
                (value) => {
                    const opt = document.querySelector(`select[name="book_number"] option[value="${value}"]`);
                    return opt && !opt.hidden && !opt.disabled;
                },
                '42'
            );
            
            // Wähle eine Buchnummer (z.B. 42 - sollte im Seeder existieren)
            await page.selectOption('select[name="book_number"]', '42');
            await page.selectOption('select[name="condition"]', CONDITION_Z1);

            // Absenden
            await page.click('button[type="submit"]');

            // Sollte zur Übersicht weiterleiten
            await expect(page).toHaveURL(/romantauschboerse$/);

            // WICHTIG: Erfolgsmeldung prüfen - dieser Check fängt DB-Fehler wie den
            // Activity::create() Bug, bei dem ein nicht existierendes 'properties'-Feld
            // verwendet wurde. Ohne diesen Check würde ein 500er-Fehler unbemerkt bleiben.
            const successMessage = page.locator('[data-testid="flash-success"], .bg-green-100, [role="alert"]').filter({ hasText: /Angebot erstellt/i });
            await expect(successMessage).toBeVisible();
        });

        test('Erstelltes Angebot erscheint in der Übersicht', async ({ page }) => {
            await loginAsMember(page);
            await page.goto('/romantauschboerse/angebot-erstellen');

            // Formular ausfüllen mit eindeutiger Buchnummer
            await page.selectOption('select[name="series"]', SERIES_MADDRAX);
            await page.waitForFunction(
                (value) => {
                    const opt = document.querySelector(`select[name="book_number"] option[value="${value}"]`);
                    return opt && !opt.hidden && !opt.disabled;
                },
                '77'
            );
            await page.selectOption('select[name="book_number"]', '77');
            await page.selectOption('select[name="condition"]', CONDITION_Z2);

            // Absenden
            await page.click('button[type="submit"]');

            // Warte auf Weiterleitung
            await expect(page).toHaveURL(/romantauschboerse$/);

            // Prüfe, dass das Angebot in der Liste erscheint
            // (Maddrax Band 77 sollte jetzt sichtbar sein)
            const offerInList = page.locator('text=77').first();
            await expect(offerInList).toBeVisible();
        });
    });

    test.describe('Gesuch erstellen', () => {
        test('Gesuch-Formular ist erreichbar', async ({ page }) => {
            await loginAsMember(page);
            // HINWEIS: Die Route heißt "anfrage-erstellen" (nicht "gesuch-erstellen")
            await page.goto('/romantauschboerse/anfrage-erstellen');

            await expect(page).toHaveURL(/anfrage-erstellen$/);
            
            // Warte auf das Formular, um sicherzugehen dass die Seite vollständig geladen ist
            await expect(page.locator('#request-form')).toBeVisible();
            
            // Die Überschrift lautet "Neues Gesuch erstellen" (aus dem Partial)
            // Verwende h1-Selektor als Fallback für webkit-Kompatibilität
            await expect(page.locator('h1').filter({ hasText: /Gesuch/i })).toBeVisible();
        });

        test('Erfolgreiches Erstellen eines Gesuchs', async ({ page }) => {
            await loginAsMember(page);
            // HINWEIS: Die Route heißt "anfrage-erstellen" (nicht "gesuch-erstellen")
            await page.goto('/romantauschboerse/anfrage-erstellen');

            // Warte auf das Formular bevor wir es ausfüllen
            await expect(page.locator('#request-form')).toBeVisible();

            // Formular ausfüllen
            await page.selectOption('select[name="series"]', SERIES_MADDRAX);
            await page.waitForFunction(
                (value) => {
                    const opt = document.querySelector(`select[name="book_number"] option[value="${value}"]`);
                    return opt && !opt.hidden && !opt.disabled;
                },
                '88'
            );
            await page.selectOption('select[name="book_number"]', '88');
            await page.selectOption('select[name="condition"]', CONDITION_Z2);

            // Absenden
            await page.click('button[type="submit"]');

            // Sollte zur Übersicht weiterleiten
            await expect(page).toHaveURL(/romantauschboerse$/);

            // Erfolgsmeldung prüfen
            const successMessage = page.locator('[data-testid="flash-success"], .bg-green-100, [role="alert"]').filter({ hasText: /Gesuch erstellt/i });
            await expect(successMessage).toBeVisible();
        });
    });

    test.describe('Angebot bearbeiten', () => {
        test('Bearbeiten-Link ist für eigene Angebote sichtbar', async ({ page }) => {
            await loginAsMember(page);
            
            // Erstelle zuerst ein Angebot
            await page.goto('/romantauschboerse/angebot-erstellen');
            await page.selectOption('select[name="series"]', SERIES_MADDRAX);
            await page.waitForFunction(
                (value) => {
                    const opt = document.querySelector(`select[name="book_number"] option[value="${value}"]`);
                    return opt && !opt.hidden && !opt.disabled;
                },
                '55'
            );
            await page.selectOption('select[name="book_number"]', '55');
            await page.selectOption('select[name="condition"]', CONDITION_Z1);
            await page.click('button[type="submit"]');
            
            await expect(page).toHaveURL(/romantauschboerse$/);

            // Bearbeiten-Link sollte sichtbar sein
            const editLink = page.locator('a[href*="/angebot/"][href*="/bearbeiten"]').first();
            await expect(editLink).toBeVisible();
        });

        test('Bearbeiten-Seite lädt für eigenes Angebot', async ({ page }) => {
            await loginAsMember(page);
            
            // Erstelle ein Angebot
            await page.goto('/romantauschboerse/angebot-erstellen');
            await page.selectOption('select[name="series"]', SERIES_MADDRAX);
            await page.waitForFunction(
                (value) => {
                    const opt = document.querySelector(`select[name="book_number"] option[value="${value}"]`);
                    return opt && !opt.hidden && !opt.disabled;
                },
                '66'
            );
            await page.selectOption('select[name="book_number"]', '66');
            await page.selectOption('select[name="condition"]', CONDITION_Z1);
            await page.click('button[type="submit"]');
            
            await expect(page).toHaveURL(/romantauschboerse$/);

            // Klicke auf Bearbeiten
            const editLink = page.locator('a[href*="/angebot/"][href*="/bearbeiten"]').first();
            await editLink.click();

            // Bearbeiten-Seite sollte laden
            await expect(page).toHaveURL(/angebot\/\d+\/bearbeiten$/);
            // Die Überschrift beim Bearbeiten lautet "Angebot bearbeiten" (aus dem Partial)
            await expect(page.getByRole('heading', { name: /Angebot bearbeiten/i })).toBeVisible();
        });
    });

    test.describe('Angebot löschen', () => {
        test('Löschen-Button ist für eigene Angebote sichtbar', async ({ page }) => {
            await loginAsMember(page);
            
            // Erstelle ein Angebot
            await page.goto('/romantauschboerse/angebot-erstellen');
            await page.selectOption('select[name="series"]', SERIES_MADDRAX);
            await page.waitForFunction(
                (value) => {
                    const opt = document.querySelector(`select[name="book_number"] option[value="${value}"]`);
                    return opt && !opt.hidden && !opt.disabled;
                },
                '33'
            );
            await page.selectOption('select[name="book_number"]', '33');
            await page.selectOption('select[name="condition"]', CONDITION_Z1);
            await page.click('button[type="submit"]');
            
            await expect(page).toHaveURL(/romantauschboerse$/);

            // Löschen-Button ist im Formular mit dem Text "Löschen" in einem span
            // Der Button enthält <span>Löschen</span>, daher suchen wir nach dem Text im Button
            const deleteButton = page.locator('form[action*="delete"] button, button:has-text("Löschen")').first();
            await expect(deleteButton).toBeVisible();
        });
    });
});
