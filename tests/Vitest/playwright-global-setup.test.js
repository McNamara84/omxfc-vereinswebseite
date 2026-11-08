/** @vitest-environment node */

import path from 'node:path';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import globalSetup from '../e2e/global-setup.js';

const harness = vi.hoisted(() => ({
    events: [],
    dirtyViews: true,
    failCleanup: false,
    fs: {
        existsSync: vi.fn(), rmSync: vi.fn(), mkdirSync: vi.fn(),
        openSync: vi.fn(), closeSync: vi.fn(), writeFileSync: vi.fn(),
    },
    spawnSync: vi.fn(),
    runArtisan: vi.fn(),
    createPhpProcess: vi.fn(),
}));

vi.mock('fs', () => ({ default: harness.fs }));
vi.mock('child_process', () => ({ spawnSync: harness.spawnSync }));
vi.mock('../e2e/utils/artisan.js', () => ({ runArtisan: harness.runArtisan }));
vi.mock('../e2e/utils/php.js', () => ({
    createPhpProcess: harness.createPhpProcess,
    toPhpRuntimePath: value => value,
}));

describe('Playwright global setup after PHP tests', () => {
    let originalEnvironment;

    beforeEach(() => {
        originalEnvironment = { ...process.env };
        harness.events.length = 0;
        harness.dirtyViews = true;
        harness.failCleanup = false;
        harness.fs.existsSync.mockReturnValue(true);
        harness.fs.openSync.mockReturnValue(17);
        harness.createPhpProcess.mockReturnValue({ command: 'php', args: ['load-schema'], shell: false });
        harness.spawnSync.mockImplementation(() => {
            harness.events.push('schema');
            return { status: 0 };
        });
        harness.runArtisan.mockImplementation(async args => {
            harness.events.push(args[0]);
            if (args[0] === 'migrate') harness.dirtyViews = true;
            if (args[0] === 'view:clear') {
                if (harness.failCleanup) throw new Error('View cache cleanup failed');
                harness.dirtyViews = false;
            }
        });
        harness.fs.writeFileSync.mockImplementation(() => {
            harness.events.push({ ready: true, dirtyViews: harness.dirtyViews });
        });
        process.env.PLAYWRIGHT_RUN_TOKEN = 'view-cache-regression';
    });

    afterEach(() => {
        for (const key of Object.keys(process.env)) if (!(key in originalEnvironment)) delete process.env[key];
        Object.assign(process.env, originalEnvironment);
    });

    it('discards PHP-test views after console seeding and before publishing browser readiness', async () => {
        await globalSetup();

        expect(harness.events).toEqual(['schema', 'migrate', 'view:clear', { ready: true, dirtyViews: false }]);
        expect(harness.fs.writeFileSync).toHaveBeenCalledWith(
            path.resolve('storage/framework/testing/playwright-ready-view-cache-regression.flag'),
            'ready\n',
        );
    });

    it('does not publish readiness when stale views cannot be removed', async () => {
        harness.failCleanup = true;

        await expect(globalSetup()).rejects.toThrow('View cache cleanup failed');
        expect(harness.fs.writeFileSync).not.toHaveBeenCalled();
    });

    it('does not clear views or publish readiness after a failed schema load', async () => {
        harness.spawnSync.mockReturnValue({ status: 1 });

        await expect(globalSetup()).rejects.toThrow('Failed to load SQLite schema dump');
        expect(harness.runArtisan).not.toHaveBeenCalled();
        expect(harness.fs.writeFileSync).not.toHaveBeenCalled();
    });
});
