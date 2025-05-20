import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import path from 'path';

export default defineConfig(({ command }) => {
    const config = {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
                publicDirectory: 'public',
            }),
        ],
        resolve: {
            alias: {
                '@': path.resolve(__dirname, 'resources'),
            },
        },
    };

    if (command === 'build') {
        // Produktionsspezifische Einstellungen
        config.build = {
            // Manifest erzeugen
            manifest: true,
            // Sourcemaps für Debugging
            sourcemap: true,
            // Explizite Ausgabepfade
            outDir: 'public/build',
        };
    }

    return config;
});