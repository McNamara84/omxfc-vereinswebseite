import { defineConfig } from '@playwright/test';
import browserConfig from './playwright.config.js';

export default defineConfig({
    ...browserConfig,
    testMatch: '**/*.benchmark.js',
    projects: browserConfig.projects.filter(project => project.name === 'chromium'),
    workers: 1,
    retries: 0,
});
