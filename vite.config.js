import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // Explizit den public-Pfad setzen
            publicDirectory: 'public',
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources'),
        },
    },
    // Force-Clear-Cache bei jedem Build
    cacheDir: '.vite/cache',
    // Bessere Fehlerbehandlung
    build: {
        sourcemap: true,
    },
});