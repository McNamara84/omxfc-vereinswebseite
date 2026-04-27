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
        globals: true,
        dir: 'tests/Vitest',
        include: ['**/*.test.js'],
    },
});