import { createRequire } from 'node:module';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

const require = createRequire(import.meta.url);
const daisyuiEntry = require.resolve('daisyui');

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
            // Fuer @plugin "daisyui" in resources/css/app.css den JS-Package-Entry erzwingen.
            daisyui: daisyuiEntry,
            '~leaflet': 'leaflet',
        },
    },
    server: command === 'serve'
        ? {
            // Native Vite-8-Serveroption, kein zusaetzliches Plugin:
            // https://vite.dev/config/server-options#server-forwardconsole
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