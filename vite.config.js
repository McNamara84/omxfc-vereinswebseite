import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

export default defineConfig({
    plugins: [
        tailwindcss(),
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/maddraxiversum.js',
                'resources/js/statistik.js',
                'resources/js/changelog.js',
                'resources/js/hoerbuecher.js',
                'resources/js/hoerbuch-role-form.js',
                'resources/js/hoerbuch-role-upload-toggle.js',
                'resources/js/fantreffen.js',
                'resources/js/romantausch-bundle-preview.js',
            ],
            refresh: true,
            // Explizit den public-Pfad setzen
            publicDirectory: 'public',
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
            '~leaflet': 'leaflet',
        },
    },
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
});