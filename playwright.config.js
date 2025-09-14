import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: 'tests/e2e',
  webServer: {
    command:
      'bash -c "php artisan migrate:fresh --seed --env=testing && php -S 127.0.0.1:8000 -t public public/index.php"',
    port: 8000,
    reuseExistingServer: !process.env.CI,
    timeout: 180000,
    env: {
      APP_ENV: 'testing',
      APP_LOCALE: 'de',
      DISABLE_GEOCODING: 'true',
    },
  },
  use: {
    baseURL: 'http://127.0.0.1:8000',
  },
});
