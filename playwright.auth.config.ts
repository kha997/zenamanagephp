import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright Configuration for Authentication E2E Tests
 * Comprehensive test suite for auth hardening
 * 
 * @see https://playwright.dev/docs/test-configuration
 */
export default defineConfig({
  testDir: './tests/E2E/auth',
  
  /* Run tests in files in parallel */
  fullyParallel: true,
  
  /* Fail the build on CI if you accidentally left test.only in the source code */
  forbidOnly: true,
  
  /* Retry */
  retries: process.env.CI ? 2 : 1,
  
  /* Reporter to use */
  outputDir: 'playwright-auth-results',
  reporter: process.env.CI ? [
    ['list'],
    ['html', { outputFolder: 'auth-report' }],
    ['json', { outputFile: 'auth-results.json' }],
    ['junit', { outputFile: 'auth-results.xml' }]
  ] : [
    ['list'],
    ['html', { outputFolder: 'auth-report' }]
  ],
  
  /* Shared settings for all projects */
  use: {
    /* Base URL */
    baseURL: process.env.BASE_URL || 'http://127.0.0.1:8000',
    
    /* Collect trace when retrying the failed test */
    trace: 'on-first-retry',
    
    /* Take screenshot on failure */
    screenshot: 'only-on-failure',
    
    /* Record video on failure */
    video: 'retain-on-failure',
    
    /* Timeouts */
    actionTimeout: 10000,
    navigationTimeout: 30000,
    
    /* Extra HTTP headers */
    extraHTTPHeaders: {
      'X-Request-Id': `test-${Date.now()}`,
    },
  },
  
  /* Global setup */
  globalSetup: require.resolve('./tests/E2E/auth/setup/global-auth-setup.ts'),
  
  /* Configure projects for different browsers */
  projects: [
    {
      name: 'auth-desktop-chromium',
      testMatch: '**/*.spec.ts',
      use: { 
        ...devices['Desktop Chrome'],
      },
    },
    
    {
      name: 'auth-desktop-firefox',
      testMatch: '**/*.spec.ts',
      use: { 
        ...devices['Desktop Firefox'],
      },
    },
    
    {
      name: 'auth-desktop-webkit',
      testMatch: '**/*.spec.ts',
      use: { 
        ...devices['Desktop Safari'],
      },
    },
    
    {
      name: 'auth-mobile-chrome',
      testMatch: '**/*.spec.ts',
      use: { 
        ...devices['Pixel 5'],
      },
    },
    
    {
      name: 'auth-mobile-safari',
      testMatch: '**/*.spec.ts',
      use: { 
        ...devices['iPhone 13'],
      },
    },
  ],
  
  /* Run local dev server before starting the tests */
  webServer: {
    command: 'php artisan serve --host=127.0.0.1 --port=8000',
    url: 'http://127.0.0.1:8000',
    reuseExistingServer: !process.env.CI,
    timeout: 120 * 1000,
  },
});

