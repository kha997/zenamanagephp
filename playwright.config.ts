import { defineConfig, devices } from '@playwright/test';

/**
 * @see https://playwright.dev/docs/test-configuration
 */
export default defineConfig({
  testDir: './tests/E2E',
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
    baseURL: 'http://127.0.0.1:8000',

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
  globalSetup: require.resolve('./tests/E2E/setup/global-setup.ts'),

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
    command: 'php artisan serve --host=127.0.0.1 --port=8000',
    url: 'http://127.0.0.1:8000',
    reuseExistingServer: !process.env.CI,
    timeout: 120 * 1000,
    stdout: 'pipe',
    stderr: 'pipe',
  },
});
