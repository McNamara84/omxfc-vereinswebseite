import { expect, test } from './test-support.js';
import AxeBuilder from '@axe-core/playwright';
import { spawnSync } from 'node:child_process';
import { createPhpProcess } from './utils/php.js';

function fixture(scenario = '') {
    const command = createPhpProcess(['tests/e2e/create-rpg-progression-fixture.php', scenario], { env: process.env });
    const result = spawnSync(command.command, command.args, {
        env: process.env, shell: command.shell, encoding: 'utf8', windowsHide: true,
    });
    if (result.error || result.status !== 0) throw new Error(result.stderr || result.error?.message);
    return JSON.parse(result.stdout.trim());
}

async function login(page, email) {
    await page.context().clearCookies();
    await page.goto('/login');
    await page.locator('input[name="email"]').fill(email);
    await page.locator('input[name="password"]').fill('password');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
}

test('EP award, private request, leader approval and updated PDF', async ({ page }) => {
    test.setTimeout(150_000);
    const data = fixture();
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await login(page, data.leader);
    await page.goto('/rpg/abenteuer/neu');
    await page.getByLabel('Titel', { exact: true }).fill('Die Ruinen von Wudan');
    await page.getByLabel('Charakter auswählen').selectOption(String(data.character_id));
    await page.getByRole('button', { name: 'Charakter hinzufügen' }).click();
    await page.getByLabel('Abweichende EP (optional)').fill('30');
    await page.getByLabel('Begründung der Abweichung').fill('Vertrauliche besondere Leistung');
    await page.getByRole('button', { name: 'Vergabe prüfen' }).click();
    await expect(page.getByRole('heading', { name: 'Vorschau der Vergabe' })).toBeVisible();
    await page.getByRole('button', { name: 'EP verbindlich vergeben' }).click();
    await page.waitForURL(/\/rpg\/abenteuer\/\d+$/);
    await expect(page.getByText('30 EP (Regelwert:', { exact: false })).toBeVisible();

    await login(page, data.player);
    await page.goto('/dashboard');
    const activity = page.getByTestId('dashboard-activity').filter({ hasText: 'Arkon EP-Test' });
    await expect(activity).toContainText('30 EP');
    await expect(activity).not.toContainText('Vertrauliche besondere Leistung');
    await activity.getByRole('link', { name: 'Charakter verbessern' }).click();
    await expect(page.getByRole('heading', { name: 'Charakter verbessern', exact: true })).toBeVisible();
    await page.getByLabel('Begründung aus dem Abenteuer / erforderliche Details').fill('Bei der Verteidigung der Ruinen trainiert.');
    await page.getByRole('button', { name: 'Verbesserung prüfen' }).click();
    await expect(page.getByRole('heading', { name: 'Vorschau der Verbesserung' })).toBeVisible();
    await page.getByRole('button', { name: 'Verbesserung beantragen' }).click();
    await page.waitForURL(/\/rpg\/verbesserungen\/\d+$/);
    const requestUrl = page.url();
    await expect(page.getByTestId('advancement-status')).toContainText('Zur Prüfung');
    await expect(page.getByRole('button', { name: 'Verbesserung genehmigen' })).toHaveCount(0);

    await login(page, data.leader);
    await page.goto(requestUrl);
    await page.getByRole('button', { name: 'Verbesserung genehmigen' }).click();
    await expect(page.getByTestId('advancement-status')).toContainText('Genehmigt');
    await expect(page.getByTestId('advancement-status')).toContainText('24 EP');
    await login(page, data.player);
    await page.goto('/rpg/charaktere/' + data.character_id + '/historie');
    await expect(page.getByTestId('experience-summary')).toContainText('Verfügbar: 24 EP');
    const pdf = await page.request.get('/rpg/charaktere/' + data.character_id + '/pdf');
    expect(pdf.ok()).toBe(true);
    expect((await pdf.body()).subarray(0, 4).toString()).toBe('%PDF');
    expect(errors).toEqual([]);
});

test('mobile forms have accessible labels and no horizontal overflow', async ({ page }) => {
    test.setTimeout(90_000);
    const data = fixture();
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page, data.leader);
    await page.goto('/rpg/abenteuer/neu');
    await page.getByLabel('Charakter auswählen').selectOption(String(data.character_id));
    await page.getByRole('button', { name: 'Charakter hinzufügen' }).click();
    let results = await new AxeBuilder({ page }).include('main').withTags(['wcag2a', 'wcag2aa']).analyze();
    expect(results.violations).toEqual([]);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await login(page, data.player);
    await page.goto('/rpg/charaktere/' + data.character_id + '/verbessern');
    await page.getByLabel('Art der Änderung').selectOption('advantage');
    await page.getByRole('combobox', { name: 'Eigenschaft', exact: true }).selectOption('Gesteigertes Attribut');
    await expect(page.getByLabel('Ziel des Vorteils')).toBeVisible();
    results = await new AxeBuilder({ page }).include('main').withTags(['wcag2a', 'wcag2aa']).analyze();
    expect(results.violations).toEqual([]);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});

test('keyboard submission, server errors, rejection and withdrawal preserve available EP', async ({ page }) => {
    test.setTimeout(150_000);
    const data = fixture('funded');
    await login(page, data.player);
    const improveUrl = '/rpg/charaktere/' + data.character_id + '/verbessern';
    await page.goto(improveUrl);
    await page.getByLabel('Begründung aus dem Abenteuer / erforderliche Details').fill('Übungen mit der Wache.');
    await page.getByLabel('Steigerung um Punkte').fill('20');
    await page.getByRole('button', { name: 'Verbesserung prüfen' }).press('Enter');
    await expect(page.getByRole('alert')).toContainText('reichen nicht aus');
    await expect(page.getByRole('alert')).toBeFocused();
    await page.getByLabel('Steigerung um Punkte').fill('1');
    await page.getByRole('button', { name: 'Verbesserung prüfen' }).press('Enter');
    await page.getByRole('button', { name: 'Verbesserung beantragen' }).press('Enter');
    await page.waitForURL(/\/rpg\/verbesserungen\/\d+$/);
    const requestUrl = page.url();
    await login(page, data.leader);
    await page.goto(requestUrl);
    await page.getByLabel('Begründung der Ablehnung').fill('Bitte die Übung genauer beschreiben.');
    await page.getByRole('button', { name: 'Antrag ablehnen' }).click();
    await expect(page.getByTestId('advancement-status')).toContainText('Abgelehnt');
    await expect(page.getByTestId('advancement-status')).toContainText('60 EP');
    await login(page, data.player);
    await page.goto(improveUrl);
    await page.getByLabel('Begründung aus dem Abenteuer / erforderliche Details').fill('Neue Übung mit der Wache.');
    await page.getByRole('button', { name: 'Verbesserung prüfen' }).click();
    await page.getByRole('button', { name: 'Verbesserung beantragen' }).click();
    await page.waitForURL(/\/rpg\/verbesserungen\/\d+$/);
    await page.getByRole('button', { name: 'Antrag zurückziehen' }).click();
    await expect(page.getByTestId('advancement-status')).toContainText('Zurückgezogen');
    await expect(page.getByTestId('advancement-status')).toContainText('60 EP');
});
