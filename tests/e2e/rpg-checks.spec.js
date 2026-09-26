import { expect, test } from './test-support.js';
import AxeBuilder from '@axe-core/playwright';
import { spawnSync } from 'node:child_process';
import { createPhpProcess } from './utils/php.js';

test.use({ actionTimeout: 20000, navigationTimeout: 30000 });

function fixture(scenario = '') {
    const command = createPhpProcess(['tests/e2e/create-rpg-check-fixture.php', scenario], { env: process.env });
    const result = spawnSync(command.command, command.args, { env: process.env, shell: command.shell, encoding: 'utf8', windowsHide: true });
    if (result.error || result.status !== 0) throw new Error(result.stderr || result.error?.message);
    return JSON.parse(result.stdout.trim());
}
async function login(page, email) {
    await page.goto('/login');
    await page.locator('input[name="email"]').fill(email);
    await page.locator('input[name="password"]').fill('password');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
}
async function addCharacter(page, id) {
    await page.getByRole('combobox', { name: 'Charakter auswählen', exact: true }).selectOption(String(id));
    await page.getByRole('button', { name: 'Charakter hinzufügen', exact: true }).click();
}
async function requestCheck(page, data, { hidden = false, opposed = false, group = false } = {}) {
    await page.goto('/rpg/proben/neu');
    await expect(page.locator('option[value="npc"]')).toHaveJSProperty('disabled', true);
    const comparison = page.getByRole('combobox', { name: 'Vergleich', exact: true });
    await comparison.selectOption(opposed ? 'opposed' : 'fixed');
    await page.getByRole('combobox', { name: 'Sichtbarkeit', exact: true }).selectOption(hidden ? 'hidden' : 'open');
    await page.getByLabel('Beschreibung (für Spieler sichtbar)', { exact: true }).fill('Die Brücke '+data.characters.player.name);
    if (!opposed) await page.getByLabel('Schwierigkeitsgrad', { exact: true }).fill(hidden ? '743' : '-100');
    await addCharacter(page, data.characters.player.id);
    if (group || opposed) await addCharacter(page, data.characters.other.id);
    if (hidden) {
        await page.getByRole('button', { name: 'Modifikator hinzufügen', exact: true }).first().click();
        await page.getByLabel('Bonus / Malus', { exact: true }).fill('913');
        await page.getByLabel('Grund des Modifikators', { exact: true }).fill('SECRET-BRIDGE');
    }
    await page.getByRole('button', { name: 'Vorschau prüfen', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Vorschau der Proben' })).toBeVisible();
    await page.getByRole('button', { name: 'Proben anfordern', exact: true }).click();
    await page.waitForURL(/\/rpg\/proben$/);
}
async function openOwnCheck(page, character) {
    await page.goto('/rpg/proben');
    const card = page.locator('article[data-check-id]').filter({ hasText: character.name });
    await card.getByRole('link').first().click();
    await page.waitForURL(/\/rpg\/proben\/\d+$/);
}

test('comparison is keyboard accessible and skips the disabled NPC option', async ({ page }) => {
    test.setTimeout(120000);
    const data = fixture();
    await login(page, data.leader);
    await page.goto('/rpg/proben/neu');
    const comparison = page.getByRole('combobox', { name: 'Vergleich', exact: true });
    await expect(page.locator('option[value="npc"]')).toHaveJSProperty('disabled', true);
    await comparison.focus();
    await expect(comparison).toBeFocused();
    // Chromium's customizable picker must be opened; Firefox uses a native select.
    const usesCustomPicker = await comparison.evaluate((element) => getComputedStyle(element).appearance === 'base-select');
    if (usesCustomPicker) await page.keyboard.press('Space');
    await page.keyboard.press('End');
    if (usesCustomPicker) await page.keyboard.press('Enter');
    await expect(comparison).toHaveValue('opposed');
    await expect(page.getByLabel('Schwierigkeitsgrad', { exact: true })).toBeHidden();
    // Tab leaves the picker without submitting the form or selecting the disabled entry.
    await page.keyboard.press('Tab');
    await expect(page.getByRole('combobox', { name: 'Sichtbarkeit', exact: true })).toBeFocused();
});

test('group request, personal dashboard polling, open roll and history', async ({ page, browser, baseURL }) => {
    test.setTimeout(180000);
    const data = fixture();
    const context = await browser.newContext({ baseURL });
    const player = await context.newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    player.on('pageerror', (e) => errors.push(e.message));
    try {
        await login(page, data.leader);
        await login(player, data.player);
        await player.goto('/dashboard');
        await requestCheck(page, data, { group: true });
        await player.bringToFront();
        const panel = player.getByRole('region', { name: 'Persönliche Rollenspiel-Proben' });
        await expect(panel.getByText('Die Brücke '+data.characters.player.name, { exact: false })).toBeVisible({ timeout: 15000 });
        await panel.getByRole('link').filter({ hasText: 'Die Brücke' }).click();
        await player.getByRole('button', { name: 'Probe würfeln', exact: true }).click();
        await expect(player.getByText('Gesamtergebnis:', { exact: false })).toBeVisible();
        await expect(player.getByRole('button', { name: 'Probe würfeln', exact: true })).toBeHidden();
        await player.reload();
        await expect(player.getByText('Gesamtergebnis:', { exact: false })).toBeVisible();
        const json = await (await player.request.get(player.url(), { headers: { Accept: 'application/json' } })).json();
        expect(json.participants).toHaveLength(1);
        expect(json.participants[0].dice).toHaveLength(2);
        await player.goto('/rpg/proben?tab=history');
        await expect(player.locator('article').filter({ hasText: data.characters.player.name })).toHaveCount(1);
        expect(errors).toEqual([]);
    } finally { await context.close(); }
});

test('hidden results never reach player HTML or JSON and mobile form is accessible', async ({ page, browser, baseURL }) => {
    test.setTimeout(180000);
    const data = fixture();
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page, data.leader);
    await page.goto('/rpg/proben/neu');
    const accessibility = await new AxeBuilder({ page }).include('main').withTags(['wcag2a', 'wcag2aa']).analyze();
    expect(accessibility.violations).toEqual([]);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await requestCheck(page, data, { hidden: true });
    const context = await browser.newContext({ baseURL });
    const player = await context.newPage();
    try {
        await login(player, data.player);
        await openOwnCheck(player, data.characters.player);
        const rollResponse = player.waitForResponse((r) => r.url().endsWith('/wuerfeln') && r.request().method() === 'POST');
        await player.getByRole('button', { name: 'Verdeckte Probe würfeln', exact: true }).click();
        const result = await (await rollResponse).json();
        expect(result).not.toHaveProperty('difficulty');
        expect(result.participants[0]).not.toHaveProperty('dice');
        expect(result.participants[0]).not.toHaveProperty('total');
        expect(result.participants[0]).not.toHaveProperty('modifiers');
        expect(JSON.stringify(result)).not.toContain('SECRET-BRIDGE');
        const html = await (await player.request.get(player.url())).text();
        expect(html).not.toContain('SECRET-BRIDGE');
        await expect(player.getByText('Gesamtergebnis:', { exact: false })).toHaveCount(0);
        await page.goto(player.url());
        await expect(page.getByText('Gesamtergebnis:', { exact: false })).toBeVisible();
        await expect(page.getByText('SECRET-BRIDGE', { exact: false })).toBeVisible();
    } finally { await context.close(); }
});

test('two owners roll an opposed check and only see their own numeric results', async ({ page, browser, baseURL }) => {
    test.setTimeout(180000);
    const data = fixture();
    await login(page, data.leader);
    await requestCheck(page, data, { opposed: true });
    const contexts = await Promise.all([browser.newContext({ baseURL }), browser.newContext({ baseURL })]);
    try {
        const [first, second] = await Promise.all(contexts.map((c) => c.newPage()));
        await login(first, data.player); await login(second, data.other);
        await openOwnCheck(first, data.characters.player);
        await second.goto(first.url());
        await first.getByRole('button', { name: 'Probe würfeln', exact: true }).click();
        await second.getByRole('button', { name: 'Probe würfeln', exact: true }).click();
        const result = await (await first.request.get(first.url(), { headers: { Accept: 'application/json' } })).json();
        expect(['completed', 'awaiting_decision']).toContain(result.status);
        expect(result.participants[0]).toHaveProperty('dice');
        expect(result.participants[1]).not.toHaveProperty('dice');
        expect(result).not.toHaveProperty('comparison_margin');
        await page.goto(first.url());
        await expect(page.getByText('Gesamtergebnis:', { exact: false })).toHaveCount(2);
    } finally { await Promise.all(contexts.map((c) => c.close())); }
});

test('leader resolves a tie and cancels another pending request', async ({ page }) => {
    test.setTimeout(120000);
    const data = fixture('tie');
    await login(page, data.leader);
    await page.goto('/rpg/proben/'+data.check_id);
    await expect(page.getByText('Gleichstand – Entscheidung der Leitung ausstehend', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Probe stornieren / entscheiden' }).click();
    await page.getByLabel('Begründung (bei Stornierung für Spieler sichtbar)', { exact: true }).fill('Beide erreichen die Brücke zugleich');
    await page.getByRole('button', { name: 'Gleichstand entscheiden', exact: true }).click();
    await expect(page.getByText('Unentschieden / anderer Ausgang', { exact: true })).toBeVisible();
    await requestCheck(page, data);
    const card = page.locator('article').filter({ hasText: 'Die Brücke '+data.characters.player.name });
    await card.getByRole('button', { name: 'Probe stornieren / entscheiden' }).click();
    await card.getByLabel('Begründung (bei Stornierung für Spieler sichtbar)', { exact: true }).fill('Die Gruppe nimmt einen anderen Weg');
    await card.getByRole('button', { name: 'Stornierung bestätigen' }).click();
    await expect(card.getByText('Storniert', { exact: true })).toBeVisible();
});
