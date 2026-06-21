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
const completeValidBarbarExport = async (page) => {
    await page.getByTestId('char-editor-form').evaluate((form) => {
        const state = window.Alpine?.$data(form);

        if (!state) {
            throw new Error('Charakter-Editor-State konnte nicht gefunden werden.');
        }

        state.attributes.st = 2;
        state.attributes.ge = 1;

        for (const skill of state.skills) {
            if (!skill.valueDisabled) {
                skill.value = 4;
            }
        }

        state.skills.push({ name: 'Fahren', value: 4, source: null, locked: false, nameDisabled: false, valueDisabled: false, badge: null });
        state.skills.push({ name: 'Handeln', value: 4, source: null, locked: false, nameDisabled: false, valueDisabled: false, badge: null });
    });

    await expect(page.getByTestId('pdf-button')).toBeEnabled();
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

    test('oeffnet den PDF-Export browseruebergreifend ueber eine GET-Viewer-URL', async ({ page }) => {
        await openAdvancedEditor(page);
        await completeValidBarbarExport(page);

        const pdfRequests = [];
        const pdfViewerPath = /^\/rpg\/char-editor\/pdf\/[0-9a-f-]{36}$/;

        page.context().on('request', (request) => {
            const url = new URL(request.url());

            if (url.pathname.startsWith('/rpg/char-editor/pdf')) {
                pdfRequests.push({ method: request.method(), pathname: url.pathname });
            }
        });

        const [popup] = await Promise.all([
            page.waitForEvent('popup', { timeout: 5000 }),
            page.getByTestId('pdf-button').click(),
        ]);

        await expect.poll(() => pdfRequests.some((request) => request.method === 'GET' && pdfViewerPath.test(request.pathname))).toBe(true);
        expect(pdfRequests.filter((request) => request.method === 'POST' && request.pathname === '/rpg/char-editor/pdf')).toHaveLength(1);
        expect(pdfRequests.filter((request) => request.method === 'POST' && request.pathname !== '/rpg/char-editor/pdf')).toHaveLength(0);

        await popup.close().catch(() => {});
    });

    test('sendet gesperrte Basisdaten und automatisch gewährte Fertigkeiten im Formularpayload', async ({ page }) => {
        await openAdvancedEditor(page);

        const payload = await page.getByTestId('char-editor-form').evaluate((form) => {
            const data = new FormData(form);
            const skillsByIndex = {};

            for (const [key, value] of data.entries()) {
                const match = key.match(/^skills\[(\d+)]\[(name|value)]$/);

                if (!match) {
                    continue;
                }

                const [, index, field] = match;
                skillsByIndex[index] ??= {};
                skillsByIndex[index][field] = value;
            }

            return {
                playerName: data.get('player_name'),
                characterName: data.get('character_name'),
                race: data.get('race'),
                culture: data.get('culture'),
                skills: Object.values(skillsByIndex),
            };
        });

        expect(payload.playerName).toBe('Playwright Spieler');
        expect(payload.characterName).toBe('Wudan');
        expect(payload.race).toBe('Barbar');
        expect(payload.culture).toBe('Landbewohner');
        expect(payload.skills).toEqual(expect.arrayContaining([
            expect.objectContaining({ name: 'Nahkampf', value: '1' }),
            expect.objectContaining({ name: 'Beruf: Landwirt', value: '2' }),
        ]));
        expect(payload.skills.filter((skill) => skill.value && !skill.name)).toEqual([]);
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

    test('erzwingt Meeresbewohner als einzige Kultur fuer Hydrit', async ({ page }) => {
        await login(page, 'info@maddraxikon.com');
        await page.goto('/rpg/char-editor');

        await page.getByLabel('Spielername').fill('Playwright Spieler');
        await page.getByLabel('Charaktername').fill('Wudan');
        await page.locator('#culture').selectOption('Landbewohner');
        await expect(page.locator('#culture')).toHaveValue('Landbewohner');

        await page.locator('#race').selectOption('Hydrit');

        await expect(page.locator('#culture')).toHaveValue('Meeresbewohner');

        const cultureOptions = await page.locator('#culture').evaluate((select) => Object.fromEntries(
            Array.from(select.options).map((option) => [option.value || 'placeholder', option.disabled]),
        ));

        expect(cultureOptions).toMatchObject({
            Landbewohner: true,
            Stadtbewohner: true,
            Meeresbewohner: false,
        });

        await page.getByTestId('char-editor-continue-button').click();

        const payload = await page.getByTestId('char-editor-form').evaluate((form) => {
            const data = new FormData(form);

            return {
                race: data.get('race'),
                culture: data.get('culture'),
            };
        });

        expect(payload).toEqual({
            race: 'Hydrit',
            culture: 'Meeresbewohner',
        });
    });

    test('setzt Hydrit- und Meeresbewohner-Regeln inklusive Wahlboni im Formularpayload um', async ({ page }) => {
        await openAdvancedEditor(page, { race: 'Hydrit', culture: 'Meeresbewohner' });

        await expect(checkbox(page, 'advantages[]', 'Kiemen')).toBeChecked();
        await expect(checkbox(page, 'advantages[]', 'Kiemen')).toBeDisabled();
        await expect(checkbox(page, 'advantages[]', 'Natürliche Waffen')).toBeChecked();
        await expect(checkbox(page, 'advantages[]', 'Natürliche Waffen')).toBeDisabled();
        await expect(checkbox(page, 'disadvantages[]', 'Anfälligkeit gegen Wahnsinn')).toBeChecked();
        await expect(checkbox(page, 'disadvantages[]', 'Anfälligkeit gegen Wahnsinn')).toBeDisabled();
        await expect(page.getByText('Freie Vorteile: 2')).toBeVisible();

        await page.locator('#sea-profession-select').selectOption('Beruf: Künstler');
        await page.locator('#sea-knowledge-combat-select').selectOption('Nahkampf');

        const payload = await page.getByTestId('char-editor-form').evaluate((form) => {
            const data = new FormData(form);
            const skillsByIndex = {};

            for (const [key, value] of data.entries()) {
                const match = key.match(/^skills\[(\d+)]\[(name|value)]$/);

                if (!match) {
                    continue;
                }

                const [, index, field] = match;
                skillsByIndex[index] ??= {};
                skillsByIndex[index][field] = value;
            }

            return {
                race: data.get('race'),
                culture: data.get('culture'),
                advantages: data.getAll('advantages[]'),
                disadvantages: data.getAll('disadvantages[]'),
                skills: Object.values(skillsByIndex),
            };
        });

        expect(payload.race).toBe('Hydrit');
        expect(payload.culture).toBe('Meeresbewohner');
        expect(payload.advantages).toEqual(expect.arrayContaining(['Zäh', 'Kiemen', 'Natürliche Waffen']));
        expect(payload.disadvantages).toContain('Anfälligkeit gegen Wahnsinn');
        expect(payload.skills).toEqual(expect.arrayContaining([
            expect.objectContaining({ name: 'Athletik', value: '2' }),
            expect.objectContaining({ name: 'Bildung', value: '1' }),
            expect.objectContaining({ name: 'Natürliche Waffen', value: '1' }),
            expect.objectContaining({ name: 'Beruf: Künstler', value: '1' }),
            expect.objectContaining({ name: 'Nahkampf', value: '1' }),
        ]));
        expect(payload.skills.filter((skill) => skill.name === 'Athletik')).toHaveLength(1);
        expect(payload.skills).not.toEqual(expect.arrayContaining([
            expect.objectContaining({ name: 'Beruf: Farmer' }),
        ]));
        expect(payload.skills).not.toEqual(expect.arrayContaining([
            expect.objectContaining({ name: 'Wissenschaftler' }),
        ]));
    });

    test('erzwingt Bunkermensch als einzige Kultur fuer Techno', async ({ page }) => {
        await login(page, 'info@maddraxikon.com');
        await page.goto('/rpg/char-editor');

        await expect(page.locator('#culture option[value="Bunkermensch"]')).toBeDisabled();

        await page.getByLabel('Spielername').fill('Playwright Spieler');
        await page.getByLabel('Charaktername').fill('Wudan');
        await page.locator('#culture').selectOption('Landbewohner');
        await expect(page.locator('#culture')).toHaveValue('Landbewohner');

        await page.locator('#race').selectOption('Techno');

        await expect(page.locator('#culture')).toHaveValue('Bunkermensch');

        const cultureOptions = await page.locator('#culture').evaluate((select) => Object.fromEntries(
            Array.from(select.options).map((option) => [option.value || 'placeholder', option.disabled]),
        ));

        expect(cultureOptions).toMatchObject({
            Landbewohner: true,
            Stadtbewohner: true,
            Meeresbewohner: true,
            Bunkermensch: false,
        });

        await page.getByTestId('char-editor-continue-button').click();

        const payload = await page.getByTestId('char-editor-form').evaluate((form) => {
            const data = new FormData(form);

            return {
                race: data.get('race'),
                culture: data.get('culture'),
            };
        });

        expect(payload).toEqual({
            race: 'Techno',
            culture: 'Bunkermensch',
        });
    });

    test('setzt Techno- und Bunkermensch-Regeln inklusive Pool im Formularpayload um', async ({ page }) => {
        await openAdvancedEditor(page, { race: 'Techno', culture: 'Bunkermensch' });

        await expect(page.locator('#st')).toHaveValue('-1');
        await expect(page.locator('#ro')).toHaveValue('-1');
        await expect(page.locator('#in')).toHaveValue('1');
        await expect(checkbox(page, 'advantages[]', 'High-Tech-Ausrüstung')).toBeChecked();
        await expect(checkbox(page, 'advantages[]', 'High-Tech-Ausrüstung')).toBeDisabled();
        await expect(checkbox(page, 'disadvantages[]', 'Tödliche Immunschwäche')).toBeChecked();
        await expect(checkbox(page, 'disadvantages[]', 'Tödliche Immunschwäche')).toBeDisabled();
        await expect(page.getByText('Verteilt: 12 / 12')).toBeVisible();

        await page.getByTestId('techno-skill-points-input').first().fill('4');
        await page.getByTestId('techno-skill-points-input').nth(2).fill('0');
        await page.locator('#bunkermensch-bonus-select').selectOption('Pilot');

        const payload = await page.getByTestId('char-editor-form').evaluate((form) => {
            const data = new FormData(form);
            const skillsByIndex = {};

            for (const [key, value] of data.entries()) {
                const match = key.match(/^skills\[(\d+)]\[(name|value)]$/);

                if (!match) {
                    continue;
                }

                const [, index, field] = match;
                skillsByIndex[index] ??= {};
                skillsByIndex[index][field] = value;
            }

            return {
                race: data.get('race'),
                culture: data.get('culture'),
                advantages: data.getAll('advantages[]'),
                disadvantages: data.getAll('disadvantages[]'),
                skills: Object.values(skillsByIndex),
            };
        });

        expect(payload.race).toBe('Techno');
        expect(payload.culture).toBe('Bunkermensch');
        expect(payload.advantages).toEqual(expect.arrayContaining(['Zäh', 'High-Tech-Ausrüstung']));
        expect(payload.disadvantages).toContain('Tödliche Immunschwäche');
        expect(payload.skills).toEqual(expect.arrayContaining([
            expect.objectContaining({ name: 'Fahren', value: '4' }),
            expect.objectContaining({ name: 'Feuerwaffen', value: '2' }),
            expect.objectContaining({ name: 'Pilot', value: '3' }),
            expect.objectContaining({ name: 'Techniker', value: '2' }),
            expect.objectContaining({ name: 'Wissenschaftler', value: '2' }),
            expect.objectContaining({ name: 'Bildung', value: '1' }),
            expect.objectContaining({ name: 'Nahkampf', value: '1' }),
        ]));
        expect(payload.skills).not.toEqual(expect.arrayContaining([
            expect.objectContaining({ name: 'Heiler' }),
        ]));
    });
});
