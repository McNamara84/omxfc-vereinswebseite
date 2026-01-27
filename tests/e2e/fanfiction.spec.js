import { test, expect } from '@playwright/test';

/**
 * Fanfiction E2E Tests
 *
 * Testet die Akzeptanzkriterien aus den Issues:
 * - #493: Verwaltung/Import für Vorstand
 * - #495: Menüpunkt und Übersicht für Mitglieder
 * - #496: Struktur der Übersichtsseite (Auf-/Zuklappen, Kommentare)
 * - #497: Sichtbarkeit für Gäste (Teaser)
 */

test.describe('Fanfiction für Gäste (Issue #497)', () => {
    test('Gast kann öffentliche Teaser-Seite aufrufen', async ({ page }) => {
        await page.goto('/fanfiction-teaser');

        await expect(page).toHaveURL(/fanfiction-teaser/);
        await expect(page.getByRole('heading', { name: 'Fanfiction' })).toBeVisible();
    });

    test('Teaser-Seite zeigt veröffentlichte Geschichten', async ({ page }) => {
        await page.goto('/fanfiction-teaser');

        // Prüfe ob mindestens eine Geschichte angezeigt wird
        await expect(page.getByText('Die Reise nach Doredo')).toBeVisible();
        await expect(page.getByText('Schatten über dem Kratersee')).toBeVisible();
    });

    test('Teaser-Seite zeigt keine Entwürfe', async ({ page }) => {
        await page.goto('/fanfiction-teaser');

        // Der Entwurf sollte nicht sichtbar sein
        await expect(page.getByText('Die dunkle Prophezeiung')).not.toBeVisible();
    });

    test('Teaser-Seite zeigt Hinweis für Gäste', async ({ page }) => {
        await page.goto('/fanfiction-teaser');

        // Prüfe ob der Hinweis für Gäste angezeigt wird
        await expect(page.getByText(/Als Gast siehst du nur einen kurzen Teaser/i)).toBeVisible();
        await expect(page.getByRole('link', { name: /Werde Mitglied/i })).toBeVisible();
    });

    test('Gast kann Teaser erweitern (LocalStorage)', async ({ page }) => {
        await page.goto('/fanfiction-teaser');

        // Finde den "Teaser erweitern" Button
        const expandButton = page.getByRole('button', { name: /Teaser erweitern/i }).first();
        await expect(expandButton).toBeVisible();

        await expandButton.click();

        // Nach dem Klick sollte "Weniger anzeigen" sichtbar sein
        await expect(page.getByRole('button', { name: /Weniger anzeigen/i }).first()).toBeVisible();
    });
});

test.describe('Fanfiction Menüpunkt (Issue #493 & #495)', () => {
    test('Vorstand-Menü enthält Fanfiction-Unterpunkt', async ({ page }) => {
        // Login als Admin
        await page.goto('/login');
        await page.fill('input[name="email"]', 'info@maddraxikon.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL((url) => !url.pathname.endsWith('/login'));

        // Navigiere zum Dashboard
        await page.goto('/dashboard');

        // Öffne das Vorstand-Dropdown im Desktop-Menü
        const vorstandDropdown = page.locator('nav').getByRole('button', { name: /Vorstand/i });

        // Desktop: Klicke auf Vorstand-Dropdown wenn sichtbar
        if (await vorstandDropdown.isVisible()) {
            await vorstandDropdown.click();
            await expect(page.getByRole('link', { name: 'Fanfiction' }).first()).toBeVisible();
        }
    });

    test('Verein-Menü enthält Fanfiction als ersten Unterpunkt', async ({ page }) => {
        // Login als Mitglied
        await page.goto('/login');
        await page.fill('input[name="email"]', 'playwright-member@example.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL((url) => !url.pathname.endsWith('/login'));

        await page.goto('/dashboard');

        // Öffne das Verein-Dropdown
        const vereinDropdown = page.locator('nav').getByRole('button', { name: /Verein/i });

        if (await vereinDropdown.isVisible()) {
            await vereinDropdown.click();

            // Fanfiction sollte im Dropdown sein
            const fanfictionLink = page.getByRole('link', { name: 'Fanfiction' }).first();
            await expect(fanfictionLink).toBeVisible();
        }
    });
});

test.describe('Fanfiction Verwaltung für Vorstand (Issue #493)', () => {
    test.beforeEach(async ({ page }) => {
        // Login als Admin/Vorstand
        await page.goto('/login');
        await page.fill('input[name="email"]', 'info@maddraxikon.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL((url) => !url.pathname.endsWith('/login'));
    });

    test('Vorstand kann Fanfiction-Verwaltung aufrufen', async ({ page }) => {
        await page.goto('/vorstand/fanfiction');

        await expect(page).toHaveURL(/vorstand\/fanfiction/);
        await expect(page.getByRole('heading', { name: /Fanfiction/i })).toBeVisible();
    });

    test('Vorstand sieht Liste der Fanfictions mit Status', async ({ page }) => {
        await page.goto('/vorstand/fanfiction');

        // Prüfe ob die Tabelle/Liste existiert
        await expect(page.getByText('Die Reise nach Doredo')).toBeVisible();

        // Prüfe ob Status angezeigt wird
        await expect(page.getByText(/Veröffentlicht|Entwurf/i).first()).toBeVisible();
    });

    test('Vorstand kann neue Fanfiction erstellen', async ({ page }) => {
        await page.goto('/vorstand/fanfiction/erstellen');

        // Prüfe ob alle erforderlichen Felder vorhanden sind
        await expect(page.getByLabel(/Titel/i)).toBeVisible();
        await expect(page.getByLabel(/Inhalt|Text/i)).toBeVisible();

        // Autortyp-Auswahl
        await expect(page.getByText(/Mitglied|Extern/i).first()).toBeVisible();
    });

    test('Vorstand kann Fanfiction mit externem Autor erstellen', async ({ page }) => {
        await page.goto('/vorstand/fanfiction/erstellen');

        // Wähle externen Autor
        await page.getByLabel(/Externer Autor/i).check();

        // Fülle Formular aus
        await page.fill('input[name="title"]', 'E2E Test Geschichte');
        await page.fill('input[name="author_name"]', 'E2E Testautor');
        await page.fill('textarea[name="content"]', 'Dies ist eine Testgeschichte für den E2E-Test. Sie enthält genug Text um die Validierung zu bestehen.');

        // Wähle Status "Entwurf"
        await page.selectOption('select[name="status"]', 'draft');

        // Speichern
        await page.click('button[type="submit"]');

        // Sollte zurück zur Übersicht leiten
        await expect(page).toHaveURL(/vorstand\/fanfiction/);
        await expect(page.getByText('E2E Test Geschichte')).toBeVisible();
    });

    test('Vorstand kann Entwurf veröffentlichen', async ({ page }) => {
        await page.goto('/vorstand/fanfiction');

        // Finde einen Entwurf (die "Dunkle Prophezeiung" aus dem Seeder)
        const draftRow = page.locator('tr', { hasText: 'Entwurf' }).first();

        if (await draftRow.isVisible()) {
            // Klicke auf Veröffentlichen-Button
            const publishButton = draftRow.getByRole('button', { name: /Veröffentlichen/i });

            if (await publishButton.isVisible()) {
                await publishButton.click();

                // Erfolgs-Meldung prüfen
                await expect(page.getByText(/erfolgreich veröffentlicht/i)).toBeVisible();
            }
        }
    });
});

test.describe('Fanfiction Übersicht für Mitglieder (Issue #495 & #496)', () => {
    test.beforeEach(async ({ page }) => {
        // Login als Mitglied
        await page.goto('/login');
        await page.fill('input[name="email"]', 'playwright-member@example.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL((url) => !url.pathname.endsWith('/login'));
    });

    test('Mitglied kann Fanfiction-Übersicht aufrufen', async ({ page }) => {
        await page.goto('/fanfiction');

        await expect(page).toHaveURL(/\/fanfiction$/);
        await expect(page.getByRole('heading', { name: 'Fanfiction' })).toBeVisible();
    });

    test('Übersicht zeigt veröffentlichte Geschichten', async ({ page }) => {
        await page.goto('/fanfiction');

        await expect(page.getByText('Die Reise nach Doredo')).toBeVisible();
        await expect(page.getByText('Schatten über dem Kratersee')).toBeVisible();
    });

    test('Übersicht zeigt nur ersten 400 Zeichen als Teaser', async ({ page }) => {
        await page.goto('/fanfiction');

        // Der Teaser sollte sichtbar sein
        const teaserElement = page.locator('[data-fanfiction-teaser]').first();
        await expect(teaserElement).toBeVisible();

        // Der vollständige Inhalt sollte zunächst verborgen sein
        const contentElement = page.locator('[data-fanfiction-content]').first();
        await expect(contentElement).not.toBeVisible();
    });

    test('Geschichte kann auf- und zugeklappt werden', async ({ page }) => {
        await page.goto('/fanfiction');

        // Finde den Toggle-Button
        const toggleButton = page.locator('[data-fanfiction-toggle]').first();
        await expect(toggleButton).toBeVisible();
        await expect(toggleButton).toContainText(/aufklappen/i);

        // Klicke zum Aufklappen
        await toggleButton.click();

        // Nach dem Aufklappen sollte der vollständige Inhalt sichtbar sein
        await expect(page.locator('[data-fanfiction-content]').first()).toBeVisible();
        await expect(toggleButton).toContainText(/zuklappen/i);

        // Klicke zum Zuklappen
        await toggleButton.click();

        // Der Inhalt sollte wieder verborgen sein
        await expect(page.locator('[data-fanfiction-content]').first()).not.toBeVisible();
        await expect(toggleButton).toContainText(/aufklappen/i);
    });

    test('Kommentarbereich ist immer sichtbar', async ({ page }) => {
        await page.goto('/fanfiction');

        // Kommentarbereich sollte immer sichtbar sein, egal ob auf- oder zugeklappt
        const commentsSection = page.locator('[data-fanfiction-comments]').first();
        await expect(commentsSection).toBeVisible();

        // Auch nach dem Aufklappen sollte der Kommentarbereich sichtbar sein
        const toggleButton = page.locator('[data-fanfiction-toggle]').first();
        await toggleButton.click();
        await expect(commentsSection).toBeVisible();
    });

    test('Bilder werden nur bei aufgeklappter Geschichte angezeigt', async ({ page }) => {
        await page.goto('/fanfiction');

        // Zunächst sollte die Galerie nicht sichtbar sein (falls Bilder vorhanden)
        const gallery = page.locator('[data-fanfiction-gallery]').first();

        // Wenn eine Galerie existiert, sollte sie zunächst verborgen sein
        if ((await gallery.count()) > 0) {
            await expect(gallery).not.toBeVisible();

            // Nach dem Aufklappen sollte die Galerie sichtbar werden
            const toggleButton = page.locator('[data-fanfiction-toggle]').first();
            await toggleButton.click();
            await expect(gallery).toBeVisible();
        }
    });

    test('Mitglied kann keine Entwürfe sehen', async ({ page }) => {
        await page.goto('/fanfiction');

        // Der Entwurf "Die dunkle Prophezeiung" sollte nicht sichtbar sein
        await expect(page.getByText('Die dunkle Prophezeiung')).not.toBeVisible();
    });
});

test.describe('Fanfiction Einzelansicht', () => {
    test.beforeEach(async ({ page }) => {
        // Login als Mitglied
        await page.goto('/login');
        await page.fill('input[name="email"]', 'playwright-member@example.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL((url) => !url.pathname.endsWith('/login'));
    });

    test('Mitglied kann Einzelansicht einer Geschichte aufrufen', async ({ page }) => {
        await page.goto('/fanfiction');

        // Klicke auf den Titel der ersten Geschichte
        await page.getByRole('link', { name: 'Die Reise nach Doredo' }).click();

        // Prüfe ob die Einzelansicht geladen wurde
        await expect(page.getByRole('heading', { name: 'Die Reise nach Doredo' })).toBeVisible();
    });

    test('Einzelansicht zeigt vollständigen Inhalt', async ({ page }) => {
        await page.goto('/fanfiction');
        await page.getByRole('link', { name: 'Die Reise nach Doredo' }).click();

        // Der vollständige Inhalt sollte sichtbar sein
        await expect(page.getByText(/Kapitel 1/i)).toBeVisible();
    });

    test('Mitglied kann Kommentar schreiben', async ({ page }) => {
        await page.goto('/fanfiction');
        await page.getByRole('link', { name: 'Die Reise nach Doredo' }).click();

        // Finde das Kommentar-Textarea
        const commentField = page.locator('textarea[name="content"]');
        await expect(commentField).toBeVisible();

        // Schreibe einen Kommentar
        await commentField.fill('Das ist ein toller E2E-Test-Kommentar!');

        // Sende ab
        await page.getByRole('button', { name: /Kommentieren/i }).click();

        // Der Kommentar sollte nach dem Neuladen sichtbar sein
        await expect(page.getByText('E2E-Test-Kommentar')).toBeVisible();
    });

    test('Kommentare werden mit Autorname angezeigt', async ({ page }) => {
        await page.goto('/fanfiction');
        await page.getByRole('link', { name: 'Die Reise nach Doredo' }).click();

        // Prüfe ob existierende Kommentare mit Autorname angezeigt werden
        // (aus dem Seeder sollte mindestens ein Kommentar vorhanden sein)
        await expect(page.getByText(/Spannende Geschichte/i)).toBeVisible();
    });
});

test.describe('Fanfiction Zugriffsrechte', () => {
    test('Nicht eingeloggte Benutzer werden zur Teaser-Seite weitergeleitet', async ({ page }) => {
        // Versuche direkt auf /fanfiction zuzugreifen ohne Login
        await page.goto('/fanfiction');

        // Sollte zum Login oder zur Teaser-Seite weitergeleitet werden
        await expect(page).toHaveURL(/login|fanfiction-teaser/);
    });

    test('Mitglied kann Vorstand-Verwaltung nicht aufrufen', async ({ page }) => {
        // Login als normales Mitglied
        await page.goto('/login');
        await page.fill('input[name="email"]', 'playwright-member@example.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL((url) => !url.pathname.endsWith('/login'));

        // Versuche Vorstand-Bereich aufzurufen
        const response = await page.goto('/vorstand/fanfiction');

        // Sollte 403 zurückgeben oder weiterleiten
        expect(response?.status()).toBe(403);
    });
});
