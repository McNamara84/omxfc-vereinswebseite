import { test, expect } from './test-support.js';
import { spawnSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { gzipSync } from 'node:zlib';
import { createPhpProcess } from './utils/php.js';

const scenarios = [
    { name: 'homepage', url: '/', public: true }, { name: 'login', url: '/login', public: true },
    { name: 'members', url: '/mitglieder' },
    { name: 'member-map', url: '/mitglieder/karte', selector: '[data-member-map] .leaflet-pane' },
    { name: 'maddraxiversum', url: '/maddraxiversum', selector: '#map .leaflet-pane' },
    { name: 'statistics', url: '/statistiken', chart: true },
    { name: 'admin-charts', url: '/admin/statistiken', chart: true },
    { name: 'character-editor', url: '/rpg/char-editor', editor: true },
];
const summary = values => {
    const sorted = [...values].sort((a, b) => a - b);
    return { min: sorted[0], median: sorted[Math.floor(sorted.length / 2)], max: sorted.at(-1) };
};

test('measures five cold contexts and warm feature navigation with production assets', async ({ browser, baseURL }, testInfo) => {
    test.setTimeout(300_000);
    const command = createPhpProcess(['tests/e2e/create-feature-lifecycle-fixture.php'], { env: process.env });
    const result = spawnSync(command.command, command.args, { env: process.env, shell: command.shell, encoding: 'utf8', windowsHide: true });
    if (result.status !== 0) throw new Error(result.stderr);
    const { email } = JSON.parse(result.stdout);
    const loginContext = await browser.newContext({ baseURL });
    const login = await loginContext.newPage();
    await login.goto('/login');
    await login.locator('[name=email]').fill(email);
    await login.locator('[name=password]').fill('password');
    await login.locator('button[type=submit]').click();
    await login.waitForURL(url => !url.pathname.endsWith('/login'));
    await expect(login.locator('[data-dashboard-activity-feed]:not([aria-busy="true"])')).toBeAttached();
    const storageState = await loginContext.storageState();
    await loginContext.close();
    const output = [];

    for (const scenario of scenarios) {
        for (const mode of scenario.public ? ['cold'] : ['cold', 'warm']) {
            const runs = [];
            for (let iteration = 0; iteration < 5; iteration++) {
                const context = await browser.newContext({ baseURL, ...(scenario.public ? {} : { storageState }) });
                try {
                    await context.route(/tile\.openstreetmap\.org|mapdraxv2\.maddraxikon\.com/, route => route.fulfill({ status: 200, contentType: 'image/png', body: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', 'base64') }));
                    await context.route('**/maddraxikon-staedte', route => route.fulfill({ json: { query: { results: {} } } }));
                    await context.addInitScript(() => {
                        window.__benchmarkLcp = null;
                        new PerformanceObserver(list => { window.__benchmarkLcp = list.getEntries().at(-1)?.startTime; }).observe({ type: 'largest-contentful-paint', buffered: true });
                    });
                    const page = await context.newPage();
                    const session = await context.newCDPSession(page);
                    await session.send('Performance.enable');
                    if (mode === 'warm') {
                        await page.goto(scenario.name === 'members' ? '/' : '/mitglieder');
                        await page.waitForFunction(() => typeof window.Livewire?.navigate === 'function');
                    }
                    const before = await session.send('Performance.getMetrics');
                    const started = Date.now();
                    const mark = mode === 'warm' ? await page.evaluate(() => performance.now()) : 0;
                    if (mode === 'warm') {
                        await page.evaluate(url => {
                            window.__benchmarkNavigation = false;
                            document.addEventListener('livewire:navigated', () => { window.__benchmarkNavigation = true; }, { once: true });
                            window.Livewire.navigate(url);
                        }, scenario.url);
                        await page.waitForURL(url => url.pathname === scenario.url);
                        await page.waitForFunction(() => window.__benchmarkNavigation);
                    } else await page.goto(scenario.url);
                    if (scenario.selector) await expect(page.locator(scenario.selector).first()).toBeAttached();
                    if (scenario.chart) await page.waitForFunction(() => [...document.querySelectorAll('canvas')].some(canvas => canvas.style.display === 'block'));
                    if (scenario.editor) await page.waitForFunction(() => typeof window.Alpine?.$data(document.querySelector('[x-data="charEditor()"]'))?.formValid === 'function');
                    await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
                    const readyMs = Date.now() - started;
                    const raw = await page.evaluate(mark => ({
                        fcpMs: performance.getEntriesByName('first-contentful-paint')[0]?.startTime ?? null,
                        lcpMs: window.__benchmarkLcp,
                        resources: performance.getEntriesByType('resource').filter(entry => entry.startTime >= mark).map(entry => ({ url: entry.name, transferBytes: entry.transferSize })),
                    }), mark);
                    const after = await session.send('Performance.getMetrics');
                    const metric = (data, name) => data.metrics.find(entry => entry.name === name)?.value ?? 0;
                    const scriptMs = (metric(after, 'ScriptDuration') - metric(before, 'ScriptDuration')) * 1000;
                    const assets = [...new Set(raw.resources.map(entry => new URL(entry.url).pathname).filter(url => url.startsWith('/build/') && /\.(js|css)$/.test(url)))];
                    const gzipBytes = assets.reduce((total, url) => total + gzipSync(readFileSync(path.join('public', url))).length, 0);
                    runs.push({ readyMs, scriptMs, fcpMs: mode === 'cold' ? raw.fcpMs : null, lcpMs: mode === 'cold' ? raw.lcpMs : null,
                        transferBytes: raw.resources.reduce((total, resource) => total + resource.transferBytes, 0), gzipBytes, assets });
                } finally { await context.close(); }
            }
            output.push({ scenario: scenario.name, mode, readyMs: summary(runs.map(run => run.readyMs)), scriptMs: summary(runs.map(run => run.scriptMs)),
                gzipBytes: summary(runs.map(run => run.gzipBytes)), runs });
        }
    }
    const directory = process.env.PERFORMANCE_RESULTS_DIR ?? path.resolve(testInfo.project.outputDir, '..', 'performance-results');
    mkdirSync(directory, { recursive: true });
    writeFileSync(path.join(directory, 'features.json'), JSON.stringify(output, null, 2));
    await testInfo.attach('features.json', { body: JSON.stringify(output, null, 2), contentType: 'application/json' });
    expect(output.every(item => item.runs.length === 5)).toBe(true);
});
