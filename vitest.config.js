import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

const currentDir = path.dirname(fileURLToPath(import.meta.url));

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
        include: ['**/*.test.js'],
        coverage: {
            provider: 'v8',
            reporter: ['json-summary'],
            reportsDirectory: 'coverage',
        },
    },
});