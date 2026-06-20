import { expect, test } from './test-support.js';

const login = async (page, email, password = 'password') => {
    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
};

const openAdvancedEditor = async (page, { race = 'Barbar', culture = 'Landbewohner' } = {}) => {
    await login(page, 'info@maddraxikon.com');
    await page.goto('/rpg/char-editor');

    await page.getByLabel('Spielername').fill('Playwright Spieler');
    await page.getByLabel('Charaktername').fill('Wudan');
    await page.locator('#race').selectOption(race);
    await page.locator('#culture').selectOption(culture);
    await page.getByTestId('char-editor-continue-button').click();

    await expect(page.getByTestId('char-editor-advantages-list')).toBeVisible();
    await expect(page.getByTestId('char-editor-disadvantages-list')).toBeVisible();
};

const checkbox = (page, name, value) => page.locator(`input[type="checkbox"][name="${name}"][value="${value}"]`);

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

    test('zeigt Besonderheiten als Checkbox-Listen und begrenzt freie Vorteile', async ({ page }) => {
        await openAdvancedEditor(page);

        await expect(page.locator('select[name="advantages[]"]')).toHaveCount(0);
        await expect(page.locator('select[name="disadvantages[]"]')).toHaveCount(0);

        const zaeh = checkbox(page, 'advantages[]', 'Zäh');
        await expect(zaeh).toBeChecked();
        await expect(zaeh).toBeDisabled();

        await checkbox(page, 'advantages[]', 'Schnell').check();
        await checkbox(page, 'advantages[]', 'Kampfreflexe').check();

        await expect(checkbox(page, 'advantages[]', 'Nachtsicht')).toBeDisabled();
        await expect(page.getByText('Freie Vorteile: 0')).toBeVisible();

        await checkbox(page, 'disadvantages[]', 'Auffällig').check();

        const payload = await page.getByTestId('char-editor-form').evaluate((form) => {
            const data = new FormData(form);

            return {
                advantages: data.getAll('advantages[]'),
                disadvantages: data.getAll('disadvantages[]'),
            };
        });

        expect(payload.advantages).toContain('Zäh');
        expect(payload.advantages).toContain('Schnell');
        expect(payload.advantages).toContain('Kampfreflexe');
        expect(payload.disadvantages).toContain('Auffällig');
    });

    test('zeigt Guul-Pflichtnachteile ausgewählt, gesperrt und submitbar', async ({ page }) => {
        await openAdvancedEditor(page, { race: 'Guul', culture: 'Stadtbewohner' });

        const primitiv = checkbox(page, 'disadvantages[]', 'Primitiv');
        const gejagt = checkbox(page, 'disadvantages[]', 'Gejagt');

        await expect(primitiv).toBeChecked();
        await expect(primitiv).toBeDisabled();
        await expect(gejagt).toBeChecked();
        await expect(gejagt).toBeDisabled();
        await expect(page.getByTestId('char-editor-disadvantages-list').getByText('Pflicht')).toHaveCount(2);

        const payload = await page.getByTestId('char-editor-form').evaluate((form) => {
            const data = new FormData(form);

            return data.getAll('disadvantages[]');
        });

        expect(payload).toContain('Primitiv');
        expect(payload).toContain('Gejagt');
    });
});
