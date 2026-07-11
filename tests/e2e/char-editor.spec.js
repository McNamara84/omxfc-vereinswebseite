import { expect, test } from './test-support.js';
import { spawnSync } from 'child_process';
import { randomUUID } from 'node:crypto';
import { createPhpProcess } from './utils/php.js';

const login = async (page, email, password = 'password') => {
    await page.goto('/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
};

const createRpgEditorUser = (testInfo) => {
    const slug = `${testInfo.project.name}-${testInfo.workerIndex}-${Date.now()}-${randomUUID()}`
        .replace(/[^a-z0-9-]/gi, '-')
        .toLowerCase();
    const email = `char-editor-${slug}@example.test`;
    const phpProcess = createPhpProcess(['tests/e2e/create-rpg-editor-user.php', email], { env: process.env });
    const result = spawnSync(phpProcess.command, phpProcess.args, {
        env: process.env,
        shell: phpProcess.shell,
        encoding: 'utf8',
        windowsHide: process.platform === 'win32',
    });

    if (result.status !== 0) {
        throw new Error(`RPG-Testuser konnte nicht angelegt werden: ${result.stderr || result.stdout}`);
    }

    return email;
};

const openAdvancedEditor = async (page, {
    email = 'info@maddraxikon.com',
    race = 'Barbar',
    culture = 'Landbewohner',
    gender = 'maennlich',
    characterName = 'Wudan',
} = {}) => {
    await login(page, email);
    await page.goto('/rpg/char-editor');

    await page.getByLabel('Spielername').fill('Playwright Spieler');
    await page.getByLabel('Charaktername').fill(characterName);
    await page.locator('#gender').selectOption(gender);
    await page.locator('#race').selectOption(race);
    await page.locator('#culture').selectOption(culture);
    await page.getByTestId('char-editor-continue-button').click();

    await expect(page.getByTestId('char-editor-advantages-list')).toBeVisible();
    await expect(page.getByTestId('char-editor-disadvantages-list')).toBeVisible();
};

const checkbox = (page, name, value) => page.locator(`input[type="checkbox"][name="${name}"][value="${value}"]`);
const tinyPngBuffer = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=',
    'base64',
);

const completeValidBarbarExport = async (page) => {
    await page.getByTestId('char-editor-form').evaluate((form) => {
        const state = window.Alpine?.$data(form);

        if (!state) {
            throw new Error('Charakter-Editor-State konnte nicht gefunden werden.');
        }

        state.attributes.st = 2;
        state.attributes.ge = 1;

        const validSkillValues = new Map([
            ['Überleben', 4],
            ['Intuition', 4],
            ['Nahkampf', 4],
            ['Beruf: Viehzüchter', 2],
            ['Kunde: Wetter', 4],
        ]);

        for (const skill of state.skills) {
            if (!skill.valueDisabled && validSkillValues.has(skill.name)) {
                skill.value = validSkillValues.get(skill.name);
            }
        }

        state.skills.push({ name: 'Fahren', value: 4, source: null, locked: false, nameDisabled: false, valueDisabled: false, badge: null });
        state.skills.push({ name: 'Handeln', value: 4, source: null, locked: false, nameDisabled: false, valueDisabled: false, badge: null });

        state.clothing = 'kleidung-einfach';
        state.setEquipmentQuantity('messer-dolch', 1);
        state.setEquipmentQuantity('seil', 1);
        state.setEquipmentQuantity('rucksack', 1);
        state.setEquipmentQuantity('wasserschlauch', 1);
        state.setEquipmentQuantity('wochenration', 1);
        state.setEquipmentQuantity('bogen', 1);
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

        const attributesNavLink = page.getByTestId('char-editor-section-nav').getByRole('link', { name: 'Attribute' });
        await expect(attributesNavLink).toHaveAttribute('aria-disabled', 'true');
        await expect(attributesNavLink).toHaveAttribute('tabindex', '-1');

        const blockedNavigation = await attributesNavLink.evaluate((link) => {
            window.location.hash = '';
            link.focus();
            const enterEvent = new KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true });
            const enterDefaultPrevented = !link.dispatchEvent(enterEvent);
            link.click();

            return { enterDefaultPrevented, hash: window.location.hash };
        });
        expect(blockedNavigation).toEqual({ enterDefaultPrevented: true, hash: '' });
        await page.getByLabel('Spielername').fill('Playwright Spieler');

        await page.getByLabel('Charaktername').fill('Wudan');
        await page.locator('#gender').selectOption('maennlich');
        await page.locator('#race').selectOption('Barbar');
        await page.locator('#culture').selectOption('Landbewohner');

        await expect(continueButton).toBeVisible();
        await expect(continueButton).toBeEnabled();
        await expect(portraitPreview).toBeHidden();
        await continueButton.click();
        await expect(attributesNavLink).not.toHaveAttribute('aria-disabled', 'true');
        await expect(attributesNavLink).not.toHaveAttribute('tabindex', '-1');


        expect(pageErrors).toEqual([]);
        expect(consoleErrors.filter((message) => /\$persist|Cannot redefine property: \$persist/i.test(message))).toEqual([]);
    });


    test('sperrt High-Tech-Ausruestung im Editor ohne passenden Vorteil', async ({ page }) => {
        await openAdvancedEditor(page);

        await expect(page.getByTestId('char-editor-equipment-section')).toBeVisible();
        await page.getByTestId('equipment-clothing-select').selectOption('kleidung-einfach');
        await page.getByTestId('equipment-category-filter').selectOption('high_tech');

        const addFunkgeraet = page.getByRole('button', { name: 'Funkgerät hinzufügen', exact: true });

        await expect(addFunkgeraet).toBeDisabled();
        await expect(page.getByText('Benötigt High-Tech-Ausrüstung').first()).toBeVisible();

        await checkbox(page, 'advantages[]', 'High-Tech-Ausrüstung').check();
        await expect(addFunkgeraet).toBeEnabled();

        for (let i = 0; i < 4; i += 1) {
            await addFunkgeraet.click();
        }

        await expect(addFunkgeraet).toBeDisabled();
        await expect(page.getByText('High-Tech: 4 / 4')).toBeVisible();
        await expect(page.getByText('Gegenstände: 4 / 6 \u00b7 High-Tech: 4 / 4', { exact: true })).toBeVisible();

        const payload = await page.getByTestId('char-editor-form').evaluate((form) => {
            const data = new FormData(form);
            const equipmentByIndex = {};

            for (const [key, value] of data.entries()) {
                const match = key.match(/^equipment_items\[(\d+)]\[(id|quantity)]$/);

                if (!match) {
                    continue;
                }

                const [, index, field] = match;
                equipmentByIndex[index] ??= {};
                equipmentByIndex[index][field] = value;
            }

            return Object.values(equipmentByIndex);
        });

        expect(payload).toEqual([
            { id: 'funkgeraet', quantity: '4' },
        ]);
    });
    test('zeigt Rassen-Regelinfos direkt an der Auswahl', async ({ page }) => {
        await login(page, 'info@maddraxikon.com');
        await page.goto('/rpg/char-editor');

        await page.getByLabel('Spielername').fill('Playwright Spieler');
        await page.getByLabel('Charaktername').fill('Wudan');
        await page.locator('#gender').selectOption('maennlich');

        const raceSelect = page.locator('#race');
        await expect(raceSelect).not.toHaveAttribute('aria-describedby', 'race-info-panel');

        await raceSelect.focus();
        await raceSelect.evaluate((select) => {
            select.value = 'Guul';
            select.dispatchEvent(new Event('input', { bubbles: true }));
        });

        const raceInfo = page.getByTestId('race-info-panel');

        await expect(raceSelect).toHaveAttribute('aria-describedby', 'race-info-panel');
        await expect(raceInfo).toContainText('Guul');
        await expect(raceInfo).toContainText('AU -1');

        await page.locator('#race').selectOption('Techno');

        await expect(raceInfo).toContainText('Techno');
        await expect(raceInfo).toContainText('ST -1, RO -1, IN +1');
        await expect(raceInfo).toContainText('Bildung +3');
        await expect(raceInfo).toContainText('Tödliche Immunschwäche');
        await expect(page.getByTestId('culture-summary')).toContainText('Bunkermensch');
        await expect(page.getByTestId('culture-summary')).toContainText('Bildung +1');
        await expect(page.getByText('Kulturtext anzeigen')).toBeVisible();

        const description = page.getByTestId('char-editor-description');
        await description.fill('Eigener Text');
        await page.locator('#race').selectOption('Barbar');
        await page.locator('#culture').selectOption('Landbewohner');

        await expect(description).toHaveValue('Eigener Text');
        await expect(page.getByText('Manuell bearbeitet')).toBeVisible();
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

    test('speichert einen fertig ausgefuellten Charakter ohne Datenverlust', async ({ page }, testInfo) => {
        const email = createRpgEditorUser(testInfo);
        const characterName = `Wudan Save ${testInfo.project.name}`;
        const storeRequests = [];

        page.on('request', (request) => {
            const url = new URL(request.url());

            if (url.pathname.startsWith('/rpg/charaktere')) {
                storeRequests.push({ method: request.method(), pathname: url.pathname });
            }
        });

        await openAdvancedEditor(page, { email, characterName });
        await completeValidBarbarExport(page);

        await Promise.all([
            page.waitForURL((url) => url.pathname === '/rpg/charaktere'),
            page.getByTestId('submit-button').click(),
        ]);

        expect(storeRequests).toContainEqual({ method: 'POST', pathname: '/rpg/charaktere' });
        await expect(page.getByTestId('rpg-character-success')).toContainText('Charakter wurde gespeichert.');
        await expect(page.getByTestId('rpg-character-row')).toContainText(characterName);
        await expect(page.getByTestId('rpg-character-errors')).toHaveCount(0);
    });

    test('PDF-Popup zeigt keinen leeren Editor und der Ursprungstab bleibt danach speicherbar', async ({ page }, testInfo) => {
        const email = createRpgEditorUser(testInfo);
        const characterName = `Wudan PDF ${testInfo.project.name}`;
        const pdfViewerPath = /^\/rpg\/char-editor\/pdf\/[0-9a-f-]{36}$/;
        const pdfRequests = [];
        const editorReloadsAfterPdfClick = [];

        await openAdvancedEditor(page, { email, characterName });
        await completeValidBarbarExport(page);

        page.context().on('request', (request) => {
            const url = new URL(request.url());

            if (url.pathname.startsWith('/rpg/char-editor/pdf')) {
                pdfRequests.push({ method: request.method(), pathname: url.pathname });
            }

            if (request.method() === 'GET' && url.pathname === '/rpg/char-editor') {
                editorReloadsAfterPdfClick.push(url.pathname);
            }
        });

        const [popup] = await Promise.all([
            page.waitForEvent('popup', { timeout: 5000 }),
            page.getByTestId('pdf-button').click(),
        ]);

        await expect.poll(() => pdfRequests.some((request) => request.method === 'GET' && pdfViewerPath.test(request.pathname))).toBe(true);
        expect(pdfRequests.filter((request) => request.method === 'POST' && request.pathname === '/rpg/char-editor/pdf')).toHaveLength(1);
        expect(editorReloadsAfterPdfClick).toEqual([]);
        await expect.poll(() => new URL(page.url()).pathname).toBe('/rpg/char-editor');
        await expect(page.getByLabel('Charaktername')).toHaveValue(characterName);
        await expect(page.getByTestId('char-editor-advantages-list')).toBeVisible();

        await popup.close().catch(() => {});

        await Promise.all([
            page.waitForURL((url) => url.pathname === '/rpg/charaktere'),
            page.getByTestId('submit-button').click(),
        ]);

        await expect(page.getByTestId('rpg-character-success')).toContainText('Charakter wurde gespeichert.');
        await expect(page.getByTestId('rpg-character-row')).toContainText(characterName);
        await expect(page.getByTestId('rpg-character-errors')).toHaveCount(0);
    });

    test('zeigt Speicher-Validierungsfehler ohne Eingabeverlust', async ({ page }, testInfo) => {
        const email = createRpgEditorUser(testInfo);
        const characterName = `Wudan Invalid Save ${testInfo.project.name}`;

        await openAdvancedEditor(page, { email, characterName });
        await completeValidBarbarExport(page);
        await page.getByTestId('char-editor-form').evaluate((form) => {
            const state = window.Alpine?.$data(form);

            if (!state) {
                throw new Error('Charakter-Editor-State konnte nicht gefunden werden.');
            }

            state.portraitPreview = 'data:image/png;base64,bm90LWltYWdl';
        });

        await Promise.all([
            page.waitForURL((url) => url.pathname === '/rpg/char-editor'),
            page.getByTestId('submit-button').click(),
        ]);

        await expect(page.getByTestId('char-editor-errors')).toBeVisible();
        await expect(page.getByLabel('Charaktername')).toHaveValue(characterName);
        await expect(page.getByTestId('char-editor-advantages-list')).toBeVisible();
        await expect(page.getByTestId('equipment-clothing-select')).toHaveValue('kleidung-einfach');
    });

    test('PDF-Validierungsfehler zeigen im Popup den ausgefuellten Editor', async ({ page }, testInfo) => {
        const email = createRpgEditorUser(testInfo);
        const characterName = `Wudan Invalid PDF ${testInfo.project.name}`;

        await openAdvancedEditor(page, { email, characterName });
        await completeValidBarbarExport(page);
        await page.getByTestId('char-editor-form').evaluate((form) => {
            const state = window.Alpine?.$data(form);

            if (!state) {
                throw new Error('Charakter-Editor-State konnte nicht gefunden werden.');
            }

            state.portraitPreview = 'data:image/png;base64,bm90LWltYWdl';
        });

        const [popup] = await Promise.all([
            page.waitForEvent('popup', { timeout: 5000 }),
            page.getByTestId('pdf-button').click(),
        ]);

        await popup.waitForURL((url) => url.pathname === '/rpg/char-editor');
        await expect(popup.getByTestId('char-editor-errors')).toBeVisible();
        await expect(popup.getByLabel('Charaktername')).toHaveValue(characterName);
        await expect(popup.getByTestId('char-editor-advantages-list')).toBeVisible();
        await expect(popup.getByTestId('equipment-clothing-select')).toHaveValue('kleidung-einfach');
        await expect(page.getByLabel('Charaktername')).toHaveValue(characterName);

        await popup.close().catch(() => {});
    });

    test('erlaubt Portrait-Upload auch nach dem Freischalten der Regelsektionen', async ({ page }, testInfo) => {
        const email = createRpgEditorUser(testInfo);

        await openAdvancedEditor(page, { email, characterName: `Wudan Portrait ${testInfo.project.name}` });

        const portraitInput = page.locator('#portrait');

        await expect(portraitInput).toBeEnabled();
        await portraitInput.setInputFiles({
            name: 'portrait.png',
            mimeType: 'image/png',
            buffer: tinyPngBuffer,
        });

        await expect(page.getByTestId('char-editor-portrait-preview')).toBeVisible();
    });

    test('zeigt Attribut-Regelhilfe per Fokus und Hover', async ({ page }) => {
        await openAdvancedEditor(page);

        const strengthInput = page.locator('#st');
        const helpButton = page.getByTestId('attribute-help-st');
        const description = page.getByTestId('attribute-description-st');

        await expect(strengthInput).toHaveAttribute('aria-describedby', 'attribute-description-st');
        await expect(helpButton).toHaveAttribute('aria-controls', 'attribute-description-st');
        await expect(description).toContainText('Muskelkraft');
        await expect(description).toHaveClass(/sr-only/);

        await helpButton.focus();

        await expect(helpButton).toHaveAttribute('aria-expanded', 'true');
        await expect(description).not.toHaveClass(/sr-only/);
        await expect(description).toContainText('2W6 + Attributswert x 3');
        await expect(description).toContainText('Regelbereich aktuell: 0 bis 2');

        await strengthInput.focus();

        await expect(helpButton).toHaveAttribute('aria-expanded', 'false');
        await expect(description).toHaveClass(/sr-only/);

        await helpButton.dispatchEvent('mouseenter');

        await expect(description).not.toHaveClass(/sr-only/);

        await helpButton.dispatchEvent('mouseleave');

        await expect(description).toHaveClass(/sr-only/);
    });

    test('sendet gesperrte Basisdaten und automatisch gewährte Fertigkeiten im Formularpayload', async ({ page }) => {
        await openAdvancedEditor(page);

        await expect(page.locator('#barbar-attribute-select')).toHaveValue('st');
        await expect(page.locator('#st')).toHaveValue('1');
        await page.locator('#barbar-attribute-select').selectOption('ge');
        await expect(page.locator('#st')).toHaveValue('0');
        await expect(page.locator('#ge')).toHaveValue('1');

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
                gender: data.get('gender'),
                race: data.get('race'),
                culture: data.get('culture'),
                barbarAttributeBonus: data.get('barbar_attribute_bonus'),
                skills: Object.values(skillsByIndex),
            };
        });

        expect(payload.playerName).toBe('Playwright Spieler');
        expect(payload.characterName).toBe('Wudan');
        expect(payload.gender).toBe('maennlich');
        expect(payload.race).toBe('Barbar');
        expect(payload.culture).toBe('Landbewohner');
        expect(payload.barbarAttributeBonus).toBe('ge');
        expect(payload.skills).toEqual(expect.arrayContaining([
            expect.objectContaining({ name: 'Nahkampf', value: '1' }),
            expect.objectContaining({ name: 'Beruf: Viehzüchter', value: '2' }),
            expect.objectContaining({ name: 'Kunde: Wetter', value: '1' }),
        ]));
        expect(payload.skills.filter((skill) => skill.value && !skill.name)).toEqual([]);
    });

    test('zeigt Besonderheiten als Checkbox-Listen und begrenzt freie Vorteile', async ({ page }) => {
        await openAdvancedEditor(page);

        await expect(page.locator('select[name="advantages[]"]')).toHaveCount(0);
        await expect(page.locator('select[name="disadvantages[]"]')).toHaveCount(0);
        await expect(page.getByTestId('roll-advantage-button')).toBeVisible();
        await expect(page.getByTestId('roll-disadvantage-button')).toBeVisible();
        await expect(page.getByTestId('char-editor-advantages-list')).toContainText('13');
        await expect(page.getByTestId('char-editor-disadvantages-list')).toContainText('Taratzenfutter');
        await expect(page.getByTestId('char-editor-disadvantages-list')).toContainText('54-63');

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

        await page.getByTestId('char-editor-form').evaluate((form) => {
            const state = window.Alpine?.$data(form);
            const sequence = [6, 5];
            state.rollD6 = () => sequence.shift();
        });
        await page.getByTestId('roll-disadvantage-button').click();

        await expect(page.getByTestId('char-editor-roll-result')).toContainText('Verpflichtung wurde übernommen');
        await expect(checkbox(page, 'disadvantages[]', 'Verpflichtung')).toBeChecked();
    });

    test('zeigt Guul-Pflichtmerkmale ausgewählt, gesperrt und submitbar', async ({ page }) => {
        await openAdvancedEditor(page, { race: 'Guul', culture: 'Stadtbewohner' });

        const natuerlicheWaffen = checkbox(page, 'advantages[]', 'Natürliche Waffen');
        const primitiv = checkbox(page, 'disadvantages[]', 'Primitiv');
        const gejagt = checkbox(page, 'disadvantages[]', 'Gejagt');

        await expect(page.locator('#au')).toHaveValue('-1');
        await expect(natuerlicheWaffen).toBeChecked();
        await expect(natuerlicheWaffen).toBeDisabled();
        await expect(primitiv).toBeChecked();
        await expect(primitiv).toBeDisabled();
        await expect(gejagt).toBeChecked();
        await expect(gejagt).toBeDisabled();
        const advantageBadges = await page.getByTestId('char-editor-advantages-list').evaluate((list) => {
            const labelTexts = [...list.querySelectorAll('label')].map((label) => label.textContent);

            return {
                pflicht: labelTexts.filter((text) => text.includes('Pflicht')).length,
                rasse: labelTexts.filter((text) => text.includes('Rasse')).length,
            };
        });
        const disadvantagePflichtBadges = await page.getByTestId('char-editor-disadvantages-list').evaluate((list) => [...list.querySelectorAll('label')]
            .filter((label) => label.textContent.includes('Pflicht')).length);

        expect(advantageBadges).toEqual({ pflicht: 1, rasse: 1 });
        expect(disadvantagePflichtBadges).toBe(2);

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
                advantages: data.getAll('advantages[]'),
                disadvantages: data.getAll('disadvantages[]'),
                skills: Object.values(skillsByIndex),
            };
        });

        expect(payload.advantages).toContain('Natürliche Waffen');
        expect(payload.disadvantages).toContain('Primitiv');
        expect(payload.disadvantages).toContain('Gejagt');
        expect(payload.skills).toEqual(expect.arrayContaining([
            expect.objectContaining({ name: 'Natürliche Waffen', value: '1' }),
        ]));
    });

    test('setzt Taratze-Regeln inklusive Pflichtnachteilen im Formularpayload um', async ({ page }) => {
        await openAdvancedEditor(page, { race: 'Taratze', culture: 'Stadtbewohner' });

        await expect(page.locator('#st')).toHaveValue('1');
        await expect(page.locator('#wa')).toHaveValue('1');
        await expect(page.locator('#in')).toHaveValue('-1');
        await expect(page.locator('#au')).toHaveValue('-1');
        await expect(checkbox(page, 'disadvantages[]', 'Auffällig')).toBeChecked();
        await expect(checkbox(page, 'disadvantages[]', 'Auffällig')).toBeDisabled();
        await expect(checkbox(page, 'disadvantages[]', 'Primitiv')).toBeChecked();
        await expect(checkbox(page, 'disadvantages[]', 'Primitiv')).toBeDisabled();
        await expect(checkbox(page, 'disadvantages[]', 'Gejagt')).toBeChecked();
        await expect(checkbox(page, 'disadvantages[]', 'Gejagt')).toBeDisabled();

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
                st: data.get('attributes[st]'),
                wa: data.get('attributes[wa]'),
                in: data.get('attributes[in]'),
                au: data.get('attributes[au]'),
                disadvantages: data.getAll('disadvantages[]'),
                skills: Object.values(skillsByIndex),
            };
        });

        expect(payload.race).toBe('Taratze');
        expect(payload.culture).toBe('Stadtbewohner');
        expect(payload.st).toBe('1');
        expect(payload.wa).toBe('1');
        expect(payload.in).toBe('-1');
        expect(payload.au).toBe('-1');
        expect(payload.disadvantages).toEqual(expect.arrayContaining(['Auffällig', 'Primitiv', 'Gejagt']));
        expect(payload.skills).toEqual(expect.arrayContaining([
            expect.objectContaining({ name: 'Intuition', value: '2' }),
            expect.objectContaining({ name: 'Heimlichkeit', value: '1' }),
            expect.objectContaining({ name: 'Überleben', value: '1' }),
        ]));
    });

    test('setzt Wulfane-Regeln inklusive Ehrenkodex im Formularpayload um', async ({ page }) => {
        await openAdvancedEditor(page, { race: 'Wulfane', culture: 'Landbewohner' });

        await expect(page.locator('#ro')).toHaveValue('1');
        await expect(page.locator('#au')).toHaveValue('-1');
        await expect(checkbox(page, 'disadvantages[]', 'Ehrenkodex')).toBeChecked();
        await expect(checkbox(page, 'disadvantages[]', 'Ehrenkodex')).toBeDisabled();

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
                ro: data.get('attributes[ro]'),
                au: data.get('attributes[au]'),
                disadvantages: data.getAll('disadvantages[]'),
                skills: Object.values(skillsByIndex),
            };
        });

        expect(payload.race).toBe('Wulfane');
        expect(payload.culture).toBe('Landbewohner');
        expect(payload.ro).toBe('1');
        expect(payload.au).toBe('-1');
        expect(payload.disadvantages).toContain('Ehrenkodex');
        expect(payload.skills).toEqual(expect.arrayContaining([
            expect.objectContaining({ name: 'Intuition', value: '1' }),
            expect.objectContaining({ name: 'Nahkampf', value: '1' }),
        ]));
    });

    test('setzt Nosfera-Regeln inklusive Pflichtmerkmalen im Formularpayload um', async ({ page }) => {
        await openAdvancedEditor(page, { race: 'Nosfera', culture: 'Stadtbewohner' });

        await expect(page.locator('#ge')).toHaveValue('1');
        await expect(page.locator('#au')).toHaveValue('-1');
        await expect(checkbox(page, 'advantages[]', 'Nachtsicht')).toBeChecked();
        await expect(checkbox(page, 'advantages[]', 'Nachtsicht')).toBeDisabled();
        await expect(checkbox(page, 'advantages[]', 'Psychisches Reservoir')).not.toBeChecked();
        await expect(checkbox(page, 'advantages[]', 'Psychisches Reservoir')).toBeEnabled();
        await expect(checkbox(page, 'disadvantages[]', 'Blutdurst')).toBeChecked();
        await expect(checkbox(page, 'disadvantages[]', 'Blutdurst')).toBeDisabled();
        await expect(checkbox(page, 'disadvantages[]', 'Lichtscheu')).toBeChecked();
        await expect(checkbox(page, 'disadvantages[]', 'Lichtscheu')).toBeDisabled();
        await expect(checkbox(page, 'disadvantages[]', 'Gejagt')).toBeChecked();
        await expect(checkbox(page, 'disadvantages[]', 'Gejagt')).toBeDisabled();
        await expect(page.getByText('Freie Vorteile: 2')).toBeVisible();

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
                ge: data.get('attributes[ge]'),
                au: data.get('attributes[au]'),
                advantages: data.getAll('advantages[]'),
                disadvantages: data.getAll('disadvantages[]'),
                skills: Object.values(skillsByIndex),
            };
        });

        expect(payload.race).toBe('Nosfera');
        expect(payload.culture).toBe('Stadtbewohner');
        expect(payload.ge).toBe('1');
        expect(payload.au).toBe('-1');
        expect(payload.advantages).toEqual(expect.arrayContaining(['Zäh', 'Nachtsicht']));
        expect(payload.advantages).not.toContain('Psychisches Reservoir');
        expect(payload.disadvantages).toEqual(expect.arrayContaining(['Blutdurst', 'Lichtscheu', 'Gejagt']));
        expect(payload.skills).toEqual(expect.arrayContaining([
            expect.objectContaining({ name: 'Intuition', value: '2' }),
            expect.objectContaining({ name: 'Heimlichkeit', value: '2' }),
        ]));
    });

    test('erlaubt Disuuslachter nur fuer Barbaren und sendet die Kulturboni', async ({ page }) => {
        await login(page, 'info@maddraxikon.com');
        await page.goto('/rpg/char-editor');

        await page.getByLabel('Spielername').fill('Playwright Spieler');
        await page.getByLabel('Charaktername').fill('Wudan');
        await page.locator('#gender').selectOption('maennlich');

        await expect(page.locator('#culture option[value="Disuuslachter (Nordmann)"]')).toBeDisabled();

        await page.locator('#race').selectOption('Nosfera');
        await expect(page.locator('#culture option[value="Disuuslachter (Nordmann)"]')).toBeDisabled();

        await page.locator('#race').selectOption('Barbar');
        await expect(page.locator('#culture option[value="Disuuslachter (Nordmann)"]')).not.toBeDisabled();
        await page.locator('#culture').selectOption('Disuuslachter (Nordmann)');
        await page.getByTestId('char-editor-continue-button').click();

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
                skills: Object.values(skillsByIndex),
            };
        });

        expect(payload.race).toBe('Barbar');
        expect(payload.culture).toBe('Disuuslachter (Nordmann)');
        expect(payload.skills).toEqual(expect.arrayContaining([
            expect.objectContaining({ name: 'Nahkampf', value: '1' }),
            expect.objectContaining({ name: 'Überleben', value: '1' }),
            expect.objectContaining({ name: 'Beruf: Seemann', value: '1' }),
        ]));
    });

    test('erzwingt Meeresbewohner als einzige Kultur fuer Hydrit', async ({ page }) => {
        await login(page, 'info@maddraxikon.com');
        await page.goto('/rpg/char-editor');

        await page.getByLabel('Spielername').fill('Playwright Spieler');
        await page.getByLabel('Charaktername').fill('Wudan');
        await page.locator('#gender').selectOption('maennlich');
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
            Nomade: true,
            Ruinenbewohner: true,
            Untergrundbewohner: true,
            'Volk der 13 Inseln': true,
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

    test('erzwingt Mensch des 21. Jahrhunderts als einzige Kultur fuer Praekristofluu', async ({ page }) => {
        await login(page, 'info@maddraxikon.com');
        await page.goto('/rpg/char-editor');

        await expect(page.locator('#culture option[value="Mensch des 21. Jahrhunderts"]')).toBeDisabled();

        await page.getByLabel('Spielername').fill('Playwright Spieler');
        await page.getByLabel('Charaktername').fill('Wudan');
        await page.locator('#gender').selectOption('maennlich');
        await page.locator('#culture').selectOption('Landbewohner');
        await expect(page.locator('#culture')).toHaveValue('Landbewohner');

        await page.locator('#race').selectOption('Präkristofluu');

        await expect(page.locator('#culture')).toHaveValue('Mensch des 21. Jahrhunderts');

        const cultureOptions = await page.locator('#culture').evaluate((select) => Object.fromEntries(
            Array.from(select.options).map((option) => [option.value || 'placeholder', option.disabled]),
        ));

        expect(cultureOptions).toMatchObject({
            Landbewohner: true,
            Stadtbewohner: true,
            Meeresbewohner: true,
            Bunkermensch: true,
            'Mensch des 21. Jahrhunderts': false,
            Nomade: true,
            Ruinenbewohner: true,
            Untergrundbewohner: true,
            'Volk der 13 Inseln': true,
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
            race: 'Präkristofluu',
            culture: 'Mensch des 21. Jahrhunderts',
        });
    });

    test('setzt Praekristofluu- und Mensch-21-Regeln inklusive Pool im Formularpayload um', async ({ page }) => {
        await openAdvancedEditor(page, { race: 'Präkristofluu', culture: 'Mensch des 21. Jahrhunderts' });

        await expect(checkbox(page, 'advantages[]', 'High-Tech-Ausrüstung')).toBeChecked();
        await expect(checkbox(page, 'advantages[]', 'High-Tech-Ausrüstung')).toBeDisabled();
        await expect(page.getByText('Freie Vorteile: 2')).toBeVisible();
        await expect(page.getByText('Verteilt: 12 / 12')).toBeVisible();

        await page.getByTestId('praekristofluu-skill-points-input').first().fill('4');
        await page.getByTestId('praekristofluu-skill-points-input').nth(2).fill('0');
        await page.locator('#mensch-21-first-bonus-select').selectOption('Techniker');
        await page.locator('#mensch-21-second-bonus-select').selectOption('Wissenschaftler');

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
                skills: Object.values(skillsByIndex),
            };
        });

        expect(payload.race).toBe('Präkristofluu');
        expect(payload.culture).toBe('Mensch des 21. Jahrhunderts');
        expect(payload.advantages).toEqual(expect.arrayContaining(['Zäh', 'High-Tech-Ausrüstung']));
        expect(payload.skills).toEqual(expect.arrayContaining([
            expect.objectContaining({ name: 'Beruf', value: '3' }),
            expect.objectContaining({ name: 'Bildung', value: '4' }),
            expect.objectContaining({ name: 'Fahren', value: '2' }),
            expect.objectContaining({ name: 'Pilot', value: '2' }),
            expect.objectContaining({ name: 'Techniker', value: '3' }),
            expect.objectContaining({ name: 'Wissenschaftler', value: '3' }),
        ]));
        expect(payload.skills).not.toEqual(expect.arrayContaining([
            expect.objectContaining({ name: 'Feuerwaffen' }),
        ]));
    });

    test('erlaubt Volk der 13 Inseln nur fuer Barbaren und erzwingt Psychische Kraft fuer weiblich', async ({ page }) => {
        await login(page, 'info@maddraxikon.com');
        await page.goto('/rpg/char-editor');

        await page.getByLabel('Spielername').fill('Playwright Spieler');
        await page.getByLabel('Charaktername').fill('Wudan');
        await page.locator('#gender').selectOption('weiblich');

        await expect(page.locator('#culture option[value="Volk der 13 Inseln"]')).toBeDisabled();

        await page.locator('#race').selectOption('Guul');
        await expect(page.locator('#culture option[value="Volk der 13 Inseln"]')).toBeDisabled();

        await page.locator('#race').selectOption('Barbar');
        await expect(page.locator('#culture option[value="Volk der 13 Inseln"]')).not.toBeDisabled();
        await page.locator('#culture').selectOption('Volk der 13 Inseln');
        await page.getByTestId('char-editor-continue-button').click();

        await expect(checkbox(page, 'advantages[]', 'Psychische Kraft')).toBeChecked();
        await expect(checkbox(page, 'advantages[]', 'Psychische Kraft')).toBeDisabled();
        await expect(page.getByText('Freie Vorteile: 2')).toBeVisible();

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
                gender: data.get('gender'),
                race: data.get('race'),
                culture: data.get('culture'),
                advantages: data.getAll('advantages[]'),
                skills: Object.values(skillsByIndex),
            };
        });

        expect(payload.gender).toBe('weiblich');
        expect(payload.race).toBe('Barbar');
        expect(payload.culture).toBe('Volk der 13 Inseln');
        expect(payload.advantages).toEqual(expect.arrayContaining(['Z\u00e4h', 'Psychische Kraft']));
        expect(payload.skills).toEqual(expect.arrayContaining([
            expect.objectContaining({ name: 'Athletik', value: '1' }),
            expect.objectContaining({ name: '\u00dcberleben', value: '1' }),
            expect.objectContaining({ name: 'Beruf: Bauer', value: '1' }),
        ]));
    });

    test('setzt Nomade-Regeln inklusive Fernkampf-Mapping im Formularpayload um', async ({ page }) => {
        await openAdvancedEditor(page, { race: 'Barbar', culture: 'Nomade' });

        await page.locator('#nomade-combat-select').selectOption('Fernkampf');
        await page.locator('#nomade-movement-select').selectOption('Athletik');

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
                skills: Object.values(skillsByIndex),
            };
        });

        expect(payload.race).toBe('Barbar');
        expect(payload.culture).toBe('Nomade');
        expect(payload.skills).toEqual(expect.arrayContaining([
            expect.objectContaining({ name: 'Nahkampf', value: '1' }),
            expect.objectContaining({ name: 'Fernkampf', value: '1' }),
            expect.objectContaining({ name: 'Athletik', value: '1' }),
            expect.objectContaining({ name: '\u00dcberleben', value: '1' }),
        ]));
        expect(payload.skills).not.toEqual(expect.arrayContaining([
            expect.objectContaining({ name: 'Fernwaffen' }),
            expect.objectContaining({ name: 'Reiten' }),
        ]));
    });

    test('setzt Ruinenbewohner-Regeln inklusive Fernkampf-Mapping im Formularpayload um', async ({ page }) => {
        await openAdvancedEditor(page, { race: 'Barbar', culture: 'Ruinenbewohner' });

        await page.locator('#ruinenbewohner-bonus-select').selectOption('Fernkampf');

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
                skills: Object.values(skillsByIndex),
            };
        });

        expect(payload.race).toBe('Barbar');
        expect(payload.culture).toBe('Ruinenbewohner');
        expect(payload.skills).toEqual(expect.arrayContaining([
            expect.objectContaining({ name: 'Nahkampf', value: '1' }),
            expect.objectContaining({ name: 'Diebeskunst', value: '1' }),
            expect.objectContaining({ name: 'Heimlichkeit', value: '1' }),
            expect.objectContaining({ name: 'Fernkampf', value: '1' }),
        ]));
        expect(payload.skills).not.toEqual(expect.arrayContaining([
            expect.objectContaining({ name: 'Fernwaffen' }),
            expect.objectContaining({ name: 'Athletik' }),
            expect.objectContaining({ name: 'Kunde' }),
        ]));
    });

    test('setzt Untergrundbewohner-Regeln im Formularpayload um', async ({ page }) => {
        await openAdvancedEditor(page, { race: 'Barbar', culture: 'Untergrundbewohner' });

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
                skills: Object.values(skillsByIndex),
            };
        });

        expect(payload.race).toBe('Barbar');
        expect(payload.culture).toBe('Untergrundbewohner');
        expect(payload.skills).toEqual(expect.arrayContaining([
            expect.objectContaining({ name: 'Nahkampf', value: '1' }),
            expect.objectContaining({ name: 'Athletik', value: '1' }),
            expect.objectContaining({ name: 'Beruf: Bergmann', value: '1' }),
            expect.objectContaining({ name: '\u00dcberleben', value: '1' }),
        ]));
        expect(payload.skills.filter((skill) => skill.name === '\u00dcberleben')).toHaveLength(1);
    });

    test('setzt Techno automatisch wenn Bunkermensch zuerst gewaehlt wird', async ({ page }) => {
        await login(page, 'info@maddraxikon.com');
        await page.goto('/rpg/char-editor');

        await expect(page.locator('#culture option[value="Bunkermensch"]')).not.toBeDisabled();

        await page.getByLabel('Spielername').fill('Playwright Spieler');
        await page.getByLabel('Charaktername').fill('Wudan');
        await page.locator('#gender').selectOption('maennlich');
        await page.locator('#culture').selectOption('Bunkermensch');

        await expect(page.locator('#race')).toHaveValue('Techno');
        await expect(page.locator('#culture')).toHaveValue('Bunkermensch');

        const lockedRaceOptions = await page.locator('#race').evaluate((select) => Object.fromEntries(
            Array.from(select.options).map((option) => [option.value || 'placeholder', option.disabled]),
        ));

        expect(lockedRaceOptions).toMatchObject({
            Barbar: true,
            Guul: true,
            Hydrit: true,
            Techno: false,
        });

        const unlockedCultureOptions = await page.locator('#culture').evaluate((select) => Object.fromEntries(
            Array.from(select.options).map((option) => [option.value || 'placeholder', option.disabled]),
        ));

        expect(unlockedCultureOptions).toMatchObject({
            Landbewohner: false,
            Stadtbewohner: false,
            Meeresbewohner: true,
            Bunkermensch: false,
            Nomade: false,
            Ruinenbewohner: false,
            Untergrundbewohner: false,
            'Volk der 13 Inseln': true,
        });

        await page.locator('#culture').selectOption('Landbewohner');

        await expect(page.locator('#culture')).toHaveValue('Landbewohner');
        await expect(page.locator('#race')).toHaveValue('');

        const releasedRaceOptions = await page.locator('#race').evaluate((select) => Object.fromEntries(
            Array.from(select.options).map((option) => [option.value || 'placeholder', option.disabled]),
        ));

        expect(releasedRaceOptions).toMatchObject({
            Barbar: false,
            Guul: false,
            Hydrit: false,
            Techno: false,
        });

        await page.locator('#race').selectOption('Barbar');
        await page.getByTestId('char-editor-continue-button').click();

        const payload = await page.getByTestId('char-editor-form').evaluate((form) => {
            const data = new FormData(form);

            return {
                race: data.get('race'),
                culture: data.get('culture'),
            };
        });

        expect(payload).toEqual({
            race: 'Barbar',
            culture: 'Landbewohner',
        });
    });

    test('erzwingt Bunkermensch als einzige Kultur fuer manuell gewaehlt Techno', async ({ page }) => {
        await login(page, 'info@maddraxikon.com');
        await page.goto('/rpg/char-editor');

        await expect(page.locator('#culture option[value="Bunkermensch"]')).not.toBeDisabled();

        await page.getByLabel('Spielername').fill('Playwright Spieler');
        await page.getByLabel('Charaktername').fill('Wudan');
        await page.locator('#gender').selectOption('maennlich');
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
            Nomade: true,
            Ruinenbewohner: true,
            Untergrundbewohner: true,
            'Volk der 13 Inseln': true,
        });

        const raceOptions = await page.locator('#race').evaluate((select) => Object.fromEntries(
            Array.from(select.options).map((option) => [option.value || 'placeholder', option.disabled]),
        ));

        expect(raceOptions).toMatchObject({
            Barbar: false,
            Guul: false,
            Hydrit: false,
            Techno: false,
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
            expect.objectContaining({ name: 'Bildung', value: '3' }),
            expect.objectContaining({ name: 'Nahkampf', value: '1' }),
        ]));
        expect(payload.skills).not.toEqual(expect.arrayContaining([
            expect.objectContaining({ name: 'Heiler' }),
        ]));
    });
});
