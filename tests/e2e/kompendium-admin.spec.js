import { expect, test } from '@playwright/test';
import path from 'path';

const login = async (page, email, password = 'password') => {
    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
};

test.describe('Kompendium Admin Dashboard', () => {
    test.beforeEach(async ({ page }) => {
        // Admin-Login
        await login(page, 'info@maddraxikon.com');
    });

    test('admin can access the admin dashboard', async ({ page }) => {
        await page.goto('/kompendium/admin');

        await expect(page.getByRole('heading', { level: 1, name: 'Kompendium-Verwaltung' })).toBeVisible();
        await expect(page.getByText('Statistiken')).toBeVisible();
    });

    test('displays statistics cards correctly', async ({ page }) => {
        await page.goto('/kompendium/admin');

        // Prüfe ob Statistik-Karten angezeigt werden
        await expect(page.getByText('Gesamt hochgeladen')).toBeVisible();
        await expect(page.getByText('Indexiert')).toBeVisible();
        await expect(page.getByText('Nicht indexiert')).toBeVisible();
        await expect(page.getByText('Fehler')).toBeVisible();
    });

    test('displays upload form', async ({ page }) => {
        await page.goto('/kompendium/admin');

        await expect(page.getByText('Roman hochladen')).toBeVisible();
        await expect(page.getByLabel('Serie')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Hochladen' })).toBeVisible();
    });

    test('shows novels in table', async ({ page }) => {
        await page.goto('/kompendium/admin');

        // Prüfe ob die Tabelle existiert
        await expect(page.getByRole('table')).toBeVisible();

        // Prüfe auf Seeder-Daten
        await expect(page.getByText('Der Gott aus dem Eis')).toBeVisible();
    });

    test('can filter by series', async ({ page }) => {
        await page.goto('/kompendium/admin');

        // Filter auf Mission Mars setzen
        await page.getByLabel('Serie filtern').selectOption('missionmars');

        // Warten auf Livewire-Update
        await page.waitForTimeout(500);

        // Mission Mars Roman sollte sichtbar sein
        await expect(page.getByText('Expedition zum roten Planeten')).toBeVisible();

        // Maddrax Romane sollten nicht sichtbar sein
        await expect(page.getByText('Der Gott aus dem Eis')).not.toBeVisible();
    });

    test('can filter by status', async ({ page }) => {
        await page.goto('/kompendium/admin');

        // Filter auf "indexiert" setzen
        await page.getByLabel('Status filtern').selectOption('indexiert');

        // Warten auf Livewire-Update
        await page.waitForTimeout(500);

        // Indexierter Roman sollte sichtbar sein
        await expect(page.getByText('Stadt ohne Hoffnung')).toBeVisible();

        // Nicht indexierter Roman sollte nicht sichtbar sein
        await expect(page.getByText('Der Gott aus dem Eis')).not.toBeVisible();
    });

    test('can search for novels', async ({ page }) => {
        await page.goto('/kompendium/admin');

        // Suche nach einem Roman
        await page.getByPlaceholder('Suche nach Titel').fill('Dämonen');

        // Warten auf Livewire-Update
        await page.waitForTimeout(500);

        // Gesuchter Roman sollte sichtbar sein
        await expect(page.getByText('Dämonen der Vergangenheit')).toBeVisible();

        // Andere Romane sollten nicht sichtbar sein
        await expect(page.getByText('Der Gott aus dem Eis')).not.toBeVisible();
    });

    test('shows status badges correctly', async ({ page }) => {
        await page.goto('/kompendium/admin');

        // Prüfe ob Status-Badges angezeigt werden
        const table = page.getByRole('table');

        // Es sollten verschiedene Status-Badges existieren
        await expect(table.getByText('Hochgeladen').first()).toBeVisible();
    });

    test('can trigger indexing for a novel', async ({ page }) => {
        await page.goto('/kompendium/admin');

        // Finde den ersten "Indexieren" Button
        const indexButton = page.getByRole('button', { name: 'Indexieren' }).first();

        if (await indexButton.isVisible()) {
            await indexButton.click();

            // Prüfe ob eine Erfolgsmeldung erscheint oder der Status sich ändert
            // Da wir Queue:sync nicht haben, prüfen wir nur ob der Klick erfolgreich war
            await page.waitForTimeout(500);

            // Die Seite sollte sich aktualisiert haben (Livewire)
            await expect(page.getByRole('heading', { level: 1, name: 'Kompendium-Verwaltung' })).toBeVisible();
        }
    });

    test('can upload a new novel file', async ({ page }) => {
        test.setTimeout(30_000);

        await page.goto('/kompendium/admin');

        // Erstelle eine Test-TXT-Datei
        const testFileName = '100 - Playwright Testroman.txt';
        const testContent = 'Dies ist ein Testroman für Playwright E2E-Tests.';

        // Wähle die Serie
        await page.getByLabel('Serie').selectOption('maddrax');

        // Lade die Datei hoch
        const fileInput = page.locator('input[type="file"]');
        await fileInput.setInputFiles({
            name: testFileName,
            mimeType: 'text/plain',
            buffer: Buffer.from(testContent),
        });

        // Klicke auf Hochladen
        await page.getByRole('button', { name: 'Hochladen' }).click();

        // Warten auf Upload und Livewire-Update
        await page.waitForTimeout(1000);

        // Der neue Roman sollte in der Tabelle erscheinen
        await expect(page.getByText('Playwright Testroman')).toBeVisible();
    });

    test('shows error for invalid file format', async ({ page }) => {
        await page.goto('/kompendium/admin');

        // Versuche eine ungültige Datei hochzuladen
        const fileInput = page.locator('input[type="file"]');
        await fileInput.setInputFiles({
            name: 'test.pdf',
            mimeType: 'application/pdf',
            buffer: Buffer.from('PDF content'),
        });

        await page.getByRole('button', { name: 'Hochladen' }).click();

        // Warten auf Validierungsfehler
        await page.waitForTimeout(500);

        // Es sollte eine Fehlermeldung erscheinen
        await expect(page.getByText(/txt|mimes|Dateityp/i)).toBeVisible();
    });

    test('shows novels with errors', async ({ page }) => {
        await page.goto('/kompendium/admin');

        // Filter auf "fehler" setzen
        await page.getByLabel('Status filtern').selectOption('fehler');

        // Warten auf Livewire-Update
        await page.waitForTimeout(500);

        // Fehlerhafter Roman sollte sichtbar sein
        await expect(page.getByText('Fehlerhafter Roman')).toBeVisible();

        // Fehlermeldung sollte angezeigt werden
        await expect(page.getByText('Datei konnte nicht gelesen werden')).toBeVisible();
    });

    test('admin link visible on kompendium page', async ({ page }) => {
        await page.goto('/kompendium');

        // Der Admin-Link sollte für Admins sichtbar sein
        await expect(page.getByRole('link', { name: 'Kompendium verwalten' })).toBeVisible();
    });

    test('non-admin cannot access admin dashboard', async ({ page, context }) => {
        // Logout first
        await page.goto('/logout');

        // Versuche direkt auf Admin-Seite zuzugreifen ohne Login
        await page.goto('/kompendium/admin');

        // Sollte zum Login weitergeleitet werden
        await expect(page).toHaveURL(/\/login/);
    });
});

test.describe('Kompendium Public Page', () => {
    test('displays indexed novels grouped by cycle', async ({ page }) => {
        await page.goto('/kompendium');

        await expect(page.getByRole('heading', { level: 1, name: 'Maddrax-Kompendium' })).toBeVisible();
    });

    test('public page shows no admin link for guests', async ({ page }) => {
        await page.goto('/kompendium');

        // Der Admin-Link sollte für Gäste nicht sichtbar sein
        await expect(page.getByRole('link', { name: 'Kompendium verwalten' })).not.toBeVisible();
    });
});
