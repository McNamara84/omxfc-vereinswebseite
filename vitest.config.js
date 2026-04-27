import { defineConfig, mergeConfig } from 'vitest/config';
import viteConfig from './vite.config.js';

export default defineConfig((configEnv) => mergeConfig(
    typeof viteConfig === 'function' ? viteConfig(configEnv) : viteConfig,
    defineConfig({
        test: {
            environment: 'jsdom',
            include: ['tests/Vitest/**/*.test.js'],
        },
    })
));