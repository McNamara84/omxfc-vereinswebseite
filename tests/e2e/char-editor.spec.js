import { expect, test } from './test-support.js';

const login = async (page, email, password = 'password') => {
    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
};

test.describe('RPG Charakter-Editor', () => {
    test('lädt ohne Persist-Fehler und sperrt den Formularfluss initial korrekt', async ({ page }) => {
        test.setTimeout(60_000);

        const consoleErrors = [];
        const pageErrors = [];

        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                consoleErrors.push(msg.text());
            }
        });

        page.on('pageerror', (error) => {
            pageErrors.push(error.message);
        });

        await login(page, 'info@maddraxikon.com');
        await page.goto('/rpg/char-editor');

        await expect(page.getByTestId('page-header')).toContainText('Charakter-Editor');
        await expect(page.getByTestId('char-editor-form')).toBeVisible();

        const continueButton = page.getByTestId('char-editor-continue-button');
        const portraitPreview = page.getByTestId('char-editor-portrait-preview');
        await expect(continueButton).toBeHidden();
        await expect(portraitPreview).toBeHidden();

        await page.getByLabel('Spielername').fill('Playwright Spieler');
        await page.getByLabel('Charaktername').fill('Wudan');
        await page.locator('#race').selectOption('Barbar');
        await page.locator('#culture').selectOption('Landbewohner');

        await expect(continueButton).toBeVisible();
        await expect(continueButton).toBeEnabled();
        await expect(portraitPreview).toBeHidden();

        expect(pageErrors).toEqual([]);
        expect(consoleErrors.filter((message) => /\$persist|Cannot redefine property: \$persist/i.test(message))).toEqual([]);
    });
});