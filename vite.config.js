import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

export default defineConfig(({ command }) => ({
    plugins: [
        tailwindcss(),
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/maddraxiversum.js',
                'resources/js/statistik.js',
                'resources/js/romantausch-bundle-preview.js',
            ],
            refresh: true,
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
            daisyui: path.resolve(__dirname, 'node_modules/daisyui/index.js'),
            '~leaflet': 'leaflet',
        },
    },
    server: command === 'serve'
        ? {
            forwardConsole: {
                unhandledErrors: true,
                logLevels: ['warn', 'error'],
            },
        }
        : undefined,
    // Force-Clear-Cache bei jedem Build
    cacheDir: '.vite/cache',
    // Bessere Fehlerbehandlung
    build: {
        sourcemap: true,
    },
    test: {
        environment: 'jsdom',
        include: ['tests/Vitest/**/*.test.js'],
    },
}));