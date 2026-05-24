/**
 * @vitest-environment node
 */

import { createProjectRuns, isDirectExecution, main } from '../e2e/run-playwright-docker.mjs';
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
            args: ['tests/e2e/homepage-performance.spec.js'],
            env: {
                CI: '1',
                PLAYWRIGHT_PORT: '8100',
            },
            basePort: 8100,
        });

        expect(runs).toHaveLength(2);
        expect(runs[0].args).toEqual(['tests/e2e/homepage-performance.spec.js', '--project', 'chromium']);
        expect(runs[0].env.PLAYWRIGHT_PORT).toBe('8100');
        expect(runs[0].env.PLAYWRIGHT_RUN_TOKEN).toMatch(/^docker-chromium-/);
        expect(runs[1].args).toEqual(['tests/e2e/homepage-performance.spec.js', '--project', 'firefox']);
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

    it('erkennt direkte Ausfuehrung auch mit relativem Scriptpfad robust', () => {
        expect(() => isDirectExecution('tests/e2e/run-playwright-docker.mjs')).not.toThrow();
        expect(isDirectExecution('tests/e2e/run-playwright-docker.mjs')).toBe(true);
        expect(isDirectExecution('tests/e2e/other-script.mjs')).toBe(false);
    });

    it('startet main ohne childEnv-Scope-Fehler und merged die Env korrekt', async () => {
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
            argv: ['tests/e2e/homepage-performance.spec.js', '--project=webkit'],
            env: {
                PLAYWRIGHT_RUN_TOKEN: 'provided-token',
                PLAYWRIGHT_PORT: '8100',
            },
            spawnFn,
            cleanupManagedDockerPortFn,
        });

        expect(exitCode).toBe(0);
        expect(spawnFn).toHaveBeenCalledTimes(1);
        expect(spawnFn.mock.calls[0][2].env).toMatchObject({
            PLAYWRIGHT_USE_DOCKER: '1',
            PLAYWRIGHT_RUN_TOKEN: 'provided-token',
            PLAYWRIGHT_PORT: '8100',
        });
        expect(cleanupManagedDockerPortFn).toHaveBeenCalledTimes(2);
        expect(cleanupManagedDockerPortFn).toHaveBeenNthCalledWith(1, 8100);
        expect(cleanupManagedDockerPortFn).toHaveBeenNthCalledWith(2, 8100);
    });
});

describe('playwright config smoke import', () => {
    const originalRunToken = process.env.PLAYWRIGHT_RUN_TOKEN;

    afterEach(() => {
        if (typeof originalRunToken === 'undefined') {
            delete process.env.PLAYWRIGHT_RUN_TOKEN;
            return;
        }

        process.env.PLAYWRIGHT_RUN_TOKEN = originalRunToken;
    });

    it('laedt die Config und setzt einen konsistenten Run-Token', async () => {
        delete process.env.PLAYWRIGHT_RUN_TOKEN;
        vi.resetModules();

        const { default: config } = await import('../../playwright.config.js');

        expect(process.env.PLAYWRIGHT_RUN_TOKEN).toMatch(/^local-/);
        expect(config.webServer.env.PLAYWRIGHT_RUN_TOKEN).toBe(process.env.PLAYWRIGHT_RUN_TOKEN);
    });
});