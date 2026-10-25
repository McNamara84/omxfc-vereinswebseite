import AxeBuilder from '@axe-core/playwright';
import { spawnSync } from 'node:child_process';
import { test, expect, clickAndWaitForLivewireUpdate } from './test-support.js';
import { createPhpProcess } from './utils/php.js';

test.afterEach(async ({ page }, testInfo) => {
    if (testInfo.status !== testInfo.expectedStatus) {
        const logs = await page.evaluate(() => window.__OMXFC_TOUR_DEBUG_LOGS__ ?? []).catch(() => []);
        await testInfo.attach('tour-debug', { body: JSON.stringify(logs, null, 2), contentType: 'application/json' });
    }
});

function fixture(...args) {
    const command = createPhpProcess(['tests/e2e/create-profile-tour-fixture.php', ...args], { env: process.env });
    const result = spawnSync(command.command, command.args, { env: process.env, shell: command.shell, encoding: 'utf8', windowsHide: true });
    if (result.error || result.status !== 0) throw new Error(result.stderr || result.error?.message || result.stdout);
    try {
        return JSON.parse(result.stdout.trim());
    } catch (error) {
        throw new Error(`Profile tour fixture returned invalid JSON:\n${result.stdout.trim()}`, { cause: error });
    }
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
        await clickTourAction(page, 'next', 'schritt');
        await expect(page.locator('#tour-runner-title')).not.toHaveText(previous);
    }
    throw new Error(`Tour step not reached: ${title}`);
}

async function clickTourAction(page, action, endpoint, status = 200) {
    const button = page.locator(`#tour-runner-${action}`);
    await expect(button).toBeEnabled({ timeout: 15_000 });
    const [response] = await Promise.all([
        page.waitForResponse(response => response.request().method() === 'POST'
            && new URL(response.url()).pathname.endsWith(`/${endpoint}`)),
        button.click(),
    ]);
    await response.finished();
    expect(response.status()).toBe(status);
}

async function submitTourForm(page, button) {
    // The form redirects to the same URL. An outstanding load event from the old
    // document is not sufficient; observe the new document before its tour fetch.
    const refreshedTour = page.waitForNavigation({ waitUntil: 'commit' })
        .then(() => page.waitForResponse(response => new URL(response.url()).pathname === '/touren/aktuell'))
        .then(async response => {
            expect(response.ok()).toBe(true);
            return response.json();
        });
    const [payload] = await Promise.all([
        refreshedTour,
        button.click(),
    ]);
    expect(payload.tour.key).toBe('profilpflege');
    // Hydrating the full catalog and Livewire's second initialization can finish
    // after the first tour response, especially in Firefox.
    await expect(page.locator('#tour-runner-panel')).toBeVisible({ timeout: 15_000 });
}

async function dismissMenuTour(page) {
    // The fixture also has a pending menu tour. Once profile care is completed
    // or postponed, its backdrop must be dismissed before using profile forms.
    const panel = page.locator('#tour-runner-panel');
    await expect(panel).toBeVisible({ timeout: 15_000 });
    await expect(panel).toHaveAttribute('data-profile-tour', 'false');
    await clickTourAction(page, 'skip', 'abbrechen');
    await expect(panel).toBeHidden();
}

for (const mobile of [false, true]) {
    test(`profile tour guides real saved input and rewards once (${mobile ? 'mobile' : 'desktop'})`, async ({ page }) => {
        test.setTimeout(120_000);
        if (mobile) await page.setViewportSize({ width: 390, height: 844 });
        const member = fixture('empty');
        await login(page, member.email);
        expect(fixture('inspect', String(member.userId)).points).toBe(0);
        await clickTourAction(page, 'start', 'starten');
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
        await expect(page.locator('#tour-runner-complete')).toBeEnabled();
        const result = await new AxeBuilder({ page }).include('#tour-runner-panel').include('[data-profile-series-form]').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
        expect(result.violations).toEqual([]);
        await clickTourAction(page, 'complete', 'abschliessen');
        await expect(page.locator('#tour-runner-panel')).toBeHidden();
        expect(fixture('inspect', String(member.userId))).toMatchObject({ points: 5, grants: 1, completed: 'completed' });
        await page.reload();
        await dismissMenuTour(page);
        const card = page.locator('article').filter({ has: page.getByRole('heading', { name: 'Profil pflegen', exact: true }) });
        await submitTourForm(page, card.getByRole('button', { name: 'Tour erneut starten' }));
        await expect(page.locator('#tour-runner-start')).toBeVisible();
        await expect(page.locator('#tour-runner-reward')).toContainText('bereits erhalten');
        await clickTourAction(page, 'start', 'starten');
        await advanceTo(page, 'Profilpflege abschließen');
        await clickTourAction(page, 'complete', 'abschliessen');
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
    await dismissMenuTour(page);
    const card = page.locator('article').filter({ has: page.getByRole('heading', { name: 'Profil pflegen', exact: true }) });
    await submitTourForm(page, card.getByRole('button', { name: 'Tour fortsetzen' }));
    await advanceTo(page, 'Profilpflege abschließen');
    await clickTourAction(page, 'complete', 'abschliessen', 422);
    await expect(page.locator('#tour-runner-error')).toContainText('Lieblingsmutation');
    await page.locator('[data-tour-missing-field="lieblingsmutation"]').click();
    await expect(page.locator('#lieblingsmutation')).toBeFocused();
    expect(fixture('inspect', String(member.userId))).toMatchObject({ points: 0, grants: 0, completed: 'in_progress' });
});

test('an already complete profile with old tour completion earns the new bonus', async ({ page }) => {
    test.setTimeout(90_000);
    const member = fixture('complete');
    await login(page, member.email);
    await clickTourAction(page, 'start', 'starten');
    await expect(page).toHaveURL(/\/user\/profile$/);
    await advanceTo(page, 'Profilpflege abschließen');
    await clickTourAction(page, 'complete', 'abschliessen');
    await expect(page.locator('#tour-runner-panel')).toBeHidden();
    expect(fixture('inspect', String(member.userId))).toMatchObject({ points: 5, grants: 1 });
});
