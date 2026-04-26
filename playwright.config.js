import { defineConfig, devices } from '@playwright/test';
import path from 'path';
import { formatPhpCommand, shouldUseDockerPhp, toPhpRuntimePath } from './tests/e2e/utils/php.js';

const databasePath = toPhpRuntimePath(path.resolve('database/playwright.sqlite'));
const playwrightPort = Number(process.env.PLAYWRIGHT_PORT ?? 8001);
const phpServerHost = shouldUseDockerPhp() ? '0.0.0.0' : '127.0.0.1';
const phpEnvironment = {
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
  PLAYWRIGHT_PORT: String(playwrightPort),
};
const phpCommand = formatPhpCommand(['-S', `${phpServerHost}:${playwrightPort}`, 'server.php'], {
  servicePorts: shouldUseDockerPhp(),
  env: phpEnvironment,
});

// WebKit auf Linux CI ist notorisch instabil (Timeout-Probleme)
// Daher nur Chromium und Firefox auf CI verwenden
const isCI = !!process.env.CI;
const shouldReuseExistingServer = process.env.PLAYWRIGHT_REUSE_EXISTING_SERVER === undefined
  ? !isCI
  : process.env.PLAYWRIGHT_REUSE_EXISTING_SERVER === '1';

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
    command: phpCommand,
    port: playwrightPort,
    reuseExistingServer: shouldReuseExistingServer,
    timeout: 180000,
    stdout: 'pipe',
    stderr: 'pipe',
    env: phpEnvironment,
  },
  use: {
    baseURL: `http://127.0.0.1:${playwrightPort}`,
  },
});
