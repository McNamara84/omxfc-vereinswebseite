import { defineConfig } from '@playwright/test';

// Run the native ARIA/axe regression before upgrading the browser package.
// This probe uses static markup and needs neither Laravel nor a database.
export default defineConfig({
    testDir: './tests/e2e',
    testMatch: 'aria-reflection.spec.js',
    workers: 1,
    retries: 0,
    forbidOnly: Boolean(process.env.CI),
    reporter: 'list',
    outputDir: 'test-results/aria-reflection',
    projects: ['chromium', 'firefox', 'webkit'].map(browserName => ({
        name: browserName,
        use: { browserName, headless: true },
    })),
});
