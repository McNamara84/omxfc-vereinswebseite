import { defineConfig, devices } from '@playwright/test';
import path from 'path';
import { formatPhpCommand } from './tests/e2e/utils/php.js';

const databasePath = path.resolve('database/playwright.sqlite');
const phpCommand = formatPhpCommand();
const playwrightPort = Number(process.env.PLAYWRIGHT_PORT ?? 8001);

// WebKit auf Linux CI ist notorisch instabil (Timeout-Probleme)
// Daher nur Chromium und Firefox auf CI verwenden
const isCI = !!process.env.CI;

export default defineConfig({
  testDir: 'tests/e2e',
  globalSetup: './tests/e2e/global-setup.js',
  
  // Browser-Projekte explizit definieren
  projects: isCI
    ? [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
        { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
        // WebKit auf CI deaktiviert wegen Timeout-Instabilität auf Linux
      ]
    : [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
        { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
        { name: 'webkit', use: { ...devices['Desktop Safari'] } },
      ],
  
  webServer: {
    command: `${phpCommand} -S 127.0.0.1:${playwrightPort} server.php`,
    port: playwrightPort,
    reuseExistingServer: process.env.PLAYWRIGHT_REUSE_EXISTING_SERVER === '1',
    timeout: 180000,
    stdout: 'pipe',
    stderr: 'pipe',
    env: {
      APP_ENV: 'testing',
      APP_DEBUG: 'false',
      APP_KEY: process.env.APP_KEY ?? 'base64:oK0ZsJlI+o7C++h527lMcrrO4jzZrXqhouB/p0l+gFw=',
      DB_CONNECTION: 'sqlite',
      DB_DATABASE: databasePath,
      SESSION_DRIVER: 'file',
      CACHE_DRIVER: 'array',
      QUEUE_CONNECTION: 'database',
      MAIL_MAILER: 'array',
      FORTIFY_DISABLE_LOGIN_RATE_LIMIT: 'true',
      FANTREFFEN_TSHIRT_DEADLINE: '2099-12-31 23:59:59',
      FANTREFFEN_MIN_FORM_TIME: '0',
      FANTREFFEN_DISABLE_RATE_LIMIT: 'true',
    },
  },
  use: {
    baseURL: `http://127.0.0.1:${playwrightPort}`,
  },
});
