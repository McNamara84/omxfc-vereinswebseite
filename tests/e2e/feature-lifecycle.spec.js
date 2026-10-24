import { expect, test } from './test-support.js';
import { spawnSync } from 'node:child_process';
import { createPhpProcess } from './utils/php.js';

const heavy = /\/(?:leaflet|chart-|charts-|karte-|maddraxiversum-|char-editor-|rpg-checks-|rpg-progression-|rpg-combats-)/;
function fixture() {
    const command = createPhpProcess(['tests/e2e/create-feature-lifecycle-fixture.php'], { env: process.env });
    const result = spawnSync(command.command, command.args, { env: process.env, shell: command.shell, encoding: 'utf8', windowsHide: true });
    if (result.status !== 0) throw new Error(result.stderr || result.error?.message);
    return JSON.parse(result.stdout.trim());
}
async function login(page, email = fixture().email) {
    await page.goto('/login');
    await page.locator('[name=email]').fill(email);
    await page.locator('[name=password]').fill('password');
    await page.locator('button[type=submit]').click();
    await page.waitForURL(url => !url.pathname.endsWith('/login'));
    await expect(page.locator('[data-dashboard-activity-feed]:not([aria-busy="true"])')).toBeAttached();
}
async function navigate(page, path) {
    await page.evaluate(path => window.Livewire.navigate(path), path);
    await page.waitForURL(url => url.pathname === path);
}
const ready = (page, selector) => expect(page.locator(selector).first()).toHaveAttribute('data-feature-state', 'ready');
test.beforeEach(async ({ page }) => {
    page.on('dialog', dialog => dialog.accept());
    await page.route(/tile\.openstreetmap\.org|mapdraxv2\.maddraxikon\.com/, route => route.fulfill({ status: 200, contentType: 'image/png', body: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', 'base64') }));
    await page.route('**/maddraxikon-staedte', route => route.fulfill({ json: { query: { results: {} } } }));
});

test('ordinary pages and locked feature pages exclude heavy modules', async ({ page }) => {
    const scripts = [];
    page.on('request', request => { if (request.resourceType() === 'script') scripts.push(request.url()); });
    await page.goto('/');
    await page.goto('/login');
    expect(scripts.filter(url => heavy.test(url))).toEqual([]);
    await login(page, 'playwright-map-locked@example.com');
    await page.goto('/mitglieder/karte');
    await expect(page.getByText('Mitgliederkarte freischalten')).toBeVisible();
    expect(await page.locator('[data-member-map-enabled]').count()).toBe(0);
    expect(scripts.filter(url => /leaflet|\/karte-/.test(url))).toEqual([]);
});

test('maps, charts and RPG survive Livewire navigation and browser history', async ({ page }) => {
    test.setTimeout(120_000);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await login(page);
    await page.goto('/mitglieder');
    for (let visit = 0; visit < 2; visit++) {
        await navigate(page, '/mitglieder/karte');
        await ready(page, '[data-member-map-enabled]');
        await expect(page.locator('.leaflet-container')).toHaveCount(1);
        await expect(page.locator('.leaflet-control-container')).toHaveCount(1);
        await navigate(page, '/maddraxiversum');
        await ready(page, '[data-maddraxiversum-map]');
        await expect(page.locator('.leaflet-container')).toHaveCount(1);
        await navigate(page, '/admin/statistiken');
        await ready(page, '#admin-charts-config');
        await expect(page.locator('.leaflet-container')).toHaveCount(0);
        await navigate(page, '/statistiken');
        await ready(page, 'canvas[data-statistik-chart]');
        await navigate(page, '/rpg/char-editor');
        await ready(page, '[data-feature="char-editor"]');
        await page.getByLabel('Spielername').fill('Ungespeichert');
        await page.evaluate(() => document.dispatchEvent(new Event('features:retry')));
        await expect(page.getByLabel('Spielername')).toHaveValue('Ungespeichert');
        await navigate(page, '/mitglieder');
    }
    await page.goBack();
    await ready(page, '[data-feature="char-editor"]');
    await page.goForward();
    await expect(page.locator('[data-feature="char-editor"]')).toHaveCount(0);
    expect(errors).toEqual([]);
});

test('navigation cancels a delayed RPG import and the next visit initializes once', async ({ page }) => {
    test.setTimeout(60_000);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await login(page);
    let release;
    const gate = new Promise(resolve => { release = resolve; });
    let requested;
    const request = new Promise(resolve => { requested = resolve; });
    await page.route('**/char-editor-*.js', async route => { requested(); await gate; await route.continue(); });
    await navigate(page, '/rpg/char-editor');
    await request;
    await expect(page.locator('[data-feature="char-editor"]')).toHaveAttribute('data-feature-state', 'loading');
    await navigate(page, '/mitglieder');
    release();
    await navigate(page, '/rpg/char-editor');
    await ready(page, '[data-feature="char-editor"]');
    await page.getByLabel('Spielername').fill('Nachgeladen');
    await expect(page.getByLabel('Spielername')).toHaveValue('Nachgeladen');
    expect(errors).toEqual([]);
});

test('failed feature imports offer a working retry', async ({ page }) => {
    test.setTimeout(60_000);
    await login(page);
    let fail = true;
    await page.route('**/char-editor-*.js', route => {
        if (fail) { fail = false; return route.abort(); }
        return route.continue();
    });
    await navigate(page, '/rpg/char-editor');
    await expect(page.locator('[data-feature="char-editor"]')).toHaveAttribute('data-feature-state', 'error');
    await page.locator('[data-feature-retry]').click();
    await ready(page, '[data-feature="char-editor"]');
    await expect(page.getByLabel('Spielername')).toBeVisible();
});
