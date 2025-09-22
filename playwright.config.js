import { defineConfig } from '@playwright/test';
import path from 'path';

const databasePath = path.resolve('database/playwright.sqlite');

export default defineConfig({
  testDir: 'tests/e2e',
  globalSetup: './tests/e2e/global-setup.js',
  webServer: {
    command: 'php artisan serve --host=127.0.0.1 --port=8000',
    port: 8000,
    reuseExistingServer: !process.env.CI,
    timeout: 180000,
    env: {
      APP_ENV: 'testing',
      APP_DEBUG: 'false',
      APP_KEY: process.env.APP_KEY ?? 'base64:oK0ZsJlI+o7C++h527lMcrrO4jzZrXqhouB/p0l+gFw=',
      DB_CONNECTION: 'sqlite',
      DB_DATABASE: databasePath,
      SESSION_DRIVER: 'file',
      CACHE_STORE: 'array',
      QUEUE_CONNECTION: 'database',
      MAIL_MAILER: 'array',
      FORTIFY_DISABLE_LOGIN_RATE_LIMIT: 'true',
    },
  },
  use: {
    baseURL: 'http://127.0.0.1:8000',
  },
});
