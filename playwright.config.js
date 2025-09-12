import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: 'tests/e2e',
  webServer: {
    command: 'php -S 127.0.0.1:8000 -t public public/index.php',
    url: 'http://127.0.0.1:8000',
    reuseExistingServer: !process.env.CI,
    timeout: 180000,
  },
  use: {
    baseURL: 'http://127.0.0.1:8000',
  },
});
