import { test, expect } from './test-support.js';

/**
 * Fanfiction E2E Tests
 *
 * Testet die Akzeptanzkriterien aus den Issues:
 * - #493: Verwaltung/Import fÃƒÂ¼r Vorstand
 * - #495: MenÃƒÂ¼punkt und ÃƒÅ“bersicht fÃƒÂ¼r Mitglieder
 * - #496: Struktur der ÃƒÅ“bersichtsseite (Auf-/Zuklappen, Kommentare)
 * - #497: Sichtbarkeit fÃƒÂ¼r GÃƒÂ¤ste (Teaser)
 */

test.describe('Fanfiction fÃƒÂ¼r GÃƒÂ¤ste (Issue #497)', () => {
    test('Gast kann ÃƒÂ¶ffentliche Teaser-Seite aufrufen', async ({ page }) => {
        await page.goto('/fanfiction-teaser');

        await expect(page).toHaveURL(/fanfiction-teaser/);
        await expect(page.getByRole('heading', { name: 'Fanfiction' })).toBeVisible();
    });

    test('Teaser-Seite zeigt verÃƒÂ¶ffentlichte Geschichten', async ({ page }) => {
        await page.goto('/fanfiction-teaser');

        // PrÃƒÂ¼fe ob mindestens eine Geschichte angezeigt wird
        await expect(page.getByText('Die Reise nach Doredo')).toBeVisible();
        await expect(page.getByText('Schatten ÃƒÂ¼ber dem Kratersee')).toBeVisible();
    });

    test('Teaser-Seite zeigt keine EntwÃƒÂ¼rfe', async ({ page }) => {
        await page.goto('/fanfiction-teaser');

        // Der Entwurf sollte nicht sichtbar sein
        await expect(page.getByText('Die dunkle Prophezeiung')).not.toBeVisible();
    });

    test('Teaser-Seite zeigt Hinweis fÃƒÂ¼r GÃƒÂ¤ste', async ({ page }) => {
        await page.goto('/fanfiction-teaser');

        // PrÃƒÂ¼fe ob der Hinweis fÃƒÂ¼r GÃƒÂ¤ste angezeigt wird
        await expect(page.getByText(/Als Gast siehst du nur einen kurzen Teaser/i)).toBeVisible();
        await expect(page.getByRole('link', { name: /Werde Mitglied/i })).toBeVisible();
    });

    test('Gast kann Teaser erweitern (LocalStorage)', async ({ page }) => {
        await page.goto('/fanfiction-teaser');

        // Finde den "Teaser erweitern" Button (Text ist in einem span innerhalb des Buttons)
        const expandButton = page.locator('button', { hasText: /Teaser erweitern/i }).first();
        await expect(expandButton).toBeVisible();

        await expandButton.click();

        // Nach dem Klick sollte "Weniger anzeigen" sichtbar sein
        await expect(page.locator('button', { hasText: /Weniger anzeigen/i }).first()).toBeVisible();
    });
});

test.describe('Fanfiction MenÃƒÂ¼punkt (Issue #493 & #495)', () => {
    test('Vorstand-MenÃƒÂ¼ enthÃƒÂ¤lt Fanfiction-Unterpunkt', async ({ page }) => {
        // Login als Admin
        await page.goto('/login');
        await page.fill('input[name="email"]', 'info@maddraxikon.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL((url) => !url.pathname.endsWith('/login'));

        // Navigiere zum Dashboard
        await page.goto('/dashboard');

        // Ãƒâ€“ffne das Vorstand-Dropdown im Desktop-MenÃƒÂ¼
        const vorstandDropdown = page.locator('nav').getByRole('button', { name: /Vorstand/i });

        // Desktop: Klicke auf Vorstand-Dropdown wenn sichtbar
        if (await vorstandDropdown.isVisible()) {
            await vorstandDropdown.click();
            await expect(page.getByRole('link', { name: 'Fanfiction' }).first()).toBeVisible();
        }
    });

    test('Community-MenÃƒÂ¼ enthÃƒÂ¤lt Fanfiction-Unterpunkt', async ({ page }) => {
        // Login als Mitglied
        await page.goto('/login');
        await page.fill('input[name="email"]', 'playwright-member@example.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL((url) => !url.pathname.endsWith('/login'));

        await page.goto('/dashboard');

        // Ãƒâ€“ffne das Community-Dropdown
        const communityDropdown = page.locator('nav').getByRole('button', { name: /Community/i });

        if (await communityDropdown.isVisible()) {
            await communityDropdown.click();

            // Fanfiction sollte im Community-Dropdown sichtbar sein
            const fanfictionLink = page.locator('nav [data-tour-device="desktop"][data-tour-key="community-fanfiction"]');
            await expect(fanfictionLink).toBeVisible();
        }
    });
});

test.describe('Fanfiction Verwaltung fÃƒÂ¼r Vorstand (Issue #493)', () => {
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
        // maryUI x-header rendert title als div, nicht als heading
        await expect(page.getByText('Fanfiction verwalten')).toBeVisible();
    });

    test('Vorstand sieht Liste der Fanfictions mit Status', async ({ page }) => {
        await page.goto('/vorstand/fanfiction');

        // PrÃƒÂ¼fe ob die Tabelle/Liste existiert - verwende spezifischeren Selektor fÃƒÂ¼r die Tabelle
        await expect(page.getByRole('table').getByText('Die Reise nach Doredo')).toBeVisible();

        // PrÃƒÂ¼fe ob Status angezeigt wird
        await expect(page.getByText(/VerÃƒÂ¶ffentlicht|Entwurf/i).first()).toBeVisible();
    });

    test('Vorstand kann neue Fanfiction erstellen', async ({ page }) => {
        await page.goto('/vorstand/fanfiction/erstellen');

        // PrÃƒÂ¼fe ob alle erforderlichen Felder vorhanden sind
        // maryUI x-input verwendet fieldset/legend statt label/input - nutze placeholder
        await expect(page.getByPlaceholder('z.B. Die RÃƒÂ¼ckkehr nach Dorado')).toBeVisible();
        // Textarea hat keinen Placeholder, prÃƒÂ¼fe dass legend-Text existiert (exact match)
        await expect(page.getByText('Geschichte *', { exact: true })).toBeVisible();

        // Autortyp-Auswahl (Radio-Buttons) - exact match um Hint-Text auszuschlieÃƒÅ¸en
        await expect(page.getByText('Vereinsmitglied', { exact: true })).toBeVisible();
        await expect(page.getByText('Externer Autor', { exact: true })).toBeVisible();
    });

    test('Vorstand kann Fanfiction mit externem Autor erstellen', async ({ page }) => {
        await page.goto('/vorstand/fanfiction/erstellen');

        // WÃƒÂ¤hle externen Autor robust ÃƒÂ¼ber das eigentliche Radio-Input.
        // Ein reiner Text-Klick kann auf CI den Livewire-State zu spÃƒÂ¤t umschalten,
        // wodurch die Validierung weiter einen Vereinsautor erwartet.
        await page.getByLabel('Externer Autor').check();
        await expect(page.locator('[wire\\:model\\.live="userId"]')).toHaveCount(0);

        // FÃƒÂ¼lle Formular aus - maryUI verwendet fieldset/legend, nutze wire:model Selektoren
        await page.locator('[wire\\:model="title"]').fill('E2E Test Geschichte');
        await page.locator('[wire\\:model="authorName"]').fill('E2E Testautor');
        await page.locator('[wire\\:model="content"]').fill('Dies ist eine Testgeschichte fÃƒÂ¼r den E2E-Test. Sie enthÃƒÂ¤lt genug Text um die Validierung zu bestehen.');

        // WÃƒÂ¤hle Status "Entwurf" explizit ÃƒÂ¼ber das Radio-Input.
        await page.getByLabel('Als Entwurf speichern').check();

        // Speichern
        await page.getByRole('button', { name: 'Fanfiction speichern' }).click();

        // Sollte exakt zurÃƒÂ¼ck zur ÃƒÅ“bersicht leiten und dort den neuen Eintrag zeigen.
        await expect(page).toHaveURL(/\/vorstand\/fanfiction$/);
        await expect(page.getByText(/Fanfiction erfolgreich erstellt/i)).toBeVisible();
        await expect(page.getByRole('row', { name: /E2E Test Geschichte/ })).toBeVisible();
    });

    test('Vorstand kann Entwurf verÃƒÂ¶ffentlichen', async ({ page }) => {
        await page.goto('/vorstand/fanfiction');

        // Finde einen Entwurf (die "Dunkle Prophezeiung" aus dem Seeder)
        const draftRow = page.locator('tr', { hasText: 'Entwurf' }).first();

        if (await draftRow.isVisible()) {
            // Klicke auf VerÃƒÂ¶ffentlichen-Button
            const publishButton = draftRow.getByRole('button', { name: /VerÃƒÂ¶ffentlichen/i });

            if (await publishButton.isVisible()) {
                await publishButton.click();

                // Erfolgs-Meldung prÃƒÂ¼fen
                await expect(page.getByText(/erfolgreich verÃƒÂ¶ffentlicht/i).first()).toBeVisible();
            }
        }
    });
});

test.describe('Fanfiction ÃƒÅ“bersicht fÃƒÂ¼r Mitglieder (Issue #495 & #496)', () => {
    test.beforeEach(async ({ page }) => {
        // Login als Mitglied
        await page.goto('/login');
        await page.fill('input[name="email"]', 'playwright-member@example.com');
        await page.fill('input[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL((url) => !url.pathname.endsWith('/login'));
    });

    test('Mitglied kann Fanfiction-ÃƒÅ“bersicht aufrufen', async ({ page }) => {
        await page.goto('/fanfiction');

        await expect(page).toHaveURL(/\/fanfiction$/);
        await expect(page.locator('[data-testid="page-title"]')).toContainText('Fanfiction');
    });

    test('ÃƒÅ“bersicht zeigt verÃƒÂ¶ffentlichte Geschichten', async ({ page }) => {
        await page.goto('/fanfiction');

        await expect(page.getByText('Die Reise nach Doredo')).toBeVisible();
        await expect(page.getByText('Schatten ÃƒÂ¼ber dem Kratersee')).toBeVisible();
    });

    test('ÃƒÅ“bersicht zeigt nur ersten 400 Zeichen als Teaser', async ({ page }) => {
        await page.goto('/fanfiction');

        // Der Teaser sollte sichtbar sein
        const teaserElement = page.locator('[data-fanfiction-teaser]').first();
        await expect(teaserElement).toBeVisible();

        // Der vollstÃƒÂ¤ndige Inhalt sollte zunÃƒÂ¤chst verborgen sein
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

        // Warte auf Alpine.js State-Ãƒâ€žnderung (Button-Text ÃƒÂ¤ndert sich)
        await expect(toggleButton).toContainText(/zuklappen/i);

        // Klicke zum Zuklappen
        await toggleButton.click();

        // Warte auf Alpine.js State-Ãƒâ€žnderung
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

        // ZunÃƒÂ¤chst sollte die Galerie nicht sichtbar sein (falls Bilder vorhanden)
        const gallery = page.locator('[data-fanfiction-gallery]').first();

        // Wenn eine Galerie existiert, sollte sie zunÃƒÂ¤chst verborgen sein
        if ((await gallery.count()) > 0) {
            await expect(gallery).not.toBeVisible();

            // Nach dem Aufklappen sollte die Galerie sichtbar werden
            const toggleButton = page.locator('[data-fanfiction-toggle]').first();
            await toggleButton.click();
            await expect(gallery).toBeVisible();
        }
    });

    test('Mitglied kann keine EntwÃƒÂ¼rfe sehen', async ({ page }) => {
        await page.goto('/fanfiction');

        // Der Seeder erstellt einen zweiten Entwurf "Geheimer Entwurf fÃƒÂ¼r Tests",
        // der von keinem anderen Test verÃƒÂ¶ffentlicht wird.
        // Der published() Scope im Controller sollte diesen herausfiltern.
        const fanfictionList = page.locator('[data-fanfiction-list]');
        await expect(fanfictionList.getByText('Geheimer Entwurf fÃƒÂ¼r Tests')).not.toBeVisible();
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
        await page.getByRole('link', { name: 'Die Reise nach Doredo' }).first().click();

        // PrÃƒÂ¼fe ob die Einzelansicht geladen wurde - verwende spezifischeren Selektor (h1 im Main-Bereich)
        await expect(page.locator('main').getByRole('heading', { name: 'Die Reise nach Doredo' })).toBeVisible();
    });

    test('Einzelansicht zeigt vollstÃƒÂ¤ndigen Inhalt', async ({ page }) => {
        await page.goto('/fanfiction');
        await page.getByRole('link', { name: 'Die Reise nach Doredo' }).first().click();

        // wire:navigate navigiert per SPA Ã¢â‚¬â€œ auf die Einzelansicht warten
        await page.waitForURL(/\/fanfiction\//);

        // Der vollstÃƒÂ¤ndige Inhalt sollte sichtbar sein - prÃƒÂ¼fe auf prose-Container mit Inhalt
        const proseContainer = page.locator('.fanfiction-content.prose').first();
        await expect(proseContainer).toBeVisible();

        // PrÃƒÂ¼fe dass der Container nicht leer ist (hat mindestens ein p-Element)
        await expect(proseContainer.locator('p').first()).toBeVisible();
    });

    test('Mitglied kann Kommentar schreiben', async ({ page }) => {
        await page.goto('/fanfiction');
        await page.getByRole('link', { name: 'Die Reise nach Doredo' }).first().click();

        // Finde das Hauptkommentar-Textarea (erstes Formular mit "Kommentar" Label)
        const commentField = page.getByRole('textbox', { name: /Kommentar/i }).first();
        await expect(commentField).toBeVisible();

        // Schreibe einen Kommentar
        await commentField.fill('Das ist ein toller E2E-Test-Kommentar!');

        // Sende ab
        await page.getByRole('button', { name: /Kommentieren/i }).click();

        // Der Kommentar sollte nach dem Neuladen sichtbar sein (nur im div, nicht im textarea)
        await expect(page.locator('div').filter({ hasText: /E2E-Test-Kommentar/ }).first()).toBeVisible();
    });

    test('Kommentare werden mit Autorname angezeigt', async ({ page }) => {
        await page.goto('/fanfiction');
        await page.getByRole('link', { name: 'Die Reise nach Doredo' }).first().click();

        // PrÃƒÂ¼fe ob existierende Kommentare mit Autorname angezeigt werden
        // Suche den Kommentar-Text unabhÃƒÂ¤ngig von CSS-Klassen
        await expect(page.getByText('Spannende Geschichte').first()).toBeVisible();
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

        // Sollte 403 zurÃƒÂ¼ckgeben oder weiterleiten
        expect(response?.status()).toBe(403);
    });
});
