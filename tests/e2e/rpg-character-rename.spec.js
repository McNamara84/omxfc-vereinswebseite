import { expect, test } from './test-support.js';
import AxeBuilder from '@axe-core/playwright';
import { spawnSync } from 'node:child_process';
import { createPhpProcess } from './utils/php.js';

function fixture(scenario = 'rename') {
    const command = createPhpProcess(['tests/e2e/create-rpg-progression-fixture.php', scenario], { env: process.env });
    const result = spawnSync(command.command, command.args, {
        env: process.env, shell: command.shell, encoding: 'utf8', windowsHide: true,
    });
    if (result.error || result.status !== 0) throw new Error(result.stderr || result.error?.message);
    return JSON.parse(result.stdout.trim());
}

async function openCharacters(page, data) {
    await page.goto('/login');
    await page.locator('input[name="email"]').fill(data.player);
    await page.locator('input[name="password"]').fill('password');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL(url => !url.pathname.endsWith('/login'));
    await expect(page.locator('[data-dashboard-activity-feed]:not([aria-busy="true"])')).toBeAttached();
    await page.goto('/rpg/charaktere');
}

async function openRename(page) {
    const details = page.getByTestId('rpg-character-rename');
    await details.locator('summary').focus();
    await page.keyboard.press('Enter');
    await expect(details).toHaveAttribute('open', '');
    return details;
}

test('rename confirms price, cancellation is free and history records the purchase', async ({ page }) => {
    const data = fixture();
    await openCharacters(page, data);
    let details = await openRename(page);
    await expect(details).toContainText('Verfügbar: 50 Baxx · Danach: 25 Baxx');
    await details.getByLabel('Neuer Charaktername').fill('Verworfener Name');
    await details.getByRole('link', { name: 'Abbrechen' }).click();
    await expect(page.getByTestId('rpg-character-row')).toContainText(data.character_name);
    await expect(page.getByTestId('rpg-character-slot-summary')).toContainText('50');
    details = await openRename(page);
    await details.getByLabel('Neuer Charaktername').fill('Birka aus Wudan');
    const sent = page.waitForRequest(request => request.method() === 'POST' && request.url().endsWith(`/rpg/charaktere/${data.character_id}/name`));
    await details.getByRole('button', { name: 'Für 25 Baxx umbenennen', exact: true }).click();
    const request = await sent;
    await expect(page.getByTestId('rpg-character-success')).toContainText('25 Baxx');
    await expect(page.getByTestId('rpg-character-row')).toContainText('Birka aus Wudan');
    await expect(page.getByTestId('rpg-character-slot-summary')).toContainText('25');

    // Retrying the actual submitted form must not consume the remaining Baxx.
    await page.request.post(request.url(), { form: Object.fromEntries(new URLSearchParams(request.postData())) });
    await page.goto('/rpg/charaktere');
    await expect(page.getByTestId('rpg-character-slot-summary')).toContainText('25');
    await page.getByRole('link', { name: 'Verlauf', exact: true }).click();
    const history = page.getByTestId('rpg-character-rename-history');
    await expect(history.locator('li')).toHaveCount(1);
    await expect(history).toContainText(`${data.character_name} → Birka aus Wudan`);
    await expect(history).toContainText('25 Baxx');
});

for (const [scenario, reason] of [
    ['rename-poor', 'Du benötigst 25 Baxx, hast aber nur 24 verfügbar.'],
    ['rename-pending', 'offener Verbesserungsantrag'],
]) {
    test(`rename explains blocked purchase: ${scenario}`, async ({ page }) => {
        await openCharacters(page, fixture(scenario));
        const details = await openRename(page);
        await expect(details.getByTestId('rpg-character-rename-blocked')).toContainText(reason);
        await expect(details.getByRole('button', { name: 'Für 25 Baxx umbenennen' })).toBeDisabled();
    });
}

test('server validation preserves the name, focuses the field and supports a small viewport', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    const data = fixture();
    await openCharacters(page, data);
    const details = await openRename(page);
    const input = details.getByLabel('Neuer Charaktername');
    // Bypass the client maxlength to exercise the server response and redirect.
    await input.evaluate(element => element.removeAttribute('maxlength'));
    const name = 'Ä'.repeat(256);
    await input.fill(name);
    await details.getByRole('button', { name: 'Für 25 Baxx umbenennen' }).click();
    await expect(page.getByRole('alert').filter({ hasText: 'maximal 255 Zeichen' })).toBeVisible();
    await expect(input).toHaveValue(name);
    await expect(input).toBeFocused();
    await expect(input).toHaveAttribute('aria-invalid', 'true');
    const accessibility = await new AxeBuilder({ page }).include('main').withTags(['wcag2a', 'wcag2aa']).analyze();
    expect(accessibility.violations).toEqual([]);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await input.fill('Äquatoria');
    await input.press('Enter');
    await expect(page.getByTestId('rpg-character-success')).toContainText('Äquatoria');
});
