import { expect, test } from './test-support.js';
import AxeBuilder from '@axe-core/playwright';
import { spawnSync } from 'node:child_process';
import { createPhpProcess } from './utils/php.js';

function fixture() {
    const command = createPhpProcess(['tests/e2e/create-rpg-combat-fixture.php'], { env: process.env });
    const result = spawnSync(command.command, command.args, { env: process.env, shell: command.shell, encoding: 'utf8', windowsHide: true });
    if (result.error || result.status !== 0) throw new Error(result.stderr || result.error?.message);
    return JSON.parse(result.stdout.trim());
}

async function login(page, email) {
    await page.goto('/login');
    await page.locator('[name="email"]').fill(email);
    await page.locator('[name="password"]').fill('password');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL(url => !url.pathname.endsWith('/login'));
}

async function challenge(page, npcUrl, character) {
    await page.goto(npcUrl);
    await page.getByRole('link', { name: 'Zum Übungskampf herausfordern' }).click();
    const form = page.locator('form').filter({ has: page.locator('[name="kind"][value="npc_vs_player"]') });
    await form.locator('[name="opponent_id"]').selectOption(String(character));
    await form.locator('[name="distance"]').fill('1');
    await form.getByRole('button', { name: 'Mit NSC verbindlich herausfordern' }).click();
    await page.waitForURL(/\/rpg\/uebungskaempfe\/\d+$/);
    return page.url();
}

test('leader creates NPC; member declines, accepts and reacts; practice leaves originals intact', async ({ page, browser, baseURL }) => {
    test.setTimeout(180000);
    const data = fixture();
    const context = await browser.newContext({ baseURL });
    const member = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    member.on('pageerror', error => errors.push(error.message));
    try {
        await login(page, data.leader);
        await page.goto('/rpg/charaktere');
        await page.getByRole('link', { name: 'NSC aus Regelwerk erstellen', exact: true }).click();
        await page.getByLabel('Optionaler Name').fill('Torwache E2E');
        await page.getByRole('button', { name: 'Vorschau berechnen' }).click();
        await expect(page.getByText('Verbindliche Vorschau', { exact: true })).toBeVisible();
        await page.getByTestId('npc-create').click();
        await page.waitForURL(/\/rpg\/charaktere\/nscs\/\d+$/);
        const npcUrl = page.url();
        const first = await challenge(page, npcUrl, data.characters.other.id);
        await login(member, data.other);
        await member.goto(first);
        await member.getByRole('button', { name: 'Ablehnen', exact: true }).click();
        await expect(member.getByText('Abgelehnt', { exact: false }).first()).toBeVisible();
        const second = await challenge(page, npcUrl, data.characters.other.id);
        await member.goto(second);
        await member.getByRole('button', { name: 'Bedingungen annehmen und starten' }).click();
        await page.goto(second);
        await expect(page.getByRole('region', { name: 'Offene Entscheidungen' })).toContainText('persönlich');
        await page.getByRole('button', { name: 'Entscheidung bestätigen', exact: true }).click();
        await member.getByRole('button', { name: 'Entscheidung bestätigen', exact: true }).click();
        await page.goto(second);
        await page.getByRole('button', { name: 'Jetzt würfeln' }).click();
        await member.goto(second);
        await member.getByRole('button', { name: 'Jetzt würfeln' }).click();
        await page.goto(second);
        await page.getByText('Kampf beenden', { exact: true }).click();
        await page.getByRole('button', { name: 'Aufgeben (Gegner gewinnt)' }).click();
        await expect(page.getByText('Gewonnen:', { exact: false })).toBeVisible();
        await expect(page.getByRole('region', { name: 'Kampfprotokoll' })).toContainText('Initiative');
        const audit = await new AxeBuilder({ page }).include('[data-rpg-combat]').analyze();
        expect(audit.violations).toEqual([]);
        await page.goto(npcUrl);
        await expect(page.getByRole('heading', { name: 'Torwache E2E' })).toBeVisible();
        expect(errors).toEqual([]);
        expect((await member.goto('/rpg/charaktere/nscs/neu')).status()).toBe(403);
    } finally { await context.close(); }
});

test('fixed special character can be created without JavaScript', async ({ browser, baseURL }) => {
    test.setTimeout(120000);
    const data = fixture();
    const context = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
    const page = await context.newPage();
    try {
        await login(page, data.leader);
        await page.goto('/rpg/charaktere/nscs/neu?template_key=maddrax');
        await expect(page.getByLabel('Optionaler Name')).toHaveCount(0);
        await page.getByRole('button', { name: 'Vorschau berechnen' }).click();
        await page.getByTestId('npc-create').click();
        await expect(page.getByRole('heading', { name: 'Matthew Drax', exact: true })).toBeVisible();
        await expect(page.getByText('NSC-Name ändern', { exact: true })).toHaveCount(0);
    } finally { await context.close(); }
});

test('leader distributes exact rank points and selects a published bandit weapon', async ({ page }) => {
    test.setTimeout(120000);
    const data = fixture();
    await login(page, data.leader);
    await page.goto('/rpg/charaktere/nscs/neu');
    await page.getByRole('combobox', { name: 'Vorlage', exact: true }).selectOption('daamure');
    await page.getByRole('button', { name: 'Vorlage auswählen', exact: true }).click();
    await page.waitForURL(url => url.searchParams.get('template_key') === 'daamure');
    await expect(page.getByRole('combobox', { name: 'Vorlage', exact: true })).toHaveValue('daamure');
    // Let the shared navigation's session-backed background requests finish before flashing validation errors.
    await page.waitForLoadState('networkidle');
    await page.getByRole('combobox', { name: 'Rang', exact: true }).selectOption('Lin');
    await page.getByLabel('Begründung der FP-Verteilung').fill('Ausbildung zum Krieger');
    await page.getByRole('button', { name: 'Vorschau berechnen' }).click();
    await expect(page.getByText('Die zusätzlichen 3 FP müssen vollständig verteilt werden.')).toBeVisible();
    await page.getByLabel('Nahkampf', { exact: true }).fill('3');
    await page.getByRole('button', { name: 'Vorschau berechnen' }).click();
    await expect(page.getByText('Verbindliche Vorschau', { exact: true })).toBeVisible();
    await page.getByTestId('npc-create').click();
    await expect(page.getByRole('heading', { name: 'Daa’mure', exact: true })).toBeVisible();
    await expect(page.getByText('Lin Techniker', { exact: true })).toBeVisible();
    await expect(page.getByText('Nahkampf 6', { exact: false })).toBeVisible();

    await page.goto('/rpg/charaktere/nscs/neu');
    await page.getByRole('combobox', { name: 'Vorlage', exact: true }).selectOption('bandit');
    await page.getByRole('button', { name: 'Vorlage auswählen', exact: true }).click();
    await page.waitForURL(url => url.searchParams.get('template_key') === 'bandit');
    await page.getByRole('combobox', { name: 'Vorgegebene Waffe', exact: true }).selectOption('zwille');
    await page.getByRole('button', { name: 'Vorschau berechnen' }).click();
    await page.getByTestId('npc-create').click();
    await expect(page.getByRole('heading', { name: 'Bandit', exact: true })).toBeVisible();
    await expect(page.getByText('Zwille · Fernkampf', { exact: false })).toBeVisible();
    await expect(page.getByText('Schwert · Nahkampf', { exact: false })).toHaveCount(0);
    const audit = await new AxeBuilder({ page }).include('main').analyze();
    expect(audit.violations).toEqual([]);
});
