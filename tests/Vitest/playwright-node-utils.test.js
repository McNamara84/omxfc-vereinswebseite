/**
 * @vitest-environment node
 */

import { collectBrowserInstallProjects, createProjectRuns, isDirectExecution, main } from '../e2e/run-playwright-docker.mjs';
import { createPlaywrightRunToken, resolvePlaywrightRunToken } from '../e2e/utils/playwright-run-token.js';

describe('playwright run token helper', () => {
    it('erstellt praefixierte Tokens ueber die injizierte UUID-Factory', () => {
        const token = createPlaywrightRunToken('local', {
            uuidFactory: () => 'uuid-123',
        });

        expect(token).toBe('local-uuid-123');
    });

    it('uebernimmt vorhandene Tokens unveraendert', () => {
        expect(resolvePlaywrightRunToken('provided-token', { prefix: 'docker' })).toBe('provided-token');
    });

    it('behandelt leere oder whitespace-only Tokens wie fehlende Werte', () => {
        const token = resolvePlaywrightRunToken('   ', {
            prefix: 'docker',
            uuidFactory: () => 'uuid-456',
        });

        expect(token).toBe('docker-uuid-456');
    });
});

describe('playwright docker harness', () => {
    it('plant pro Browserprojekt eigene Ports und Tokens', () => {
        const runs = createProjectRuns({
            args: ['tests/e2e/spenden.spec.js'],
            env: {
                CI: '1',
                PLAYWRIGHT_PORT: '8100',
            },
            basePort: 8100,
        });

        expect(runs).toHaveLength(2);
        expect(runs[0].args).toEqual(['tests/e2e/spenden.spec.js', '--project', 'chromium']);
        expect(runs[0].env.PLAYWRIGHT_PORT).toBe('8100');
        expect(runs[0].env.PLAYWRIGHT_RUN_TOKEN).toMatch(/^docker-chromium-/);
        expect(runs[1].args).toEqual(['tests/e2e/spenden.spec.js', '--project', 'firefox']);
        expect(runs[1].env.PLAYWRIGHT_PORT).toBe('8101');
        expect(runs[1].env.PLAYWRIGHT_RUN_TOKEN).toMatch(/^docker-firefox-/);
    });

    it('haelt explizite Projektwahl kompakt und respektiert vorhandene Tokens', () => {
        const runs = createProjectRuns({
            args: ['--project=webkit'],
            env: {
                PLAYWRIGHT_RUN_TOKEN: 'provided-token',
            },
            basePort: 8001,
        });

        expect(runs).toEqual([
            {
                args: ['--project=webkit'],
                env: {
                    PLAYWRIGHT_RUN_TOKEN: 'provided-token',
                },
            },
        ]);
    });

    it('ermittelt installierbare Browserprojekte ohne Duplikate', () => {
        const projects = collectBrowserInstallProjects([
            { args: ['--project=chromium'] },
            { args: ['--project', 'firefox'] },
            { args: ['--project=mobile-chrome'] },
            { args: ['--project', 'chromium'] },
        ]);

        expect(projects).toEqual(['chromium', 'firefox']);
    });

    it('erkennt direkte Ausfuehrung auch mit relativem Scriptpfad robust', () => {
        expect(() => isDirectExecution('tests/e2e/run-playwright-docker.mjs')).not.toThrow();
        expect(isDirectExecution('tests/e2e/run-playwright-docker.mjs')).toBe(true);
        expect(isDirectExecution('tests/e2e/other-script.mjs')).toBe(false);
    });

    it('baut vor Docker-Playwright standardmaessig die Vite-Assets', async () => {
        const cleanupManagedDockerPortFn = vi.fn();
        const spawnFn = vi.fn(() => {
            const handlers = new Map();

            queueMicrotask(() => {
                handlers.get('exit')?.(0);
            });

            return {
                on(event, handler) {
                    handlers.set(event, handler);
                    return this;
                },
            };
        });

        const exitCode = await main({
            argv: ['tests/e2e/spenden.spec.js', '--project=webkit'],
            env: {
                PLAYWRIGHT_RUN_TOKEN: 'provided-token',
                PLAYWRIGHT_PORT: '8100',
            },
            spawnFn,
            cleanupManagedDockerPortFn,
        });

        expect(exitCode).toBe(0);
        expect(spawnFn).toHaveBeenCalledTimes(3);
        expect(spawnFn.mock.calls[0][1]).toEqual([
            expect.stringMatching(/node_modules[\\/]playwright[\\/]cli\.js$/),
            'install',
            'webkit',
        ]);
        expect(spawnFn.mock.calls[1][1]).toEqual([
            expect.stringMatching(/node_modules[\\/]vite[\\/]bin[\\/]vite\.js$/),
            'build',
        ]);
        expect(spawnFn.mock.calls[2][2].env).toMatchObject({
            PLAYWRIGHT_USE_DOCKER: '1',
            PLAYWRIGHT_RUN_TOKEN: 'provided-token',
            PLAYWRIGHT_PORT: '8100',
        });
        expect(cleanupManagedDockerPortFn).toHaveBeenCalledTimes(2);
        expect(cleanupManagedDockerPortFn).toHaveBeenNthCalledWith(1, 8100);
        expect(cleanupManagedDockerPortFn).toHaveBeenNthCalledWith(2, 8100);
    });

    it('ueberspringt den Vite-Build wenn Hotfile explizit aktiviert ist', async () => {
        const cleanupManagedDockerPortFn = vi.fn();
        const spawnFn = vi.fn(() => {
            const handlers = new Map();

            queueMicrotask(() => {
                handlers.get('exit')?.(0);
            });

            return {
                on(event, handler) {
                    handlers.set(event, handler);
                    return this;
                },
            };
        });

        const exitCode = await main({
            argv: ['tests/e2e/spenden.spec.js', '--project=webkit'],
            env: {
                PLAYWRIGHT_RUN_TOKEN: 'provided-token',
                PLAYWRIGHT_PORT: '8100',
                PLAYWRIGHT_USE_VITE_HOT: '1',
            },
            spawnFn,
            cleanupManagedDockerPortFn,
        });

        expect(exitCode).toBe(0);
        expect(spawnFn).toHaveBeenCalledTimes(2);
        expect(spawnFn.mock.calls[0][1]).toEqual([
            expect.stringMatching(/node_modules[\\/]playwright[\\/]cli\.js$/),
            'install',
            'webkit',
        ]);
        expect(spawnFn.mock.calls[1][1]).toEqual([
            expect.stringMatching(/node_modules[\\/]playwright[\\/]cli\.js$/),
            'test',
            'tests/e2e/spenden.spec.js',
            '--project=webkit',
        ]);
    });
});

describe('playwright config smoke import', () => {
    beforeEach(() => {
        // Exercise local defaults even when Vitest itself runs on a CI runner.
        vi.stubEnv('CI', undefined);
        vi.stubEnv('APP_KEY', undefined);
        vi.stubEnv('PLAYWRIGHT_USE_DOCKER', undefined);
        vi.stubEnv('PLAYWRIGHT_WORKERS', undefined);
        vi.stubEnv('PLAYWRIGHT_REUSE_EXISTING_SERVER', undefined);
        vi.stubEnv('PLAYWRIGHT_RUN_TOKEN', undefined);
        vi.stubEnv('PLAYWRIGHT_USE_VITE_HOT', undefined);
        vi.resetModules();
    });

    afterEach(() => {
        vi.unstubAllEnvs();
    });

    it('laedt die Config und setzt einen konsistenten Run-Token', async () => {
        const { default: config } = await import('../../playwright.config.js');

        expect(process.env.PLAYWRIGHT_RUN_TOKEN).toMatch(/^local-/);
        expect(config.webServer.env.PLAYWRIGHT_RUN_TOKEN).toBe(process.env.PLAYWRIGHT_RUN_TOKEN);
        const applicationKey = config.webServer.env.APP_KEY;
        expect(applicationKey).toBe(process.env.APP_KEY);
        expect(applicationKey).toMatch(/^base64:[A-Za-z0-9+/]{43}=$/);
        expect(Buffer.from(applicationKey.slice(7), 'base64')).toHaveLength(32);
        vi.resetModules();
        const reloadedConfig = (await import('../../playwright.config.js')).default;
        expect(reloadedConfig.webServer.env.APP_KEY).toBe(applicationKey);
        expect(config.webServer.env.PLAYWRIGHT_USE_VITE_HOT).toBe('0');
        expect(config.projects.map(project => project.name)).toEqual(['chromium', 'firefox', 'webkit']);
        expect(config.workers).toBeUndefined();
        expect(config.retries).toBe(0);
        expect(config.forbidOnly).toBe(false);
        expect(config.webServer.reuseExistingServer).toBe(true);
        expect(config.use.trace).toBe('retain-on-failure-and-retries');
    });

    it('reicht PLAYWRIGHT_USE_VITE_HOT explizit an den Webserver weiter', async () => {
        vi.stubEnv('PLAYWRIGHT_USE_VITE_HOT', '1');

        const { default: config } = await import('../../playwright.config.js');

        expect(config.webServer.env.PLAYWRIGHT_USE_VITE_HOT).toBe('1');
    });

    it('isoliert CI-Browser, Worker, Server und Diagnoseeinstellungen von lokalen Defaults', async () => {
        vi.stubEnv('CI', 'true');
        const suppliedKey = `base64:${Buffer.alloc(32, 7).toString('base64')}`;
        vi.stubEnv('APP_KEY', suppliedKey);

        const { default: config } = await import('../../playwright.config.js');

        expect(config.projects.map(project => project.name)).toEqual(['chromium', 'firefox']);
        expect(config.webServer.env.APP_KEY).toBe(suppliedKey);
        expect(process.env.APP_KEY).toBe(suppliedKey);
        expect(config.workers).toBe(1);
        expect(config.retries).toBe(1);
        expect(config.forbidOnly).toBe(true);
        expect(config.webServer.reuseExistingServer).toBe(false);
        expect(config.use.trace).toEqual({
            mode: 'on-first-retry',
            snapshots: { dom: true, aria: true, screen: true },
        });
    });
});
