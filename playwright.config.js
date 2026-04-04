const { defineConfig } = require('@playwright/test');

const port = process.env.E2E_PORT || '8010';
const baseURL = process.env.E2E_BASE_URL || `http://127.0.0.1:${port}`;

module.exports = defineConfig({
  testDir: 'tests/e2e',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 60 * 1000,
  use: {
    baseURL,
    headless: true,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off',
  },
  webServer: {
    command: `php artisan serve --host=127.0.0.1 --port=${port}`,
    env: {
      ...process.env,
      APP_ENV: 'local',
      DB_DATABASE: process.env.E2E_DB_DATABASE || process.env.DB_DATABASE || 'cbex',
    },
    url: baseURL,
    reuseExistingServer: true,
    timeout: 120 * 1000,
  },
});
