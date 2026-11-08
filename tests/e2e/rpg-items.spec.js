import { expect as baseExpect, test } from './test-support.js';
import AxeBuilder from '@axe-core/playwright';
import { spawnSync } from 'node:child_process';
import { createPhpProcess } from './utils/php.js';

const expect = baseExpect.configure({ timeout: 30_000 });

function fixture() {
    const command = createPhpProcess(['tests/e2e/create-rpg-progression-fixture.php'], { env: process.env });
    const result = spawnSync(command.command, command.args, { env: process.env, shell: command.shell, encoding: 'utf8', windowsHide: true });
    if (result.error || result.status !== 0) throw new Error(result.stderr || result.error?.message);
    return JSON.parse(result.stdout.trim());
}

test('leader designs, releases and versions equipment while existing stock keeps its values', async ({ page }) => {
    test.setTimeout(360_000);
    const data = fixture(); const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/login', { timeout: 60_000 });
    await page.locator('[name="email"]').fill(data.leader);
    await page.locator('[name="password"]').fill('password');
    await page.locator('button[type=submit]').click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
    await page.goto('/rpg/gegenstaende/neu', { timeout: 60_000 });
    const name = 'Klinge aus Wudan';
    await page.getByLabel('Name', { exact: true }).fill(name);
    await page.getByLabel('Kategorie', { exact: true }).selectOption('melee_weapons');
    await page.getByLabel('Beschreibung und besondere Wirkungen', { exact: true }).fill('Eine ausgewogene Klinge.');
    await page.getByLabel('Vergleichsgegenstand oder fachliche Grundlage', { exact: true }).fill('Schwert, Regelwerk S. 41');
    await page.getByLabel('Bucks-Richtpreis', { exact: true }).fill('90');
    await page.getByRole('button', { name: 'Vorschau prüfen', exact: true }).click();
    await expect(page.getByTestId('item-preview')).toContainText('TW 4');
    await page.getByRole('button', { name: 'Gegenstand speichern', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Version 1 · ' + name, exact: true })).toBeVisible();
    const detail = page.url();
    await page.getByRole('button', { name: 'Für Startausrüstung freigeben', exact: true }).click();
    await expect(page.getByText('Startausrüstung: Freigegeben', { exact: true })).toBeVisible();

    const inventory = '/rpg/charaktere/' + data.character_id + '/ausruestung';
    await page.goto(inventory, { timeout: 60_000 });
    await page.getByLabel('Tatsächlicher Bucks-Bestand', { exact: true }).fill('0');
    const book = async (reason) => {
        await page.getByLabel('Begründung aus der Handlung', { exact: true }).fill(reason);
        await page.getByRole('button', { name: 'Buchung prüfen', exact: true }).click();
        await expect(page.getByTestId('inventory-preview')).toBeVisible();
        await page.getByRole('button', { name: 'Buchung speichern', exact: true }).click();
        await expect(page.getByTestId('inventory-journal')).toContainText(reason);
    };
    await book('Anfangsbestand bestätigt');
    await page.getByRole('button', { name: 'Erhaltene Positionen ergänzen', exact: true }).click();
    const first = await page.getByLabel('Position', { exact: true }).locator('option').filter({ hasText: name + ' (Version 1)' }).getAttribute('value');
    await page.getByLabel('Position', { exact: true }).selectOption(first);
    await book('Klinge in einer Ruine gefunden');
    await expect(page.getByTestId('inventory-stock')).toContainText(name + ' (Version 1)');

    await page.goto(detail, { timeout: 60_000 });
    await page.getByRole('link', { name: 'Neue Version erstellen', exact: true }).click();
    await page.getByLabel('Meisterarbeit (+1 Schaden)', { exact: true }).check();
    await page.getByRole('button', { name: 'Vorschau prüfen', exact: true }).click();
    await expect(page.getByTestId('item-preview')).toContainText('TW 12');
    await page.getByRole('button', { name: 'Gegenstand speichern', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Version 2 · ' + name, exact: true })).toBeVisible();
    await page.goto(inventory, { timeout: 60_000 });
    await expect(page.getByTestId('inventory-stock')).toContainText(name + ' (Version 1)');
    await expect(page.getByTestId('inventory-stock')).not.toContainText(name + ' (Version 2)');
    await page.getByLabel('Vorgang', { exact: true }).selectOption('item_version_change');
    await page.getByLabel('Quellversion', { exact: true }).selectOption(first);
    const second = await page.getByLabel('Zielversion', { exact: true }).locator('option').filter({ hasText: name + ' (Version 2)' }).getAttribute('value');
    await page.getByLabel('Zielversion', { exact: true }).selectOption(second);
    await book('Klinge ausdrücklich auf neue Version umgestellt');
    await expect(page.getByTestId('inventory-stock')).toContainText(name + ' (Version 2)');
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(detail, { timeout: 60_000 });
    const accessibility = await new AxeBuilder({ page }).include('main').analyze();
    expect(accessibility.violations).toEqual([]);
    expect(errors).toEqual([]);
});
