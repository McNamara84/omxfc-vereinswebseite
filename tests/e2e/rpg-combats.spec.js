import { expect, test } from './test-support.js';
import AxeBuilder from '@axe-core/playwright';
import { spawnSync } from 'node:child_process';
import { createPhpProcess } from './utils/php.js';

function fixture(...args) {
    const cmd = createPhpProcess(['tests/e2e/create-rpg-combat-fixture.php', ...args], { env: process.env });
    const result = spawnSync(cmd.command, cmd.args, { env: process.env, shell: cmd.shell, encoding: 'utf8', windowsHide: true });
    if (result.error || result.status !== 0) throw new Error(result.stderr || result.error?.message);
    return JSON.parse(result.stdout.trim());
}
async function login(page, email) {
    await page.goto('/login');
    await page.locator('input[name="email"]').fill(email);
    await page.locator('input[name="password"]').fill('password');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL(url => !url.pathname.endsWith('/login'));
    await expect(page.locator('[data-dashboard-activity-feed]:not([aria-busy="true"])')).toBeAttached();
}
async function challenge(page, data) {
    await page.goto('/rpg/uebungskaempfe/neu');
    await page.getByRole('combobox', { name: 'Dein Charakter', exact: true }).selectOption(String(data.characters.player.id));
    await page.getByRole('combobox', { name: 'Herausgeforderter Charakter', exact: true }).selectOption(String(data.characters.other.id));
    await page.getByLabel('Startentfernung in Metern').fill('1');
    await page.getByRole('button', { name: 'Verbindlich herausfordern' }).click();
    await page.waitForURL(/\/rpg\/uebungskaempfe\/\d+$/);
    return page.url();
}

test('two members challenge, accept, roll, react, and publish milestones', async ({ page, browser, baseURL }) => {
    test.setTimeout(240000);
    const data = fixture();
    const otherContext = await browser.newContext({ baseURL });
    const other = await otherContext.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    other.on('pageerror', error => errors.push(error.message));
    try {
        await login(page, data.player);
        const url = await challenge(page, data);
        await login(other, data.other);
        await other.goto('/dashboard');
        await expect(other.getByRole('region', { name: 'Persönliche Übungskämpfe' })).toContainText(data.characters.player.name);
        await other.goto(url);
        await other.getByRole('button', { name: 'Bedingungen annehmen und starten' }).click();
        await page.goto(url);
        await page.getByRole('button', { name: 'Entscheidung bestätigen', exact: true }).click();
        await other.getByRole('button', { name: 'Entscheidung bestätigen', exact: true }).click();
        await page.goto(url);
        await page.getByRole('button', { name: 'Jetzt würfeln' }).click();
        await page.evaluate(() => { window.combatPollingDocument = true; });
        await other.goto(url);
        await other.getByRole('button', { name: 'Jetzt würfeln' }).click();
        await page.bringToFront();
        const action = page.getByRole('combobox', { name: 'Handlung', exact: true });
        await expect(action).toBeVisible({ timeout: 20000 });
        await expect(page.locator('[data-combat-notice]')).toHaveText('Kampfstand aktualisiert.');
        expect(await page.evaluate(() => window.combatPollingDocument)).toBe(true);
        const decisionForm = page.locator('form').filter({ has: action });
        await expect(decisionForm.locator('[name="power"]')).toBeHidden();
        await expect(decisionForm.locator('[name="power"]')).toBeDisabled();
        await expect(decisionForm.locator('[name="description"]')).toBeDisabled();
        await action.selectOption('wait');
        await expect(decisionForm.locator('[name="weapon"]')).toBeHidden();
        await expect(decisionForm.locator('[name="weapon"]')).toBeDisabled();
        expect(await decisionForm.evaluate(form => [...new FormData(form).keys()].filter(key => key !== '_token').sort())).toEqual(['kind', 'move']);
        await action.selectOption('attack');
        await expect(decisionForm.locator('[name="weapon"]')).toBeVisible();
        await expect(decisionForm.locator('[name="weapon"]')).toBeEnabled();
        expect(await decisionForm.evaluate(form => [...new FormData(form).keys()].filter(key => key !== '_token').sort())).toEqual(['aim', 'fire', 'kind', 'mode', 'move', 'weapon']);
        await page.getByRole('button', { name: 'Entscheidung bestätigen', exact: true }).click();
        await other.goto(url);
        await other.getByRole('button', { name: 'Entscheidung bestätigen', exact: true }).click();
        await page.goto(url);
        await page.getByRole('button', { name: 'Jetzt würfeln' }).click();
        await expect(page.getByRole('region', { name: 'Kampfprotokoll' })).toContainText('Schadenswurf');
        await other.goto(url);
        const terminate = other.getByText('Kampf beenden', { exact: true });
        if (await terminate.count()) {
            await terminate.click();
            await other.getByRole('button', { name: 'Aufgeben (Gegner gewinnt)' }).click();
        }
        await expect(other.getByText('Gewonnen:', { exact: false })).toBeVisible();
        const audit = await new AxeBuilder({ page: other }).include('[data-rpg-combat]').analyze();
        expect(audit.violations).toEqual([]);
        expect(errors).toEqual([]);
    } finally { await otherContext.close(); }
});

test('overdue preparations advance and a stranger cannot read combat data', async ({ page, browser, baseURL }) => {
    test.setTimeout(180000);
    const data = fixture();
    await login(page, data.player);
    const url = await challenge(page, data);
    const context = await browser.newContext({ baseURL });
    const other = await context.newPage();
    const outsiderContext = await browser.newContext({ baseURL });
    const outsider = await outsiderContext.newPage();
    try {
        await login(other, data.other);
        await other.goto(url);
        await other.getByRole('button', { name: 'Bedingungen annehmen und starten' }).click();
        const result = fixture('expire', url.split('/').pop());
        expect(result.automatic).toBe(2);
        await other.goto(url);
        await expect(other.getByRole('button', { name: 'Jetzt würfeln' })).toBeVisible();
        await login(outsider, data.outsider);
        const response = await outsider.goto(url);
        expect(response.status()).toBe(403);
        await expect(outsider.getByText(data.characters.player.name)).toHaveCount(0);
    } finally { await context.close(); await outsiderContext.close(); }
});

test('psychic decisions work on mobile and only the replacement leader can adjudicate', async ({ page, browser, baseURL }) => {
    test.setTimeout(240000);
    const data = fixture('psychic');
    const url = `/rpg/uebungskaempfe/${data.combat}`;
    const leaderContext = await browser.newContext({ baseURL });
    const leader = await leaderContext.newPage();
    const replacementContext = await browser.newContext({ baseURL });
    const replacement = await replacementContext.newPage();
    const otherContext = await browser.newContext({ baseURL });
    const other = await otherContext.newPage();
    try {
        await page.setViewportSize({ width: 390, height: 844 });
        await login(page, data.player);
        await page.goto(url);
        await page.getByRole('combobox', { name: 'Handlung', exact: true }).selectOption('psychic');
        await page.getByRole('spinbutton', { name: 'Dauer [D]', exact: true }).fill('1');
        await page.getByRole('button', { name: 'Entscheidung bestätigen', exact: true }).click();
        await expect(page.getByRole('region', { name: 'Offene Entscheidungen' })).toContainText('AG-Leitung');
        await login(leader, data.leader);
        await leader.goto(url);
        await expect(leader.getByRole('textbox', { name: 'Begründung' })).toBeVisible();
        fixture('replace-leader', String(data.combat), data.replacement);
        await leader.reload();
        await expect(leader.getByRole('textbox', { name: 'Begründung' })).toHaveCount(0);
        await login(replacement, data.replacement);
        await replacement.goto('/dashboard');
        await expect(replacement.getByRole('region', { name: 'Persönliche Übungskämpfe' })).toContainText('Regelfrage entscheiden');
        await replacement.goto(url);
        await replacement.getByRole('textbox', { name: 'Begründung' }).fill('Die veröffentlichten Standardauslegungen gelten für diesen Übungskampf.');
        await replacement.getByRole('button', { name: 'Entscheidung bestätigen', exact: true }).click();
        await login(other, data.other);
        await other.goto(url);
        await expect(other.getByRole('region', { name: 'Offene Entscheidungen' })).toContainText('Psychisch widerstehen');
        await other.getByRole('button', { name: 'Jetzt würfeln' }).click();
        await expect(other.getByRole('region', { name: 'Kampfprotokoll' })).toContainText('Pyrokinese wirkt.');
        await page.goto(url);
        const audit = await new AxeBuilder({ page }).include('[data-rpg-combat]').analyze();
        expect(audit.violations).toEqual([]);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    } finally { await leaderContext.close(); await replacementContext.close(); await otherContext.close(); }
});

test('simultaneous declarations stay hidden until both members have chosen', async ({ page, browser, baseURL }) => {
    test.setTimeout(240000);
    const data = fixture('simultaneous');
    const url = `/rpg/uebungskaempfe/${data.combat}`;
    const context = await browser.newContext({ baseURL });
    const other = await context.newPage();
    const leaderContext = await browser.newContext({ baseURL });
    const leader = await leaderContext.newPage();
    try {
        await login(leader, data.leader);
        await leader.goto(url);
        await leader.getByRole('textbox', { name: 'Begründung' }).fill('Die gleichzeitigen Handlungen gelten für diese Runde.');
        await leader.getByRole('button', { name: 'Entscheidung bestätigen', exact: true }).click();
        await login(page, data.player);
        await page.goto(url);
        await page.getByRole('combobox', { name: 'Handlung', exact: true }).selectOption('wait');
        await page.getByRole('spinbutton', { name: 'Bewegung in Zentimetern', exact: false }).fill('-100');
        await page.getByRole('button', { name: 'Entscheidung bestätigen', exact: true }).click();
        await login(other, data.other);
        await other.goto(url);
        await expect(other.getByText('Entfernung: 1 m', { exact: true })).toBeVisible();
        await other.getByRole('combobox', { name: 'Handlung', exact: true }).selectOption('wait');
        await other.getByRole('button', { name: 'Entscheidung bestätigen', exact: true }).click();
        await expect(other.getByText('Entfernung: 2 m', { exact: true })).toBeVisible();
        await expect(other.getByRole('button', { name: 'Jetzt würfeln' })).toBeVisible();
    } finally { await context.close(); await leaderContext.close(); }
});
