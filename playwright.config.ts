import { defineConfig, devices } from '@playwright/test';

/**
 * Backend host/port configuration for Playwright webServer
 * Round 168: Make host/port configurable via env vars for CI/Codex compatibility
 * Default: 127.0.0.1:8000 (local dev)
 * Override: PLAYWRIGHT_BACKEND_HOST and PLAYWRIGHT_BACKEND_PORT
 */
const backendHost = process.env.PLAYWRIGHT_BACKEND_HOST ?? '127.0.0.1';
const rawBackendPort = Number(process.env.PLAYWRIGHT_BACKEND_PORT ?? '8000');
const backendPort = Number.isNaN(rawBackendPort) ? 8000 : rawBackendPort;
const backendBaseURL = process.env.PLAYWRIGHT_BASE_URL ?? `http://${backendHost}:${backendPort}`;

/**
 * Build webServer command with database configuration
 * Round 158: Add MySQL support for testing
 * Round 168: Use configurable backend host/port
 */
function buildWebServerCommand(): string {
  // DB config: Use process.env if set, otherwise default to XAMPP MySQL defaults
  // Round 161: Use zenamanage_e2e to match global-setup.ts default
  const dbConnection = process.env.DB_CONNECTION || 'mysql';
  const dbHost = process.env.DB_HOST || '127.0.0.1';
  const dbPort = process.env.DB_PORT || '3306';
  const dbDatabase = process.env.DB_DATABASE || 'zenamanage_e2e';
  const dbUsername = process.env.DB_USERNAME || 'root';
  const dbPassword = process.env.DB_PASSWORD || '';
  
  // Escape password for bash: replace single quotes with '\'' and wrap in single quotes
  // This ensures password with special chars works correctly
  const escapedPassword = dbPassword.replace(/'/g, "'\\''");
  
  // Round 161: Clear config cache before starting server to ensure DB_CONNECTION is respected
  // Round 168: Use configurable backend host/port
  return `bash -lc 'set -euo pipefail && cd frontend && npm run build && cd .. && php artisan config:clear && APP_ENV=testing SESSION_DRIVER=file DB_CONNECTION=${dbConnection} DB_HOST=${dbHost} DB_PORT=${dbPort} DB_DATABASE=${dbDatabase} DB_USERNAME=${dbUsername} DB_PASSWORD='${escapedPassword}' CORS_ALLOWED_ORIGINS=${backendBaseURL} APP_URL=${backendBaseURL} APP_KEY=base64:mIGiZouhlcX21Z+nN7cELAaY94Gi/Br0U6f72PJC1eg= php artisan serve --host=${backendHost} --port=${backendPort}'`;
}

/**
 * @see https://playwright.dev/docs/test-configuration
 */
export default defineConfig({
  testDir: './tests/e2e',
  /* Run tests in files in parallel */
  fullyParallel: true,
  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,
  /* Retry on CI only */
  retries: process.env.CI ? 2 : 0,
  /* Opt out of parallel tests on CI. */
  workers: process.env.CI ? 1 : undefined,
  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  reporter: process.env.CI ? [
    ['html'],
    ['json', { outputFile: 'test-results/results.json' }],
    ['junit', { outputFile: 'test-results/results.xml' }]
  ] : [
    ['list'],
    ['json', { outputFile: 'test-results/results.json' }]
  ],
  /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
  use: {
    /* Base URL to use in actions like `await page.goto('/')`. */
    baseURL: backendBaseURL,

    /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
    trace: 'on-first-retry',
    
    /* Take screenshot on failure */
    screenshot: 'only-on-failure',
    
    /* Record video on failure */
    video: 'retain-on-failure',
    
    /* Timeout for each action */
    actionTimeout: 10000,
    
    /* Timeout for navigation */
    navigationTimeout: 30000,
  },

  /* Global setup for all tests */
  globalSetup: require.resolve('./tests/e2e/setup/global-setup.ts'),

  /* Configure projects for major browsers */
  projects: [
    {
      name: 'smoke-chromium',
      testMatch: '**/smoke/*-minimal.spec.ts',
      testIgnore: '**/smoke/api-*.spec.ts',
      use: { 
        ...devices['Desktop Chrome'],
        // Increase timeouts for CI environment
        actionTimeout: process.env.CI ? 20000 : 5000,
        navigationTimeout: process.env.CI ? 30000 : 15000,
      },
      // Run smoke tests sequentially to avoid race conditions
      fullyParallel: false,
      workers: 1,
    },

    {
      name: 'smoke-mobile',
      testMatch: '**/smoke/*-minimal.spec.ts',
      testIgnore: '**/smoke/api-*.spec.ts',
      use: { 
        ...devices['Pixel 5'],
        // Mobile tests might be slower
        actionTimeout: 8000,
        navigationTimeout: 20000,
      },
    },

    {
      name: 'core-chromium',
      testMatch: '**/core/**/*.spec.ts',
      use: { ...devices['Desktop Chrome'] },
    },

    {
      name: 'projects-chromium',
      testMatch: '**/projects/**/*.spec.ts',
      use: { ...devices['Desktop Chrome'] },
      // Round 169: Run projects tests with 1 worker to avoid session/DB conflicts
      workers: 1,
    },

    {
      name: 'core-firefox',
      testMatch: '**/core/**/*.spec.ts',
      use: { ...devices['Desktop Firefox'] },
    },

    {
      name: 'core-webkit',
      testMatch: '**/core/**/*.spec.ts',
      use: { ...devices['Desktop Safari'] },
    },

    {
      name: 'regression-chromium',
      testMatch: '**/regression/**/*.spec.ts',
      use: { 
        ...devices['Desktop Chrome'],
        // Extended timeouts for regression tests
        actionTimeout: 15000,
        navigationTimeout: 45000,
      },
    },

    {
      name: 'regression-mobile',
      testMatch: '**/regression/**/*.spec.ts',
      use: { ...devices['Pixel 5'] },
    },

    /* Test against mobile viewports. */
    {
      name: 'Mobile Chrome',
      testMatch: '**/smoke/*-minimal.spec.ts',
      use: { ...devices['Pixel 5'] },
    },
    {
      name: 'Mobile Safari',
      testMatch: '**/smoke/*-minimal.spec.ts',
      use: { ...devices['iPhone 12'] },
    },

    {
      name: 'header-chromium',
      testMatch: '**/header/**/*.spec.ts',
      use: { ...devices['Desktop Chrome'] },
    },

    {
      name: 'auth-chromium',
      testMatch: '**/auth/**/*.spec.ts',
      use: { ...devices['Desktop Chrome'] },
    },

    /* Test against branded browsers. */
    // {
    //   name: 'Microsoft Edge',
    //   use: { ...devices['Desktop Edge'], channel: 'msedge' },
    // },
    // {
    //   name: 'Google Chrome',
    //   use: { ...devices['Desktop Chrome'], channel: 'chrome' },
    // },
  ],

  /* Run your local dev server before starting the tests */
  webServer: {
    // Round 134: Force rebuild with pipefail - build MUST fail if npm run build fails
    // Round 158: Add SESSION_DRIVER=file to ensure sessions persist across requests in testing
    // Round 158: Add DB_CONNECTION=mysql to use MySQL instead of SQLite for testing
    // DB config: Use process.env if set, otherwise default to XAMPP MySQL defaults
    command: buildWebServerCommand(),
    // Use /api/_e2e/ready endpoint - simple 200 OK response, no dependencies, no auth, no session
    // This route is defined in routes/api.php to avoid session middleware that requires APP_KEY
    // Round 168: Use configurable backend base URL
    url: `${backendBaseURL}/api/_e2e/ready`,
    // Always start fresh server to ensure correct env vars are used
    // reuseExistingServer: true causes Playwright to reuse old server with wrong env
    reuseExistingServer: false,
    // Increased timeout to 240s (4 minutes) to handle slow bootstrap and config loading
    timeout: 240 * 1000,
    stdout: 'pipe',
    stderr: 'pipe',
  },
});
