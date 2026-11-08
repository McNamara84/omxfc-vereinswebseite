import { defineConfig } from 'vitest/config';
import { playwright } from '@vitest/browser-playwright';

export default defineConfig({
    cacheDir: '.vite/vitest-browser',
    test: {
        name: 'browser',
        dir: 'tests/Vitest/browser',
        include: ['**/*.browser.test.js'],
        setupFiles: ['tests/Vitest/browser/setup.js'],
        maxWorkers: 1,
        attachmentsDir: 'test-results/vitest-browser/attachments',
        reporters: ['default', ['html', { outputDir: 'test-results/vitest-browser' }]],
        browser: {
            enabled: true,
            headless: true,
            provider: playwright(),
            instances: [{ browser: 'chromium' }],
            traceView: 'failure-only',
            // Experimental in 5.1, even though upstream enables it by default.
            importMapMocks: false,
            screenshotDirectory: 'test-results/vitest-browser',
        },
        // Keep the main Node/jsdom coverage report and cache independent.
        coverage: { enabled: false },
    },
});
