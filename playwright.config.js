import { defineConfig, devices } from '@playwright/test';
import path from 'path';
import { formatDockerServiceCommand, formatPhpCommand, shouldUseDockerPhp, toPhpRuntimePath } from './tests/e2e/utils/php.js';
import { resolvePlaywrightRunToken } from './tests/e2e/utils/playwright-run-token.js';

const databasePath = toPhpRuntimePath(path.resolve('database/playwright.sqlite'));
const playwrightPort = Number(process.env.PLAYWRIGHT_PORT ?? 8001);
const vitePort = Number(process.env.VITE_PORT ?? process.env.DOCKER_DEV_VITE_PORT ?? 5173);
const phpServerHost = shouldUseDockerPhp() ? '0.0.0.0' : '127.0.0.1';
const isCI = !!process.env.CI;
const playwrightRunToken = resolvePlaywrightRunToken(process.env.PLAYWRIGHT_RUN_TOKEN, { prefix: 'local' });
const playwrightViteDevServerUrl = process.env.VITE_DEV_SERVER_URL
  ?? process.env.DOCKER_DEV_VITE_DEV_SERVER_URL
  ?? `http://localhost:${vitePort}`;
process.env.PLAYWRIGHT_RUN_TOKEN = playwrightRunToken;
const configuredWorkers = Number(process.env.PLAYWRIGHT_WORKERS ?? NaN);
const playwrightWorkers = Number.isInteger(configuredWorkers) && configuredWorkers > 0
  ? configuredWorkers
  : isCI || shouldUseDockerPhp()
    ? 1
    : undefined;
const phpEnvironment = {
  APP_ENV: 'testing',
  APP_DEBUG: 'false',
  APP_KEY: process.env.APP_KEY ?? 'base64:oK0ZsJlI+o7C++h527lMcrrO4jzZrXqhouB/p0l+gFw=',
  DB_CONNECTION: 'sqlite',
  DB_DATABASE: databasePath,
  SESSION_DRIVER: 'file',
  CACHE_STORE: 'array',
  CACHE_DRIVER: 'array',
  VITE_DEV_SERVER_URL: playwrightViteDevServerUrl,
  QUEUE_CONNECTION: 'database',
  MAIL_MAILER: 'array',
  FORTIFY_DISABLE_LOGIN_RATE_LIMIT: 'true',
  FANTREFFEN_TSHIRT_DEADLINE: '2099-12-31 23:59:59',
  FANTREFFEN_MIN_FORM_TIME: '0',
  FANTREFFEN_DISABLE_RATE_LIMIT: 'true',
  PLAYWRIGHT_USE_DOCKER: process.env.PLAYWRIGHT_USE_DOCKER ?? '0',
  PLAYWRIGHT_PORT: String(playwrightPort),
  DOCKER_DEV_PLAYWRIGHT_PORT: String(playwrightPort),
  PLAYWRIGHT_RUN_TOKEN: playwrightRunToken,
};
const phpCommand = shouldUseDockerPhp()
  ? formatDockerServiceCommand(['sh', toPhpRuntimePath(path.resolve('tests/e2e/start-playwright-webserver.sh'))], {
      servicePorts: true,
      env: phpEnvironment,
    })
  : formatPhpCommand(['-S', `${phpServerHost}:${playwrightPort}`, 'server.php'], {
      servicePorts: false,
      env: phpEnvironment,
    });

// WebKit auf Linux CI ist notorisch instabil (Timeout-Probleme)
// Daher nur Chromium und Firefox auf CI verwenden
const shouldReuseExistingServerByDefault = !isCI && !shouldUseDockerPhp();
const shouldReuseExistingServer = process.env.PLAYWRIGHT_REUSE_EXISTING_SERVER === undefined
  ? shouldReuseExistingServerByDefault
  : process.env.PLAYWRIGHT_REUSE_EXISTING_SERVER === '1';

export default defineConfig({
  testDir: 'tests/e2e',
  globalSetup: './tests/e2e/global-setup.js',
  // The Docker harness serves the app through a single PHP dev server instance
  // backed by one shared SQLite file. Default to one worker there to avoid
  // request queueing and cross-test interference; allow explicit override.
  workers: playwrightWorkers,
  
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
