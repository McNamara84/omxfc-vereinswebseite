import { expect, test } from '@playwright/test';

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

        // Korrekter Titel: "Kompendium-Administration"
        await expect(page.getByRole('heading', { level: 1, name: 'Kompendium-Administration' })).toBeVisible();
    });

    test('displays statistics cards correctly', async ({ page }) => {
        await page.goto('/kompendium/admin');

        // Prüfe ob Statistik-Karten angezeigt werden
        await expect(page.locator('.bg-white.rounded-lg.shadow').first()).toBeVisible();
    });

    test('displays upload form', async ({ page }) => {
        await page.goto('/kompendium/admin');

        await expect(page.getByText('Romane hochladen')).toBeVisible();
        await expect(page.locator('#serie')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Hochladen' })).toBeVisible();
    });

    test('shows novels in table', async ({ page }) => {
        await page.goto('/kompendium/admin');

        // Prüfe ob die Tabelle existiert
        await expect(page.getByRole('table')).toBeVisible();

        // Prüfe auf Seeder-Daten - verwende spezifische Zelle
        await expect(page.getByRole('cell', { name: 'Der Gott aus dem Eis' })).toBeVisible();
    });

    test('can filter by series', async ({ page }) => {
        await page.goto('/kompendium/admin');

        // Filter auf Mission Mars setzen - verwende die ID
        await page.locator('#filterSerie').selectOption('missionmars');

        // Warten auf Livewire-Update
        await page.waitForTimeout(1000);

        // Mission Mars Roman sollte sichtbar sein
        await expect(page.getByRole('cell', { name: 'Expedition zum roten Planeten' })).toBeVisible();

        // Maddrax Romane sollten nicht sichtbar sein
        await expect(page.getByRole('cell', { name: 'Der Gott aus dem Eis' })).not.toBeVisible();
    });

    test('can filter by status', async ({ page }) => {
        await page.goto('/kompendium/admin');

        // Filter auf "indexiert" setzen - verwende die ID
        await page.locator('#filterStatus').selectOption('indexiert');

        // Warten auf Livewire-Update
        await page.waitForTimeout(1000);

        // Indexierter Roman sollte sichtbar sein
        await expect(page.getByRole('cell', { name: 'Stadt ohne Hoffnung' })).toBeVisible();

        // Nicht indexierter Roman sollte nicht sichtbar sein
        await expect(page.getByRole('cell', { name: 'Der Gott aus dem Eis' })).not.toBeVisible();
    });

    test('can search for novels', async ({ page }) => {
        await page.goto('/kompendium/admin');

        // Suche nach einem Roman - verwende die ID
        await page.locator('#suchbegriff').fill('Dämonen');

        // Warten auf Livewire-Update (mit debounce)
        await page.waitForTimeout(1000);

        // Gesuchter Roman sollte sichtbar sein
        await expect(page.getByRole('cell', { name: 'Dämonen der Vergangenheit' })).toBeVisible();

        // Andere Romane sollten nicht sichtbar sein
        await expect(page.getByRole('cell', { name: 'Der Gott aus dem Eis' })).not.toBeVisible();
    });

    test('shows status badges correctly', async ({ page }) => {
        await page.goto('/kompendium/admin');

        // Prüfe ob Status-Badges angezeigt werden
        const table = page.getByRole('table');

        // Es sollten verschiedene Status-Badges existieren
        await expect(table.locator('span:has-text("Hochgeladen")').first()).toBeVisible();
    });

    test('shows action buttons for novels', async ({ page }) => {
        await page.goto('/kompendium/admin');

        // Für hochgeladene Romane sollte der Indexieren-Button sichtbar sein
        await expect(page.getByRole('button', { name: 'Indexieren' }).first()).toBeVisible();
    });

    // Upload-Test auskommentiert da er wegen Livewire-Timing instabil ist
    // Der Upload wird durch PHPUnit Feature-Tests abgedeckt
    test.skip('can upload a new novel file', async ({ page }) => {
        test.setTimeout(30_000);

        await page.goto('/kompendium/admin');

        // Erstelle eine Test-TXT-Datei
        const testFileName = '100 - Playwright Testroman.txt';
        const testContent = 'Dies ist ein Testroman für Playwright E2E-Tests.';

        // Wähle die Serie - verwende die ID für das Upload-Formular
        await page.locator('#serie').selectOption('maddrax');

        // Lade die Datei hoch
        const fileInput = page.locator('input[type="file"]');
        await fileInput.setInputFiles({
            name: testFileName,
            mimeType: 'text/plain',
            buffer: Buffer.from(testContent),
        });

        // Klicke auf Hochladen
        await page.getByRole('button', { name: 'Hochladen' }).click();

        // Warten auf Upload und Livewire-Update - Livewire braucht Zeit
        await page.waitForTimeout(3000);

        // Der neue Roman sollte in der Tabelle erscheinen
        await expect(page.getByText('Playwright Testroman')).toBeVisible();
    });

    test('shows novels with errors', async ({ page }) => {
        await page.goto('/kompendium/admin');

        // Filter auf "fehler" setzen
        await page.locator('#filterStatus').selectOption('fehler');

        // Warten auf Livewire-Update
        await page.waitForTimeout(1000);

        // Fehlerhafter Roman sollte sichtbar sein
        await expect(page.getByRole('cell', { name: 'Fehlerhafter Roman' })).toBeVisible();
    });

    test('admin link visible on kompendium page', async ({ page }) => {
        await page.goto('/kompendium');

        // Der Admin-Link sollte für Admins sichtbar sein
        await expect(page.getByRole('link', { name: 'Kompendium verwalten' })).toBeVisible();
    });

    test('mass actions buttons are visible', async ({ page }) => {
        await page.goto('/kompendium/admin');

        // Massen-Aktionen sollten sichtbar sein
        await expect(page.getByRole('button', { name: 'Alle indexieren' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Alle de-indexieren' })).toBeVisible();
    });
});

test.describe('Kompendium Public Page', () => {
    test('kompendium page requires authentication', async ({ page }) => {
        await page.goto('/kompendium');

        // Kompendium erfordert Login - wird zur Login-Seite weitergeleitet
        await expect(page).toHaveURL(/\/login/);
    });

    test('public page shows no admin link for guests', async ({ page }) => {
        await page.goto('/kompendium');

        // Der Admin-Link sollte für Gäste nicht sichtbar sein
        await expect(page.getByRole('link', { name: 'Kompendium verwalten' })).not.toBeVisible();
    });
});
