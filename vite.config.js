import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

const require = createRequire(import.meta.url);
const currentDir = path.dirname(fileURLToPath(import.meta.url));
const daisyuiEntry = require.resolve('daisyui');
const vitePort = Number(process.env.VITE_PORT ?? 5173);
const viteOrigin = process.env.VITE_DEV_SERVER_URL ?? `http://localhost:${vitePort}`;
const viteHmrHost = process.env.VITE_HMR_HOST ?? 'localhost';
const usePolling = process.env.VITE_USE_POLLING === '1';
const pollingInterval = Number(process.env.VITE_POLLING_INTERVAL ?? 300);

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
            '@': path.resolve(currentDir, 'resources/js'),
            // Fuer @plugin "daisyui" in resources/css/app.css den JS-Package-Entry erzwingen.
            daisyui: daisyuiEntry,
            '~leaflet': 'leaflet',
        },
    },
    server: command === 'serve'
        ? {
            host: '0.0.0.0',
            port: vitePort,
            strictPort: true,
            origin: viteOrigin,
            hmr: {
                host: viteHmrHost,
                port: vitePort,
            },
            watch: usePolling
                ? {
                    usePolling: true,
                    interval: pollingInterval,
                }
                : undefined,
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
}));