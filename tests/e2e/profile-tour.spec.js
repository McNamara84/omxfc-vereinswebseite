import AxeBuilder from '@axe-core/playwright';
import { spawnSync } from 'node:child_process';
import { test, expect, clickAndWaitForLivewireUpdate } from './test-support.js';
import { createPhpProcess } from './utils/php.js';

function fixture(...args) {
    const command = createPhpProcess(['tests/e2e/create-profile-tour-fixture.php', ...args], { env: process.env });
    const result = spawnSync(command.command, command.args, { env: process.env, shell: command.shell, encoding: 'utf8', windowsHide: true });
    if (result.error || result.status !== 0) throw new Error(result.stderr || result.error?.message || result.stdout);
    return JSON.parse(result.stdout.trim());
}

async function login(page, email) {
    await page.goto('/login');
    await page.locator('[name=email]').fill(email);
    await page.locator('[name=password]').fill('password');
    await page.locator('[name=password]').press('Enter');
    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.locator('#tour-runner-start')).toBeVisible();
    await expect(page.locator('#tour-runner-reward')).toContainText('Einmalig 5 Baxx');
}

async function advanceTo(page, title) {
    for (let count = 0; count < 25; count++) {
        if ((await page.locator('#tour-runner-title').textContent()) === title) return;
        const previous = await page.locator('#tour-runner-title').textContent();
        await page.locator('#tour-runner-next').click();
        await expect(page.locator('#tour-runner-title')).not.toHaveText(previous);
    }
    throw new Error(`Tour step not reached: ${title}`);
}

for (const mobile of [false, true]) {
    test(`profile tour guides real saved input and rewards once (${mobile ? 'mobile' : 'desktop'})`, async ({ page }) => {
        test.setTimeout(120_000);
        if (mobile) await page.setViewportSize({ width: 390, height: 844 });
        const member = fixture('empty');
        await login(page, member.email);
        expect(fixture('inspect', String(member.userId)).points).toBe(0);
        await page.locator('#tour-runner-start').click();
        await expect(page).toHaveURL(/\/user\/profile$/);
        await advanceTo(page, 'Einstiegsroman');
        for (const [field, value] of Object.entries(member.values)) {
            // Use the current tour field or scroll the actual input into view.
            const input = page.locator(`#${field}`);
            await input.scrollIntoViewIfNeeded();
            if (field === 'lieblingsmutation') await input.fill(value);
            else await input.selectOption(value);
        }
        await advanceTo(page, 'Angaben speichern');
        await clickAndWaitForLivewireUpdate(page, page.locator('[data-tour-profile-key="profile-series-save"]'));
        await expect(page.locator('[data-profile-series-completion]')).toContainText('11 von 11');
        await expect(page.locator('#tour-runner-reward')).toContainText('11 von 11');
        await advanceTo(page, 'Profilpflege abschließen');
        const result = await new AxeBuilder({ page }).include('#tour-runner-panel').include('[data-profile-series-form]').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
        expect(result.violations).toEqual([]);
        await page.locator('#tour-runner-complete').click();
        await expect(page.locator('#tour-runner-panel')).toBeHidden();
        expect(fixture('inspect', String(member.userId))).toMatchObject({ points: 5, grants: 1, completed: 'completed' });
        await page.reload();
        const card = page.locator('article').filter({ has: page.getByRole('heading', { name: 'Profil pflegen', exact: true }) });
        await card.getByRole('button', { name: 'Tour erneut starten' }).click();
        await expect(page.locator('#tour-runner-start')).toBeVisible();
        await expect(page.locator('#tour-runner-reward')).toContainText('bereits erhalten');
        await page.locator('#tour-runner-start').click();
        await advanceTo(page, 'Profilpflege abschließen');
        await page.locator('#tour-runner-complete').click();
        await expect(page.locator('#tour-runner-panel')).toBeHidden();
        expect(fixture('inspect', String(member.userId)).points).toBe(5);
    });
}

test('incomplete tour remains open and Later can be resumed', async ({ page }) => {
    test.setTimeout(120_000);
    const member = fixture('empty');
    await login(page, member.email);
    await page.locator('#tour-runner-skip').click();
    await expect(page.locator('#tour-runner-panel')).toBeHidden();
    await page.goto('/user/profile');
    const card = page.locator('article').filter({ has: page.getByRole('heading', { name: 'Profil pflegen', exact: true }) });
    await card.getByRole('button', { name: 'Tour fortsetzen' }).click();
    await advanceTo(page, 'Profilpflege abschließen');
    await page.locator('#tour-runner-complete').click();
    await expect(page.locator('#tour-runner-error')).toContainText('Lieblingsmutation');
    await page.locator('[data-tour-missing-field="lieblingsmutation"]').click();
    await expect(page.locator('#lieblingsmutation')).toBeFocused();
    expect(fixture('inspect', String(member.userId))).toMatchObject({ points: 0, grants: 0, completed: 'in_progress' });
});

test('an already complete profile with old tour completion earns the new bonus', async ({ page }) => {
    test.setTimeout(90_000);
    const member = fixture('complete');
    await login(page, member.email);
    await page.locator('#tour-runner-start').click();
    await expect(page).toHaveURL(/\/user\/profile$/);
    await advanceTo(page, 'Profilpflege abschließen');
    await page.locator('#tour-runner-complete').click();
    await expect(page.locator('#tour-runner-panel')).toBeHidden();
    expect(fixture('inspect', String(member.userId))).toMatchObject({ points: 5, grants: 1 });
});
