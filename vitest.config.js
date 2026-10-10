import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

const currentDir = path.dirname(fileURLToPath(import.meta.url));
const nodeTestFiles = ['docker-utils', 'php-utils', 'navigation-utils', 'performance-metrics', 'playwright-node-utils']
    .map(name => `**/${name}.test.js`);

export default defineConfig({
    resolve: {
        alias: {
            '@': path.resolve(currentDir, 'resources/js'),
        },
    },
    test: {
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
            reporter: ['json-summary'],
            reportsDirectory: 'coverage',
        },
    },
});
