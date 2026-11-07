import { expect, test as base } from './test-support.js';
import AxeBuilder from '@axe-core/playwright';
import { spawnSync } from 'node:child_process';
import { createPhpProcess } from './utils/php.js';

const test = base.extend({
    playerPage: async ({ browser, baseURL }, use) => {
        const context = await browser.newContext({ baseURL });
        try { await use(await context.newPage()); } finally { await context.close(); }
    },
});

function fixture(scenario = '') {
    const command = createPhpProcess(['tests/e2e/create-rpg-progression-fixture.php', scenario], { env: process.env });
    const result = spawnSync(command.command, command.args, { env: process.env, shell: command.shell, encoding: 'utf8', windowsHide: true });
    if (result.error || result.status !== 0) throw new Error(result.stderr || result.error?.message);
    try { return JSON.parse(result.stdout.trim()); }
    catch { throw new Error('RPG-Testdaten konnten nicht erstellt werden: ' + result.stdout); }
}

async function login(page, email) {
    await page.goto('/login');
    await page.locator('input[name="email"]').fill(email);
    await page.locator('input[name="password"]').fill('password');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
    await expect(page.locator('[data-dashboard-activity-feed]:not([aria-busy="true"])')).toBeAttached();
}

async function save(page, reason) {
    await page.getByLabel('Begründung aus der Handlung', { exact: true }).fill(reason);
    await page.getByRole('button', { name: 'Buchung prüfen', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Buchung speichern', exact: true })).toBeVisible();
    await Promise.all([page.waitForEvent('load'), page.getByRole('button', { name: 'Buchung speichern', exact: true }).click()]);
    await expect(page.getByTestId('inventory-manager').getByLabel('Vorgang', { exact: true })).toBeVisible();
}

async function position(page, heading, id, quantity) {
    const section = page.locator('section').filter({ has: page.getByRole('heading', { name: heading, exact: true }) });
    await section.getByRole('button', { name: heading + ' ergänzen', exact: true }).click();
    await section.getByLabel('Position', { exact: true }).last().selectOption(id);
    await section.getByLabel('Menge', { exact: true }).last().fill(String(quantity));
}

test('leader manages persistent ammunition and trade while owner reads the updated PDF', async ({ page, playerPage }) => {
    test.setTimeout(180_000);
    const data = fixture(); const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const url = '/rpg/charaktere/' + data.character_id + '/ausruestung';
    await login(page, data.leader); await page.goto(url);
    await expect(page.getByLabel('Vorgang', { exact: true })).toHaveValue('initialize');
    await page.getByLabel('Tatsächlicher Bucks-Bestand', { exact: true }).fill('100');
    await save(page, 'Anfangsbestand bestätigt');
    await expect(page.getByTestId('inventory-stock')).toContainText('100 Bucks');
    await position(page, 'Erhaltene Positionen', 'item:revolver', 1);
    await position(page, 'Erhaltene Positionen', 'ammo:revolver', 24);
    await save(page, 'Revolver und Munition in der Ruine gefunden');
    await page.getByLabel('Vorgang', { exact: true }).selectOption('loss');
    await position(page, 'Abgegebene Positionen', 'ammo:revolver', 7);
    await save(page, 'Sieben Schuss im Abenteuer verbraucht');
    await expect(page.getByTestId('inventory-stock')).toContainText('17 × Revolver – Munition');
    await page.getByLabel('Vorgang', { exact: true }).selectOption('purchase');
    await position(page, 'Erhaltene Positionen', 'item:bogen', 1);
    await page.getByLabel('Vereinbarter Gesamtpreis in Bucks', { exact: true }).fill('30');
    await page.getByLabel('Bezeichnung der externen Gegenpartei', { exact: true }).fill('Händler in Wudan');
    await save(page, 'Bogen für dreißig Bucks gekauft');
    await expect(page.getByTestId('inventory-stock')).toContainText('70 Bucks');
    await expect(page.getByTestId('inventory-stock')).toContainText('17 × Revolver – Munition');
    await login(playerPage, data.player); await playerPage.goto(url);
    await expect(playerPage.getByTestId('inventory-manager')).toHaveCount(0);
    await expect(playerPage.getByTestId('inventory-stock')).toContainText('70 Bucks');
    await expect(playerPage.getByTestId('inventory-journal')).toContainText('Bogen für dreißig Bucks gekauft');
    const pdf = await playerPage.request.get('/rpg/charaktere/' + data.character_id + '/pdf');
    expect(pdf.ok()).toBe(true); expect((await pdf.body()).subarray(0, 4).toString()).toBe('%PDF');
    expect(errors).toEqual([]);
});

test('reversal shows authoritative differences and mobile forms remain accessible', async ({ page }) => {
    test.setTimeout(180_000);
    const data = fixture(); await page.setViewportSize({ width: 390, height: 844 });
    await login(page, data.leader); await page.goto('/rpg/charaktere/' + data.character_id + '/ausruestung');
    await page.getByLabel('Tatsächlicher Bucks-Bestand', { exact: true }).fill('0'); await save(page, 'Kein Spielgeld vorhanden');
    await position(page, 'Erhaltene Positionen', 'item:seil', 2); await save(page, 'Zwei Seile gefunden');
    await page.getByTestId('inventory-journal').getByRole('button', { name: /zurückbuchen$/ }).first().click();
    await save(page, 'Fund irrtümlich erfasst');
    await expect(page.getByTestId('inventory-stock')).not.toContainText('× Seil');
    await expect(page.getByTestId('inventory-journal')).toContainText('Rückbuchung');
    const results = await new AxeBuilder({ page }).include('main').analyze();
    expect(results.violations).toEqual([]);
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
});

test('catalogue search and character barter show both sides before booking', async ({ page }) => {
    test.setTimeout(180_000);
    const data = fixture('inventory-trade'); await login(page, data.leader);
    await page.goto('/rpg/charaktere/' + data.character_id + '/ausruestung');
    await page.getByLabel('Vorgang', { exact: true }).selectOption('swap');
    await page.getByLabel('Katalog durchsuchen', { exact: true }).fill('Bogen');
    await position(page, 'Erhaltene Positionen', 'item:bogen', 1);
    await page.getByLabel('Katalog durchsuchen', { exact: true }).fill('Seil');
    await position(page, 'Abgegebene Positionen', 'item:seil', 1);
    await page.getByLabel('Gegenpartei', { exact: true }).selectOption(String(data.other_character_id));
    await page.getByLabel('Bucks-Betrag', { exact: true }).fill('10');
    await page.getByLabel('Richtung des Geldes', { exact: true }).selectOption('expense');
    await page.getByLabel('Begründung aus der Handlung', { exact: true }).fill('Seil und zehn Bucks gegen einen Bogen');
    await page.getByRole('button', { name: 'Buchung prüfen', exact: true }).click();
    await expect(page.getByTestId('inventory-preview')).toContainText('Tauschpartner');
    await expect(page.getByTestId('inventory-preview')).toContainText('90');
    await expect(page.getByTestId('inventory-preview')).toContainText('110');
    await Promise.all([page.waitForEvent('load'), page.getByRole('button', { name: 'Buchung speichern', exact: true }).click()]);
    await expect(page.getByTestId('inventory-stock')).toContainText('90 Bucks');
    await page.goto('/rpg/charaktere/' + data.other_character_id + '/ausruestung');
    await expect(page.getByTestId('inventory-stock')).toContainText('110 Bucks');
    await expect(page.getByTestId('inventory-stock')).toContainText('1 × Seil');
});
