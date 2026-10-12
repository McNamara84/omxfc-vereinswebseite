import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

const currentDir = path.dirname(fileURLToPath(import.meta.url));
const nodeTestFiles = ['docker-utils', 'php-utils', 'navigation-utils', 'performance-metrics', 'playwright-node-utils']
    .map(name => `**/${name}.test.js`);
const configuredWorkers = Number(process.env.VITEST_WORKERS ?? 2);
if (!Number.isInteger(configuredWorkers) || configuredWorkers < 1) {
    throw new Error('VITEST_WORKERS must be a positive integer.');
}

export default defineConfig({
    resolve: {
        alias: {
            '@': path.resolve(currentDir, 'resources/js'),
        },
    },
    test: {
        maxWorkers: configuredWorkers,
        environment: 'jsdom',
        environmentOptions: {
            jsdom: {
                url: 'http://localhost/',
            },
        },
        globals: true,
        setupFiles: ['tests/Vitest/setup.js'],
        dir: 'tests/Vitest',
        projects: [
            { test: { name: 'node', environment: 'node', include: nodeTestFiles } },
            { test: { name: 'dom', environment: 'jsdom', include: ['**/*.test.js'], exclude: nodeTestFiles } },
        ],
        coverage: {
            provider: 'v8',
            reporter: ['json', 'json-summary'],
            reportsDirectory: 'coverage',
        },
    },
});
